<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class EnsureSchedulerTest extends TestCase
{
    public function test_existing_project_cron_is_preserved(): void
    {
        Process::fake(fn () => Process::result(output: '* * * * * cd '.base_path().' && php artisan schedule:run'));
        Process::preventStrayProcesses();

        $this->artisan('app:ensure-scheduler')->assertSuccessful();

        Process::assertRanTimes(fn ($process) => $process->command === ['crontab', '-l'], 1);
        Process::assertNotRan(fn ($process) => $process->command === ['crontab', '-']);
    }

    public function test_installer_keeps_other_jobs_and_ignores_commented_entries(): void
    {
        $existing = "0 0 * * * /usr/bin/backup\n# * * * * * cd ".base_path()." && php artisan schedule:run\n";
        Process::fake(fn ($process) => Process::result(output: $process->command === ['crontab', '-l'] ? $existing : ''));
        Process::preventStrayProcesses();

        $this->artisan('app:ensure-scheduler')->assertSuccessful();

        Process::assertRan(fn ($process) => $process->command === ['crontab', '-']
            && str_contains($process->input, $existing)
            && str_contains($process->input, '* * * * * cd '.escapeshellarg(base_path())));
    }

    public function test_no_existing_crontab_can_be_installed(): void
    {
        Process::fake(fn ($process) => $process->command === ['crontab', '-l']
            ? Process::result(errorOutput: 'no crontab for www-data', exitCode: 1)
            : Process::result());
        Process::preventStrayProcesses();

        $this->artisan('app:ensure-scheduler')->assertSuccessful();
        Process::assertRan(fn ($process) => $process->command === ['crontab', '-']);
    }

    public function test_read_error_does_not_overwrite_crontab(): void
    {
        Process::fake(fn () => Process::result(errorOutput: 'Permission denied', exitCode: 1));
        Process::preventStrayProcesses();

        $this->artisan('app:ensure-scheduler')->assertFailed();

        Process::assertNotRan(fn ($process) => $process->command === ['crontab', '-']);
    }
}
