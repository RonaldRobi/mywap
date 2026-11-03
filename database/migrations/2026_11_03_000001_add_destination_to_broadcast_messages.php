<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Destinasi notifikasi untuk siaran.
 *
 * Admin memilih program atau produk melalui pencarian di UI, bukan menampal
 * URL mentah. `action_type` + `action_id` membolehkan app mobile membina
 * laluan dalaman sendiri, manakala `action_url` menyimpan pautan web yang
 * sudah diselesaikan semasa siaran dicipta (untuk web dan emel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcast_messages', function (Blueprint $table) {
            $table->string('action_type')->nullable()->after('content');
            $table->unsignedBigInteger('action_id')->nullable()->after('action_type');
            $table->string('action_label', 60)->nullable()->after('action_id');
            $table->string('action_url', 2048)->nullable()->after('action_label');

            $table->index(['action_type', 'action_id']);
        });
    }

    public function down(): void
    {
        Schema::table('broadcast_messages', function (Blueprint $table) {
            $table->dropIndex(['action_type', 'action_id']);
            $table->dropColumn(['action_type', 'action_id', 'action_label', 'action_url']);
        });
    }
};
