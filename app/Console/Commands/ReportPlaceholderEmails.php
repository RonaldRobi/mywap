<?php

namespace App\Console\Commands;

use App\Models\Scopes\OrganizationScope;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Laporan kesihatan emel ahli (tiada perubahan data).
 *
 * Ahli lama yang di-import tanpa emel sebenar diberi emel placeholder
 * "{noIC}@mywap.my". OTP / pautan reset tidak akan sampai ke alamat itu, jadi
 * ahli tersebut perlu melalui aliran "Log Masuk Kali Pertama" untuk menetapkan
 * emel sebenar + kata laluan. Arahan ini membantu mengenal pasti siapa yang
 * terjejas (dan berapa ramai) sebelum menghubungi mereka.
 */
class ReportPlaceholderEmails extends Command
{
    protected $signature = 'members:email-health {--export= : Simpan senarai ke fail CSV}';

    protected $description = 'Senaraikan ahli yang menggunakan emel placeholder import (laporan sahaja, tiada perubahan data)';

    public function handle(): int
    {
        $candidates = User::withoutGlobalScope(OrganizationScope::class)
            ->with('organization:id,name')
            ->whereNotNull('email')
            ->where('email', 'like', '%@mywap.my')
            ->orderBy('name')
            ->get();

        $affected = $candidates->filter(fn (User $user) => $user->emailIsPlaceholder());

        $total = $affected->count();
        $pendingFirstLogin = $affected->filter(fn (User $user) => is_null($user->first_login_at))->count();
        $withoutOrganization = $affected->filter(fn (User $user) => is_null($user->current_organization_id))->count();

        $this->info('Laporan Emel Placeholder Ahli');
        $this->line('Jumlah ahli dengan emel placeholder: '.$total);
        $this->line('  - Belum log masuk kali pertama: '.$pendingFirstLogin);
        $this->line('  - Tiada organisasi semasa: '.$withoutOrganization);

        if ($total === 0) {
            $this->info('Tiada ahli terjejas. Semua emel kelihatan sah.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('Ahli ini perlu melalui "Log Masuk Kali Pertama" untuk menetapkan emel sebenar:');

        $this->table(
            ['No. Ahli', 'Nama', 'No. IC', 'Emel', 'First Login', 'Organisasi'],
            $affected->map(fn (User $user) => [
                $user->member_no ?? '-',
                $user->name ?? '-',
                $user->ic_number ?? '-',
                $user->email,
                is_null($user->first_login_at) ? 'Belum' : 'Sudah',
                $user->organization?->name ?? '-',
            ])->values()->all(),
        );

        if ($exportPath = $this->option('export')) {
            $this->exportCsv($exportPath, $affected);
            $this->info("Senarai dieksport ke: {$exportPath}");
        }

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $users
     */
    private function exportCsv(string $path, $users): void
    {
        $handle = fopen($path, 'w');

        if ($handle === false) {
            $this->error("Tidak dapat menulis fail: {$path}");

            return;
        }

        fputcsv($handle, ['member_no', 'name', 'ic_number', 'email', 'first_login_at', 'organization']);

        foreach ($users as $user) {
            fputcsv($handle, [
                $user->member_no,
                $user->name,
                $user->ic_number,
                $user->email,
                optional($user->first_login_at)->toDateTimeString(),
                $user->organization?->name,
            ]);
        }

        fclose($handle);
    }
}
