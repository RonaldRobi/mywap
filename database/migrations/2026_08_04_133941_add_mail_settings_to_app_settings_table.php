<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('mail_mailer', 20)->default('log')->after('mail_from_name');
            $table->string('mail_smtp_host', 255)->nullable()->after('mail_mailer');
            $table->string('mail_smtp_port', 10)->nullable()->after('mail_smtp_host');
            $table->string('mail_smtp_username', 255)->nullable()->after('mail_smtp_port');
            $table->text('mail_smtp_password')->nullable()->after('mail_smtp_username');
            $table->string('mail_smtp_encryption', 10)->nullable()->after('mail_smtp_password');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'mail_mailer',
                'mail_smtp_host',
                'mail_smtp_port',
                'mail_smtp_username',
                'mail_smtp_password',
                'mail_smtp_encryption',
            ]);
        });
    }
};
