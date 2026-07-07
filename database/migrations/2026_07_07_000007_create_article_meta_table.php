<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_meta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('meta_key');
            $table->text('meta_value')->nullable();
            $table->timestamps();

            $table->unique(['article_id', 'meta_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_meta');
    }
};
