<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('subject');
            $table->text('body');
            $table->timestamps();
        });

        DB::table('email_templates')->insert([
            [
                'key' => 'otp_login',
                'subject' => 'Kod Pengesahan Log Masuk myWAP',
                'body' => "Assalamualaikum {{name}},\n\nGunakan kod OTP di bawah untuk melengkapkan log masuk ke akaun myWAP anda. Kod ini sah untuk 5 minit.\n\nKod OTP: {{code}}\n\nJika anda tidak meminta kod ini, sila abaikan email ini.\n\nSalam,\nmyWAP",
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
