<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcast_messages', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('email_use_template');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('broadcast_messages', function (Blueprint $table) {
            $table->dropIndex(['scheduled_at']);
            $table->dropColumn('scheduled_at');
        });
    }
};
