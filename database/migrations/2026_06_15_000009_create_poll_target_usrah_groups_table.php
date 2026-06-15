<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poll_target_usrah_groups', function (Blueprint $table) {
            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('usrah_group_id')->constrained()->cascadeOnDelete();
            $table->primary(['poll_id', 'usrah_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_target_usrah_groups');
    }
};
