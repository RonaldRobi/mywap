<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional add-ons / merchandise that can be purchased alongside an event
     * registration (e.g. an event jersey). Scoped to a Form so the Form Builder
     * remains the single source of truth. Stock NULL means unlimited.
     */
    public function up(): void
    {
        Schema::create('form_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('image')->nullable();
            $table->integer('stock')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['form_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_items');
    }
};
