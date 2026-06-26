<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('shipping_address')->nullable()->after('tracking_no');
            $table->string('shipping_postcode', 10)->nullable()->after('shipping_address');
            $table->string('shipping_phone', 20)->nullable()->after('shipping_postcode');
            $table->string('shipping_name', 255)->nullable()->after('shipping_phone');
            $table->decimal('postage_cost', 10, 2)->default(0)->after('total');
            $table->string('courier', 100)->nullable()->after('postage_cost');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_address',
                'shipping_postcode',
                'shipping_phone',
                'shipping_name',
                'postage_cost',
                'courier',
            ]);
        });
    }
};
