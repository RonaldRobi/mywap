<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeployController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        $deployToken = config('app.deploy_token');
        if (!$deployToken || $token !== $deployToken) {
            Log::channel('deploy')->warning('Invalid deploy token attempt', [
                'ip' => $request->ip(),
            ]);
            abort(401);
        }

        $payload = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        if ($signature) {
            $expected = 'sha256=' . hash_hmac('sha256', $payload, $deployToken);
            if (!hash_equals($expected, $signature)) {
                Log::channel('deploy')->warning('Invalid webhook signature', [
                    'ip' => $request->ip(),
                ]);
                abort(401);
            }
        }

        set_time_limit(0);

        $basePath = base_path();
        $log = [];

        $commands = [
            "cd $basePath && git pull origin main 2>&1",
            "cd $basePath && npm ci --no-optional 2>&1",
            "cd $basePath && NODE_OPTIONS=\"--max-old-space-size=1024\" npx --yes vite build 2>&1",
            "cd $basePath && php artisan migrate --force 2>&1",
            "cd $basePath && php artisan optimize 2>&1",
        ];

        foreach ($commands as $cmd) {
            $output = [];
            $exitCode = 0;
            exec($cmd, $output, $exitCode);

            Log::channel('deploy')->info('Command result', [
                'command' => $cmd,
                'exit_code' => $exitCode,
                'output' => implode("\n", $output),
            ]);

            $log[] = [
                'command' => $cmd,
                'output' => implode("\n", $output),
                'exit_code' => $exitCode,
            ];
        }

        Log::channel('deploy')->info('Deploy completed', ['log' => $log]);

        return response()->json([
            'success' => true,
            'log' => $log,
        ]);
    }
}
