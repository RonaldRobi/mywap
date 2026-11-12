<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')->insertOrIgnore([
            [
                'key' => 'supporter_invitation',
                'subject' => 'Jemputan Menjadi Ahli {{organization}}',
                'body' => "Assalamualaikum {{name}},\n\nTerima kasih kerana menyertai program kami. Kami ingin menjemput anda mendaftar sebagai ahli {{organization}}.\n\nSila klik pautan di bawah untuk memulakan pendaftaran:\n{{register_link}}\n\nSalam,\nmyWAP",
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')->where('key', 'supporter_invitation')->delete();
    }
};
