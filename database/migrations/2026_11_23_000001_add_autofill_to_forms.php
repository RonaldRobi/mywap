<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->boolean('autofill_enabled')->default(false)->after('allow_public');
        });

        Schema::table('form_questions', function (Blueprint $table) {
            $table->string('autofill_key')->nullable()->after('help_text');
        });
    }

    public function down(): void
    {
        Schema::table('form_questions', function (Blueprint $table) {
            $table->dropColumn('autofill_key');
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('autofill_enabled');
        });
    }
};
