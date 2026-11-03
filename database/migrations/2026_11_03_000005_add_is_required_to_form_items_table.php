<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A required add-on is a fee that every participant must pay (e.g. a meal
     * charge) rather than an optional merchandise purchase. Required items are
     * always included in the total; the participant cannot opt out.
     */
    public function up(): void
    {
        Schema::table('form_items', function (Blueprint $table) {
            $table->boolean('is_required')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('form_items', function (Blueprint $table) {
            $table->dropColumn('is_required');
        });
    }
};
