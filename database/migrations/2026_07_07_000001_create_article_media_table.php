<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('type')->default('image');
            $table->string('caption')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['article_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_media');
    }
};
