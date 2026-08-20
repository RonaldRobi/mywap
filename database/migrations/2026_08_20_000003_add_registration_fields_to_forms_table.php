<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->nullable()->after('description');
            $table->boolean('payment_required')->default(false)->after('price');
            $table->text('terms')->nullable()->after('payment_required');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(['price', 'payment_required', 'terms']);
        });
    }
};
