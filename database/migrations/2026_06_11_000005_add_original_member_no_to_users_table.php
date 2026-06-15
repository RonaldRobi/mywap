<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('original_member_no', 20)->nullable()->after('member_no');
        });

        // Backfill: original_member_no = member_no for all existing users
        \Illuminate\Support\Facades\DB::table('users')
            ->whereNotNull('member_no')
            ->whereNull('original_member_no')
            ->update(['original_member_no' => \Illuminate\Support\Facades\DB::raw('member_no')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('original_member_no');
        });
    }
};
