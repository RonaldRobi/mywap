<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('membership_letter_enabled')->default(false);
            $table->string('membership_letter_subject')->nullable();
            $table->longText('membership_letter_body')->nullable();
            $table->string('membership_letter_letterhead_path')->nullable();
            $table->string('membership_letter_signatory_name')->nullable();
            $table->string('membership_letter_signatory_position')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('membership_letter_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('membership_letter_sent_at');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'membership_letter_enabled',
                'membership_letter_subject',
                'membership_letter_body',
                'membership_letter_letterhead_path',
                'membership_letter_signatory_name',
                'membership_letter_signatory_position',
            ]);
        });
    }
};
