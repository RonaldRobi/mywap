<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the organizations table representing PKPIM, ABIM, and WADAH tiers.
     * The color_theme column stores a Tailwind/HEX accent color for dynamic UI theming.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');                  // e.g. PKPIM, ABIM, WADAH
            $table->string('slug')->unique();        // pkpim | abim | wadah  (used in routing & theming)
            $table->string('color_theme')->nullable(); // Hex or Tailwind class for accent color
            $table->unsignedTinyInteger('min_age');  // Minimum age for this tier (0, 20, 30)
            $table->unsignedTinyInteger('max_age')->nullable(); // NULL = no upper bound (WADAH)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
