<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Run the migrations.
     *
     * Stores an immutable audit trail of every NGO tier change per member.
     * Indexed on (user_id, transitioned_at) for fast timeline queries.
     */
    public function up(): void
    {
        Schema::create('user_transition_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // NULL means the member joined directly into this tier
            // (e.g., registered at age 25 → starts directly in ABIM).
            $table->foreignId('from_organization_id')
                  ->nullable()
                  ->constrained('organizations')
                  ->nullOnDelete();

            $table->foreignId('to_organization_id')
                  ->constrained('organizations')
                  ->cascadeOnDelete();

            // Explicit column so historical back-fills are possible
            // independently of created_at.
            $table->timestamp('transitioned_at');

            $table->timestamps();

            // Powers the Profile Journey Timeline query.
            $table->index(['user_id', 'transitioned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_transition_histories');
    }
};
