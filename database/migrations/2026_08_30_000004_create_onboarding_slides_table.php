<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_slides', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('slide_order')->unique();
            $table->string('title', 120)->nullable();
            $table->text('body')->nullable();
            $table->string('button_label', 40)->nullable();
            $table->string('button_url', 2048)->nullable();
            $table->string('background_start', 7)->default('#071525');
            $table->string('background_end', 7)->default('#2F6B32');
            $table->string('text_color', 7)->default('#FFFFFF');
            $table->string('media_path', 2048)->nullable();
            $table->string('media_type', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('onboarding_slides')->insert([
            ['slide_order' => 1, 'title' => 'Selamat Datang ke myWAP', 'body' => 'Satu aplikasi untuk keahlian, program dan khidmat gerakan.', 'button_label' => 'Seterusnya', 'background_start' => '#071525', 'background_end' => '#2F6B32', 'text_color' => '#FFFFFF', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slide_order' => 2, 'title' => 'Semua Dalam Satu Tempat', 'body' => 'Ikuti acara, urus keahlian dan temui kemudahan organisasi anda.', 'button_label' => 'Seterusnya', 'background_start' => '#123D2A', 'background_end' => '#6FBF8A', 'text_color' => '#FFFFFF', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slide_order' => 3, 'title' => 'Bersedia Untuk Bermula', 'body' => 'Log masuk untuk meneruskan ke pengalaman myWAP anda.', 'button_label' => 'Mula', 'background_start' => '#2F6B32', 'background_end' => '#071525', 'text_color' => '#FFFFFF', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_slides');
    }
};
