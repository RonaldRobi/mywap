<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('year');
            $table->integer('total_rows')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('skip_count')->default(0);
            $table->string('csv_file')->nullable();
            $table->string('proof_file')->nullable();
            $table->json('errors')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_imports');
    }
};
