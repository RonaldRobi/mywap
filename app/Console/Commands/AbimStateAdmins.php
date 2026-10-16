<?php

namespace App\Console\Commands;

use Database\Seeders\AbimStateAdminSeeder;
use Illuminate\Console\Command;

/**
 * Cipta/kemas kini akaun "Admin Negeri" ABIM (14 negeri, peranan Admin).
 *
 *   php artisan abim:state-admins
 *
 * Idempotent — selamat dijalankan berulang (katalaluan akaun sedia ada tidak
 * ditimpa).
 */
class AbimStateAdmins extends Command
{
    protected $signature = 'abim:state-admins';

    protected $description = 'Cipta akaun Admin Negeri ABIM (14 negeri, peranan Admin Organisasi).';

    public function handle(): int
    {
        $this->call('db:seed', [
            '--class' => AbimStateAdminSeeder::class,
            '--force' => true,
        ]);

        return self::SUCCESS;
    }
}
