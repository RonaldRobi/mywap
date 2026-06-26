<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infaq_donations', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('wants_updates');
            $table->string('frequency')->nullable()->after('is_recurring');
            $table->string('mandate_id')->nullable()->after('frequency');
            $table->string('mandate_ref')->nullable()->after('mandate_id');
            $table->date('next_billing_date')->nullable()->after('mandate_ref');
            $table->string('recurring_status')->nullable()->after('next_billing_date');
        });
    }

    public function down(): void
    {
        Schema::table('infaq_donations', function (Blueprint $table) {
            $table->dropColumn([
                'is_recurring',
                'frequency',
                'mandate_id',
                'mandate_ref',
                'next_billing_date',
                'recurring_status',
            ]);
        });
    }
};
