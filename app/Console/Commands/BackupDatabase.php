<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Backup pangkalan data mingguan.
 *
 * Menyokong sqlite, mysql/mariadb dan pgsql. Fail backup disimpan di
 * storage/app/backups (di luar public/) dan dipangkas secara automatik
 * supaya cakera VPS tidak penuh.
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database
                            {--keep= : Bilangan backup mingguan untuk disimpan}
                            {--path= : Direktori simpanan backup}
                            {--no-prune : Jangan padam backup lama}';

    protected $description = 'Cipta backup pangkalan data termampat di storage/app/backups';

    /** Saat maksimum untuk utiliti dump (mysqldump/pg_dump). */
    private const DUMP_TIMEOUT_SECONDS = 900;

    public function handle(): int
    {
        $lockPath = storage_path('framework/backup.lock');
        File::ensureDirectoryExists(dirname($lockPath));

        $lock = @fopen($lockPath, 'c');

        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            $this->error('Backup lain sedang berjalan. Cuba lagi kemudian.');

            return self::FAILURE;
        }

        try {
            return $this->runBackup();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function runBackup(): int
    {
        $connectionName = (string) config('database.default');
        $connection = config("database.connections.{$connectionName}");

        if (! is_array($connection)) {
            $this->error("Sambungan pangkalan data tidak dijumpai: {$connectionName}");

            return self::FAILURE;
        }

        $driver = (string) ($connection['driver'] ?? '');
        $directory = (string) ($this->option('path')
            ?: config('backup.path')
            ?: storage_path('app/backups'));

        File::ensureDirectoryExists($directory, 0750, true);

        $realDirectory = realpath($directory);
        $realPublic = realpath(public_path());

        if ($realDirectory !== false && $realPublic !== false
            && ($realDirectory === $realPublic
                || str_starts_with($realDirectory.DIRECTORY_SEPARATOR, $realPublic.DIRECTORY_SEPARATOR))) {
            $this->error('Direktori backup tidak boleh berada di dalam public/.');

            return self::FAILURE;
        }

        if (! is_writable($directory)) {
            $this->error("Direktori backup tidak boleh ditulis: {$directory}");

            return self::FAILURE;
        }

        // SQLite disimpan sebagai snapshot binari; MySQL/PostgreSQL sebagai SQL.
        $extension = $driver === 'sqlite' ? 'sqlite.gz' : 'sql.gz';
        $baseName = 'mywap-db-'.now()->format('Y-m-d_His');
        $finalPath = $directory.DIRECTORY_SEPARATOR.$baseName.'.'.$extension;
        $tempPath = $finalPath.'.part';
        $rawPath = $finalPath.'.raw';

        $this->info("Backup pangkalan data ({$driver}) → {$finalPath}");

        try {
            // Tulis dump mentah dalam direktori backup (bukan /tmp) supaya
            // pangkalan data besar tidak terhenti oleh tmpfs yang kecil.
            // VACUUM INTO juga memerlukan destinasi yang belum wujud.
            @unlink($rawPath);

            match ($driver) {
                'sqlite' => $this->dumpSqlite($connection, $rawPath),
                'mysql', 'mariadb' => $this->dumpMysql($connection, $rawPath),
                'pgsql' => $this->dumpPostgres($connection, $rawPath),
                default => throw new RuntimeException("Driver pangkalan data tidak disokong: {$driver}"),
            };

            if (! is_file($rawPath) || filesize($rawPath) < 100) {
                throw new RuntimeException('Hasil dump kosong atau terlalu kecil.');
            }

            $this->gzip($rawPath, $tempPath);
            @unlink($rawPath);

            if (! is_file($tempPath) || filesize($tempPath) < 100) {
                throw new RuntimeException('Fail backup termampat kosong.');
            }

            $magic = (string) file_get_contents($tempPath, false, null, 0, 2);

            if ($magic !== "\x1f\x8b") {
                throw new RuntimeException('Fail backup bukan gzip yang sah.');
            }

            File::move($tempPath, $finalPath);
            @chmod($finalPath, 0640);

            $hash = hash_file('sha256', $finalPath);

            if ($hash === false) {
                throw new RuntimeException('Gagal mengira checksum backup.');
            }

            File::put($finalPath.'.sha256', $hash.'  '.basename($finalPath).PHP_EOL);
            @chmod($finalPath.'.sha256', 0640);

            $bytes = (int) filesize($finalPath);

            $this->info('✓ Backup siap: '.$this->humanBytes($bytes)
                .' (sha256 '.substr((string) $hash, 0, 12).'…)');

            Log::info('Database backup created', [
                'driver' => $driver,
                'path' => $finalPath,
                'bytes' => $bytes,
                'sha256' => $hash,
            ]);
        } catch (Throwable $e) {
            @unlink($rawPath);
            @unlink($tempPath);

            $this->error('✗ Backup gagal: '.$e->getMessage());

            Log::error('Database backup failed', [
                'driver' => $driver,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        if (! $this->option('no-prune')) {
            $keep = (int) ($this->option('keep') ?: config('backup.keep', 8));
            $this->prune($directory, max(1, $keep));
        }

        return self::SUCCESS;
    }

    /**
     * Snapshot SQLite yang konsisten (termasuk WAL yang telah di-commit).
     *
     * @param  array<string, mixed>  $connection
     */
    private function dumpSqlite(array $connection, string $destination): void
    {
        $database = (string) ($connection['database'] ?? '');

        if ($database === '' || $database === ':memory:') {
            throw new RuntimeException('Laluan pangkalan data SQLite tidak sah.');
        }

        if (! str_starts_with($database, DIRECTORY_SEPARATOR)
            && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $database)) {
            $database = base_path($database);
        }

        if (! is_file($database)) {
            throw new RuntimeException("Fail SQLite tidak dijumpai: {$database}");
        }

        $pdo = new PDO('sqlite:'.$database);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('VACUUM INTO '.$pdo->quote($destination));
        $pdo = null;
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function dumpMysql(array $connection, string $destination): void
    {
        $binary = $this->findBinary('mysqldump');

        if ($binary === null) {
            throw new RuntimeException('mysqldump tidak dijumpai pada pelayan ini.');
        }

        $host = (string) ($connection['host'] ?? '127.0.0.1');
        $port = (string) ($connection['port'] ?? '3306');
        $socket = (string) ($connection['unix_socket'] ?? '');

        $args = [
            $binary,
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--default-character-set='.($connection['charset'] ?? 'utf8mb4'),
            '--user='.($connection['username'] ?? ''),
        ];

        if ($socket !== '') {
            $args[] = '--socket='.$socket;
        } else {
            $args[] = '--host='.$host;
            $args[] = '--port='.$port;
        }

        $database = (string) ($connection['database'] ?? '');

        if ($database === '') {
            throw new RuntimeException('Nama pangkalan data MySQL tidak ditetapkan.');
        }

        $env = [
            'MYSQL_PWD' => (string) ($connection['password'] ?? ''),
        ];

        // MySQL 8 memerlukan --no-tablespaces supaya tidak minta privilege
        // PROCESS. MariaDB lama tidak mengenali opsyen ini, jadi cuba semula
        // tanpanya jika ia ditolak.
        try {
            $this->runDump([...$args, '--no-tablespaces', $database], $env, $destination);
        } catch (RuntimeException $e) {
            if (! str_contains($e->getMessage(), 'no-tablespaces')
                && ! str_contains($e->getMessage(), 'tablespaces')) {
                throw $e;
            }

            @unlink($destination);
            $this->runDump([...$args, $database], $env, $destination);
        }
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function dumpPostgres(array $connection, string $destination): void
    {
        $binary = $this->findBinary('pg_dump');

        if ($binary === null) {
            throw new RuntimeException('pg_dump tidak dijumpai pada pelayan ini.');
        }

        $database = (string) ($connection['database'] ?? '');

        if ($database === '') {
            throw new RuntimeException('Nama pangkalan data PostgreSQL tidak ditetapkan.');
        }

        $this->runDump([
            $binary,
            '--no-owner',
            '--no-privileges',
            '--host='.($connection['host'] ?? '127.0.0.1'),
            '--port='.($connection['port'] ?? '5432'),
            '--username='.($connection['username'] ?? ''),
            '--dbname='.$database,
        ], [
            'PGPASSWORD' => (string) ($connection['password'] ?? ''),
        ], $destination);
    }

    /**
     * Jalankan utiliti dump dan tulis stdout terus ke fail (elak memori besar).
     *
     * @param  list<string>  $args
     * @param  array<string, string>  $env
     */
    private function runDump(array $args, array $env, string $destination): void
    {
        $process = new Process($args, base_path(), $this->processEnv($env), null, self::DUMP_TIMEOUT_SECONDS);

        $handle = fopen($destination, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Gagal menulis fail dump sementara.');
        }

        $stderr = '';
        $writeFailed = false;

        try {
            $process->run(function (string $type, string $buffer) use ($handle, &$stderr, &$writeFailed): void {
                if ($type === Process::ERR) {
                    if (strlen($stderr) < 8192) {
                        $stderr .= $buffer;
                    }

                    return;
                }

                $offset = 0;
                $length = strlen($buffer);

                while ($offset < $length) {
                    $written = fwrite($handle, substr($buffer, $offset));

                    if ($written === false || $written === 0) {
                        $writeFailed = true;

                        return;
                    }

                    $offset += $written;
                }
            });
        } finally {
            fclose($handle);
        }

        if ($writeFailed) {
            throw new RuntimeException('Gagal menulis dump ke cakera (mungkin ruang penuh).');
        }

        if (! $process->isSuccessful()) {
            $detail = trim($stderr) ?: trim($process->getErrorOutput());

            throw new RuntimeException(
                'Arahan dump gagal (exit '.$process->getExitCode().'): '
                .($detail !== '' ? $detail : 'tiada butiran')
            );
        }
    }

    /**
     * Kekalkan pembolehubah persekitaran penting (PATH/HOME) sambil menambah
     * kata laluan, kerana Symfony Process menggantikan env apabila ia diberi.
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function processEnv(array $overrides): array
    {
        $env = [];

        foreach (['PATH', 'HOME', 'TMPDIR', 'LANG', 'LC_ALL'] as $key) {
            $value = getenv($key);

            if ($value !== false) {
                $env[$key] = $value;
            }
        }

        return array_merge($env, $overrides);
    }

    private function gzip(string $source, string $destination): void
    {
        $in = fopen($source, 'rb');

        if ($in === false) {
            throw new RuntimeException('Gagal membuka fail dump untuk dimampatkan.');
        }

        $out = gzopen($destination, 'wb9');

        if ($out === false) {
            fclose($in);

            throw new RuntimeException('Gagal mencipta fail gzip.');
        }

        try {
            while (! feof($in)) {
                $chunk = fread($in, 1024 * 1024);

                if ($chunk === false) {
                    throw new RuntimeException('Gagal membaca fail dump.');
                }

                if ($chunk === '') {
                    break;
                }

                if (gzwrite($out, $chunk) !== strlen($chunk)) {
                    throw new RuntimeException('Gagal menulis fail gzip (mungkin ruang penuh).');
                }
            }
        } finally {
            fclose($in);
            gzclose($out);
        }
    }

    private function prune(string $directory, int $keep): void
    {
        $files = glob($directory.DIRECTORY_SEPARATOR.'mywap-db-*.gz') ?: [];

        usort($files, fn (string $a, string $b): int => (int) filemtime($b) <=> (int) filemtime($a));

        foreach (array_slice($files, $keep) as $file) {
            @unlink($file);
            @unlink($file.'.sha256');

            $this->line('  – Buang backup lama: '.basename($file));
        }

        // Buang sisa .part/.raw yang tertinggal lebih 24 jam (cth. proses mati).
        foreach (['part', 'raw'] as $suffix) {
            foreach (glob($directory.DIRECTORY_SEPARATOR.'mywap-db-*.'.$suffix) ?: [] as $leftover) {
                if ((int) filemtime($leftover) < time() - 86400) {
                    @unlink($leftover);
                }
            }
        }
    }

    private function findBinary(string $name): ?string
    {
        if (function_exists('shell_exec')) {
            $path = trim((string) @shell_exec('command -v '.escapeshellarg($name).' 2>/dev/null'));

            if ($path !== '' && @is_executable($path)) {
                return $path;
            }
        }

        foreach ([
            '/usr/bin/'.$name,
            '/usr/local/bin/'.$name,
            '/opt/homebrew/bin/'.$name,
            '/opt/local/bin/'.$name,
        ] as $candidate) {
            if (@is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return round($value, $value >= 10 ? 0 : 1).' '.$units[$unit];
    }
}
