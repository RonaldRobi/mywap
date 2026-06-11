<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infaq_donations', function (Blueprint $table) {
            $table->string('donor_name')->nullable();
            $table->string('donor_phone')->nullable();
            $table->string('donor_email')->nullable();
            $table->text('prayer_message')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('wants_updates')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('infaq_donations', function (Blueprint $table) {
            $table->dropColumn([
                'donor_name',
                'donor_phone',
                'donor_email',
                'prayer_message',
                'is_anonymous',
                'wants_updates',
            ]);
        });
    }
};
