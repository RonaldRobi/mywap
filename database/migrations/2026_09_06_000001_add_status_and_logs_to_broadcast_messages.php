<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcast_messages', function (Blueprint $table) {
            $table->string('status')->default('queued')->after('email_use_template');
            $table->unsignedInteger('recipient_count')->nullable()->after('status');
            $table->unsignedInteger('success_count')->default(0)->after('recipient_count');
            $table->unsignedInteger('failed_count')->default(0)->after('success_count');
            $table->timestamp('started_at')->nullable()->after('sent_at');
            $table->timestamp('finished_at')->nullable()->after('started_at');
            $table->text('error_message')->nullable()->after('finished_at');
        });

        Schema::table('broadcast_messages', function (Blueprint $table) {
            $table->index(['status', 'sent_at']);
        });

        Schema::create('broadcast_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_message_id')->constrained('broadcast_messages')->cascadeOnDelete();
            $table->string('event');
            $table->string('channel')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['broadcast_message_id', 'created_at']);
        });

        // Rekod lama yang sudah dihantar ditandakan 'completed' (status baru
        // tidak wujud sebelum ini). Yang masih menunggu kekal 'queued'.
        DB::table('broadcast_messages')
            ->whereNotNull('sent_at')
            ->update(['status' => 'completed']);
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_logs');

        Schema::table('broadcast_messages', function (Blueprint $table) {
            $table->dropIndex(['status', 'sent_at']);
        });

        Schema::table('broadcast_messages', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'recipient_count',
                'success_count',
                'failed_count',
                'started_at',
                'finished_at',
                'error_message',
            ]);
        });
    }
};
