<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_post_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_post_id')->constrained('news_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('reaction', ['like', 'dislike']);
            $table->timestamps();

            $table->unique(['news_post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_post_reactions');
    }
};
