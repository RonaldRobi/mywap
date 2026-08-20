<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')->insertOrIgnore([
            'key' => 'registration_confirmation',
            'subject' => 'Pengesahan Pendaftaran: {{event_title}}',
            'body' => "Assalamualaikum {{name}},\n\nPendaftaran anda telah diterima. Maklumat pendaftaran anda:\n\nNo Pendaftaran: {{registration_no}}\nProgram: {{event_title}}\nTarikh: {{event_date}}\nLokasi: {{location}}\nStatus Bayaran: {{payment_status}}\n\nSila simpan No Pendaftaran anda untuk semakan kehadiran pada hari program.\n\nSalam,\nmyWAP",
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')->where('key', 'registration_confirmation')->delete();
    }
};
