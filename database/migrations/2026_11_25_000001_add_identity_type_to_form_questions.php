<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_questions', function (Blueprint $table) {
            $table->string('identity_type')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('form_questions', function (Blueprint $table) {
            $table->dropColumn('identity_type');
        });
    }
};
