<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->decimal('fee_amount', 10, 2)->default(50.00)->after('max_age');
        });

        // Also add reference + description to payments for better tracking
        Schema::table('payments', function (Blueprint $table) {
            $table->string('reference')->nullable()->after('status');
            $table->string('description')->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('fee_amount');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['reference', 'description']);
        });
    }
};
