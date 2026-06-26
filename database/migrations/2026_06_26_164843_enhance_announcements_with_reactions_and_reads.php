<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cover_image_path')->nullable();
            $table->string('target_criteria')->default('all');
            $table->foreignId('usrah_group_id')->nullable()->constrained('usrah_groups')->nullOnDelete();
        });

        Schema::create('announcement_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reaction', 50)->default('like');
            $table->timestamps();

            $table->unique(['announcement_id', 'user_id']);
        });

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();

            $table->unique(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcement_reactions');

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
            $table->dropForeign(['usrah_group_id']);
            $table->dropColumn(['author_id', 'cover_image_path', 'target_criteria', 'usrah_group_id']);
        });
    }
};
