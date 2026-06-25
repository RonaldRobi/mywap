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
        DB::table('organizations')->where('slug', 'abim')->update(['max_age' => 43]);
        DB::table('organizations')->where('slug', 'wadah')->update(['min_age' => 44]);
    }

    public function down(): void
    {
        DB::table('organizations')->where('slug', 'abim')->update(['max_age' => 29]);
        DB::table('organizations')->where('slug', 'wadah')->update(['min_age' => 30]);
    }
};
