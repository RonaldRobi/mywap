<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                  ->constrained('organizations')
                  ->cascadeOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->enum('type', ['physical', 'online'])->default('physical');
            $table->string('location_or_link')->nullable(); // Venue address or Zoom/Meet URL

            $table->dateTime('start_time');
            $table->dateTime('end_time');

            $table->string('featured_image_path')->nullable(); // S3 path

            // 32-char random token — the secret embedded in the QR code URL.
            // Auto-generated in Event::booted(); unique constraint prevents collisions.
            $table->string('attendance_token', 64)->unique();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'start_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
