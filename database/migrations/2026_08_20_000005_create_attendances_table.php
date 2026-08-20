<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained('registrations')->cascadeOnDelete();
            $table->timestamp('attended_at')->nullable();
            $table->string('method')->default('scan')->index();

            $table->timestamps();

            $table->unique('registration_id');
            $table->index(['event_id', 'method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
