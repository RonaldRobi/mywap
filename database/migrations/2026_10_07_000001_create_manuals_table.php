<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manuals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            // user = ahli biasa, admin = admin organisasi, all = kedua-dua
            $table->string('audience')->default('user');
            $table->string('file_path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['audience', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manuals');
    }
};
