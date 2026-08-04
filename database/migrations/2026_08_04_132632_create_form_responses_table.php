<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('respondent_name')->nullable();
            $table->string('respondent_email')->nullable();
            $table->string('respondent_phone')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_responses');
    }
};
