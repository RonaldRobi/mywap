<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usrah_group_user', function (Blueprint $table) {
            $table->string('role', 20)->nullable()->after('is_naqib');
            $table->index('role');
        });

        DB::table('usrah_group_user')->where('is_naqib', true)->update(['role' => 'leader']);
        DB::table('usrah_group_user')->where('is_naqib', false)->update(['role' => 'member']);
    }

    public function down(): void
    {
        Schema::table('usrah_group_user', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
