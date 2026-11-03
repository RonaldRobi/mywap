<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Variants for a form item (e.g. jersey sizes S/M/L/XL). A NULL price falls
     * back to the parent item price, so a size can share the same price while
     * one variant (e.g. XL) can cost more. Stock NULL means unlimited.
     */
    public function up(): void
    {
        Schema::create('form_item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_item_id')->constrained('form_items')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 12, 2)->nullable();
            $table->integer('stock')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['form_item_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_item_variants');
    }
};
