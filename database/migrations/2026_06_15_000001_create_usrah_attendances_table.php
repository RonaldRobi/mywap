<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usrah_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usrah_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('session_date');
            $table->string('status'); // hadir, tidak_hadir, uzur
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['usrah_group_id', 'user_id', 'session_date']);
            $table->index(['usrah_group_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usrah_attendances');
    }
};
