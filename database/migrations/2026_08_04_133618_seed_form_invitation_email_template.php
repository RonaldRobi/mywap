<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')->insertOrIgnore([
            'key' => 'form_invitation',
            'subject' => 'Jemputan Borang: {{form_title}}',
            'body' => "Assalamualaikum {{name}},\n\nAnda dijemput untuk mengisi borang berikut:\n\nBorang: {{form_title}}\nOrganisasi: {{organization}}\n\nSila klik pautan di bawah untuk membuka borang:\n{{form_link}}\n\nSalam,\nmyWAP",
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')->where('key', 'form_invitation')->delete();
    }
};
