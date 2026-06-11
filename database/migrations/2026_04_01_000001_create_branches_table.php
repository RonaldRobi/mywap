<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates the branches table.
     *
     * Each branch belongs to one organization (PKPIM / ABIM / WADAH) and
     * represents a state/regional chapter, e.g. "ABIM Selangor".
     * Members will pick their cawangan when completing their profile.
     */
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                  ->constrained('organizations')
                  ->cascadeOnDelete();
            $table->string('name');                        // e.g. "Selangor", "Johor"
            $table->string('state')->nullable();           // standardised state name
            $table->text('address')->nullable();           // full postal address
            $table->string('phone', 30)->nullable();       // contact number
            $table->string('email')->nullable();           // contact email
            $table->string('logo_path')->nullable();       // optional per-branch logo (else uses org logo)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
