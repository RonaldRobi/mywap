<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use PDO;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    private string $workspace;

    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir().'/mywap-backup-test-'.uniqid('', true);
        mkdir($this->workspace, 0700, true);

        $this->databasePath = $this->workspace.'/database.sqlite';

        $pdo = new PDO('sqlite:'.$this->databasePath);
        $pdo->exec('CREATE TABLE greetings (id INTEGER PRIMARY KEY, message TEXT)');
        $pdo->exec("INSERT INTO greetings (message) VALUES ('salam')");
        $pdo = null;

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->workspace.'/backups/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->workspace.'/backups');
        @unlink($this->databasePath);
        @rmdir($this->workspace);

        parent::tearDown();
    }

    public function test_it_creates_a_compressed_sqlite_snapshot_with_checksum(): void
    {
        $backupDirectory = $this->workspace.'/backups';

        $exitCode = Artisan::call('backup:database', [
            '--path' => $backupDirectory,
            '--no-prune' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $files = glob($backupDirectory.'/mywap-db-*.sqlite.gz') ?: [];

        $this->assertCount(1, $files);
        $this->assertSame("\x1f\x8b", file_get_contents($files[0], false, null, 0, 2));
        $this->assertFileExists($files[0].'.sha256');

        $hash = trim((string) file_get_contents($files[0].'.sha256'));
        $this->assertStringStartsWith(hash_file('sha256', $files[0]).'  ', $hash);
    }

    public function test_backup_webhook_rejects_an_invalid_token(): void
    {
        $this->post('/__backup/wrong-token')->assertUnauthorized();
    }

    public function test_backup_webhook_creates_a_backup_with_the_valid_token(): void
    {
        config([
            'app.deploy_token' => 'test-backup-token',
            'backup.path' => $this->workspace.'/backups',
        ]);

        $this->post('/__backup/test-backup-token')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('exit_code', 0);

        $this->assertNotEmpty(glob($this->workspace.'/backups/mywap-db-*.gz') ?: []);
    }

    public function test_it_prunes_old_backups_beyond_the_retention_limit(): void
    {
        $backupDirectory = $this->workspace.'/backups';
        mkdir($backupDirectory, 0700, true);

        foreach (['2000-01-01_000000', '2000-01-02_000000'] as $stamp) {
            $path = $backupDirectory.'/mywap-db-'.$stamp.'.sqlite.gz';
            file_put_contents($path, 'old');
            touch($path, time() - 86400);
        }

        $exitCode = Artisan::call('backup:database', [
            '--path' => $backupDirectory,
            '--keep' => 1,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $files = glob($backupDirectory.'/mywap-db-*.gz') ?: [];

        $this->assertCount(1, $files);
        $this->assertStringNotContainsString('2000-01', $files[0]);
    }
}
