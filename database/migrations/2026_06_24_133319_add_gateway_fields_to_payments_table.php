<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway')->default('dummy')->after('reference');
            $table->string('gateway_ref')->nullable()->after('gateway');
            $table->string('gateway_url')->nullable()->after('gateway_ref');
            $table->foreignId('organization_id')->nullable()->constrained()->after('gateway_url');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['gateway', 'gateway_ref', 'gateway_url']);
        });
    }
};
