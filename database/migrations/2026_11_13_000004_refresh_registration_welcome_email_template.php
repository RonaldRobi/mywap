<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $oldBodies = [
            "Assalamualaikum {{name}},\n\nTahniah! Akaun myWAP anda telah diaktifkan.\n\nNo Ahli: {{member_no}}\nOrganisasi: {{organization}}\n\nAnda kini boleh log masuk kali pertama menggunakan No IC dan kod OTP yang akan dihantar ke emel anda.\n\n{{login_link}}\n\nSalam,\nmyWAP",
        ];

        DB::table('email_templates')
            ->where('key', 'registration_activated')
            ->whereIn('body', $oldBodies)
            ->update([
                'subject' => 'Selamat datang ke {{organization}} - myWAP',
                'body' => "Assalamualaikum {{name}},\n\nSelamat datang ke {{organization}}! Pendaftaran anda sebagai ahli telah berjaya. Akaun myWAP anda kini aktif dan sedia digunakan.\n\nPendaftaran adalah percuma. Gunakan panduan log masuk di bawah untuk mula menggunakan akaun anda.",
                'updated_at' => now(),
            ]);

        DB::table('email_templates')
            ->where('key', 'new_member_alert')
            ->where('body', 'like', '%telah mendaftar dan membuat bayaran:%')
            ->update([
                'body' => "Assalamualaikum Admin,\n\nSeorang ahli baharu telah mendaftar:\n\nNama: {{name}}\nNo Ahli: {{member_no}}\nNo IC: {{ic_number}}\nOrganisasi: {{organization}}\nCawangan: {{branch}}\n\nPendaftaran adalah percuma. Sila semak profil ahli di panel pentadbir.\n\nSalam,\nmyWAP",
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Template copy is intentionally left in place on rollback.
    }
};
