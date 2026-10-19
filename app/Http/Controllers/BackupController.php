<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Pencetus backup manual melalui webhook (untuk VPS tanpa akses SSH).
 *
 * Token yang sama dengan DEPLOY_TOKEN digunakan. Endpoint ini hanya
 * mencipta backup; ia tidak boleh memuat turun atau memadam data.
 */
class BackupController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        $expected = (string) config('app.deploy_token');

        if ($expected === '' || ! hash_equals($expected, $token)) {
            abort(401);
        }

        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        try {
            $exitCode = Artisan::call('backup:database');
            $output = trim(Artisan::output());
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => $exitCode === 0,
            'exit_code' => $exitCode,
            'output' => $this->tail($output),
            'backups' => $this->backups(),
        ], $exitCode === 0 ? 200 : 500);
    }

    private function tail(string $output): string
    {
        $lines = preg_split('/\R/', $output) ?: [];

        return implode("\n", array_slice($lines, -80));
    }

    /**
     * @return list<array{name: string, bytes: int, modified_at: string}>
     */
    private function backups(): array
    {
        $directory = (string) (config('backup.path') ?: storage_path('app/backups'));
        $files = glob($directory.DIRECTORY_SEPARATOR.'mywap-db-*.gz') ?: [];

        usort($files, fn (string $a, string $b): int => (int) filemtime($b) <=> (int) filemtime($a));

        return array_map(fn (string $file): array => [
            'name' => basename($file),
            'bytes' => (int) filesize($file),
            'modified_at' => date(DATE_ATOM, (int) filemtime($file)),
        ], array_slice($files, 0, 20));
    }
}
