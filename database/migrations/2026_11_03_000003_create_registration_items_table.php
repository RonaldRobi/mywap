<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable snapshot of the add-ons a participant selected. Names, variant
     * names and prices are copied at registration time so later edits to the
     * form item never rewrite history (and event-day verification stays exact).
     */
    public function up(): void
    {
        Schema::create('registration_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('registrations')->cascadeOnDelete();
            $table->foreignId('form_item_id')->nullable()->constrained('form_items')->nullOnDelete();
            $table->foreignId('form_item_variant_id')->nullable()->constrained('form_item_variants')->nullOnDelete();
            $table->string('name');
            $table->string('variant_name')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();

            $table->index('registration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_items');
    }
};
