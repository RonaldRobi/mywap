<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Webhook deploy (git pull + migrate + optimize) untuk VPS lama yang tiada SSH.
 *
 * Prinsip:
 *  - Jangan sekali-kali hang: git dipaksa non-interaktif dan setiap langkah
 *    dibatasi oleh `timeout` (jika tersedia) supaya ia tidak melebihi had
 *    Apache/php-fpm.
 *  - Jangan senyap: jika langkah kritikal gagal, pulangkan HTTP 500 beserta
 *    langkah yang gagal dan hujung output supaya puncanya jelas.
 *  - Kegagalan log tidak boleh meruntuhkan deploy (dibalut try/catch).
 */
class DeployController extends Controller
{
    /** Saat maksimum setiap arahan sebelum ia dibunuh. */
    private const STEP_TIMEOUT_SECONDS = 240;

    /** Bilangan baris output terakhir yang dikembalikan setiap langkah. */
    private const OUTPUT_TAIL_LINES = 40;

    public function __invoke(Request $request, string $token)
    {
        $deployToken = config('app.deploy_token');

        if (! $deployToken || ! hash_equals((string) $deployToken, (string) $token)) {
            $this->safeLog('warning', 'Invalid deploy token attempt', ['ip' => $request->ip()]);
            abort(401);
        }

        $payload = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        if ($signature) {
            $expected = 'sha256='.hash_hmac('sha256', $payload, $deployToken);
            if (! hash_equals($expected, $signature)) {
                $this->safeLog('warning', 'Invalid webhook signature', ['ip' => $request->ip()]);
                abort(401);
            }
        }

        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $basePath = base_path();
        $php = $this->phpBinary();
        $timeoutBin = $this->binaryPath('timeout');

        // Langkah yang dijalankan. `critical` = kegagalan memulangkan HTTP 500.
        $steps = [
            ['key' => 'fetch', 'critical' => true, 'cmd' => $this->gitCommand($basePath, 'fetch origin main')],
            ['key' => 'reset', 'critical' => true, 'cmd' => $this->gitCommand($basePath, 'reset --hard origin/main')],
            ['key' => 'migrate', 'critical' => true, 'cmd' => escapeshellarg($php).' artisan migrate --force'],
            // Idempotent: cipta akaun Admin Negeri ABIM. Tidak kritikal — kegagalan
            // seeding tidak seharusnya menggagalkan deploy kod.
            ['key' => 'seed_admins', 'critical' => false, 'cmd' => escapeshellarg($php).' artisan abim:state-admins'],
            ['key' => 'optimize', 'critical' => true, 'cmd' => escapeshellarg($php).' artisan optimize'],
        ];

        $log = [];
        $failedCritical = null;

        if (! function_exists('exec')) {
            return response()->json([
                'success' => false,
                'error' => 'PHP exec() dilumpuhkan pada pelayan ini — deploy automatik tidak boleh dijalankan.',
                'log' => [],
            ], 500);
        }

        try {
            foreach ($steps as $step) {
                $command = $step['cmd'];

                // Bungkus dengan `sh -c` supaya pembolehubah persekitaran (cth.
                // GIT_TERMINAL_PROMPT=0) berfungsi, dan dengan `timeout` untuk
                // elak arahan hang (network/DTD).
                if ($timeoutBin !== null) {
                    $command = escapeshellarg($timeoutBin).' '.self::STEP_TIMEOUT_SECONDS.' sh -c '.escapeshellarg($command);
                } else {
                    $command = 'sh -c '.escapeshellarg($command);
                }

                $fullCommand = 'cd '.escapeshellarg($basePath).' && '.$command.' 2>&1';

                $output = [];
                $exitCode = 0;
                exec($fullCommand, $output, $exitCode);

                $tail = $this->tail($output);
                $ok = $exitCode === 0;

                $log[] = [
                    'step' => $step['key'],
                    'command' => $fullCommand,
                    'exit_code' => $exitCode,
                    'ok' => $ok,
                    'output' => implode("\n", $tail),
                ];

                $this->safeLog('info', 'Deploy step finished', [
                    'step' => $step['key'],
                    'exit_code' => $exitCode,
                    'output' => implode("\n", $tail),
                ]);

                if (! $ok && $step['critical'] && $failedCritical === null) {
                    $failedCritical = $step['key'];
                }
            }
        } catch (Throwable $e) {
            $this->safeLog('error', 'Deploy crashed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'log' => $log,
            ], 500);
        }

        $this->safeLog('info', 'Deploy completed', [
            'failed_critical' => $failedCritical,
        ]);

        $status = $failedCritical === null ? 200 : 500;

        return response()->json([
            'success' => $failedCritical === null,
            'failed_step' => $failedCritical,
            'log' => $log,
        ], $status);
    }

    /**
     * Perintah git yang tidak akan pernah minta input (elak hang webhook).
     */
    private function gitCommand(string $basePath, string $args): string
    {
        $git = $this->binaryPath('git') ?? 'git';

        return 'GIT_TERMINAL_PROMPT=0 GIT_ASKPASS=/bin/true '
            ."GIT_SSH_COMMAND='ssh -oBatchMode=yes -oStrictHostKeyChecking=accept-new' "
            .escapeshellarg($git).' -C '.escapeshellarg($basePath).' '.$args;
    }

    /**
     * Cari binari PHP yang sedang digunakan (lebih dipercayai daripada `php`).
     */
    private function phpBinary(): string
    {
        if (PHP_BINARY && @is_executable(PHP_BINARY)) {
            return PHP_BINARY;
        }

        return 'php';
    }

    /**
     * Cari laluan mutlak sesuatu binari melalui PATH, atau null jika tiada.
     */
    private function binaryPath(string $name): ?string
    {
        if (! function_exists('shell_exec')) {
            return null;
        }

        $path = trim((string) @shell_exec('command -v '.escapeshellarg($name).' 2>/dev/null'));

        return $path !== '' && @is_executable($path) ? $path : null;
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function tail(array $lines): array
    {
        if (count($lines) <= self::OUTPUT_TAIL_LINES) {
            return $lines;
        }

        return array_merge(
            ['… ('.(count($lines) - self::OUTPUT_TAIL_LINES).' baris awal dipotong) …'],
            array_slice($lines, -self::OUTPUT_TAIL_LINES),
        );
    }

    /**
     * Log tanpa membenarkan kegagalan penulisan log meruntuhkan deploy.
     */
    private function safeLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::channel('deploy')->{$level}($message, $context);
        } catch (Throwable) {
            // Abaikan — log bukan bahagian kritikal deploy.
        }
    }
}
