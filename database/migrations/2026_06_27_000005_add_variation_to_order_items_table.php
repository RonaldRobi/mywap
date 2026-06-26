<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_variation_option_id')->nullable()->constrained('product_variation_options')->onDelete('set null');
            $table->text('variation_snapshot')->nullable()->after('product_variation_option_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_variation_option_id']);
            $table->dropColumn(['product_variation_option_id', 'variation_snapshot']);
        });
    }
};
