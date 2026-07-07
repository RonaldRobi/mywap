<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_article_category', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_category_id')->constrained('article_categories')->cascadeOnDelete();
            $table->primary(['article_id', 'article_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_article_category');
    }
};
