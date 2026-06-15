<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postcodes', function (Blueprint $table) {
            $table->string('postcode', 5)->primary();
            $table->string('city')->nullable();
            $table->string('state');
            $table->string('country')->default('Malaysia');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postcodes');
    }
};
