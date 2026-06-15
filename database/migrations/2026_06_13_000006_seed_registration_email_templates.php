<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')->insertOrIgnore([
            [
                'key' => 'registration_received',
                'subject' => 'Pendaftaran Diterima - myWAP',
                'body' => "Assalamualaikum {{name}},\n\nPendaftaran anda sebagai ahli {{organization}} telah diterima.\n\nNo Ahli: {{member_no}}\nOrganisasi: {{organization}}\nCawangan: {{branch}}\nYuran: RM {{fee}}\n\nSila lengkapkan bayaran yuran untuk mengaktifkan akaun anda.\n\nSalam,\nmyWAP",
            ],
            [
                'key' => 'registration_activated',
                'subject' => 'Akaun Anda Telah Diaktifkan - myWAP',
                'body' => "Assalamualaikum {{name}},\n\nTahniah! Akaun myWAP anda telah diaktifkan.\n\nNo Ahli: {{member_no}}\nOrganisasi: {{organization}}\n\nAnda kini boleh log masuk kali pertama menggunakan No IC dan kod OTP yang akan dihantar ke emel anda.\n\n{{login_link}}\n\nSalam,\nmyWAP",
            ],
            [
                'key' => 'new_member_alert',
                'subject' => 'Ahli Baru Mendaftar - {{name}}',
                'body' => "Assalamualaikum Admin,\n\nSeorang ahli baru telah mendaftar dan membuat bayaran:\n\nNama: {{name}}\nNo Ahli: {{member_no}}\nNo IC: {{ic_number}}\nOrganisasi: {{organization}}\nCawangan: {{branch}}\nYuran: RM {{fee}}\n\nSalam,\nmyWAP",
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')->whereIn('key', [
            'registration_received',
            'registration_activated',
            'new_member_alert',
        ])->delete();
    }
};
