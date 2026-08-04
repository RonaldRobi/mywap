<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->boolean('is_live')->default(false)->after('youtube_id');
        });
        Schema::table('broadcast_messages', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('target_organization_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('is_live');
        });
        Schema::table('broadcast_messages', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
