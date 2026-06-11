<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usrah_group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usrah_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_naqib')->default(false);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['usrah_group_id', 'user_id']);
            $table->index(['user_id', 'is_naqib']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usrah_group_user');
    }
};
