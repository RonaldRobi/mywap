<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supporters', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('ic_number')->nullable()->after('phone');
            $table->string('ic_normalized', 32)->nullable()->after('ic_number');
            $table->string('phone_normalized', 40)->nullable()->after('phone');
            $table->foreignId('converted_user_id')->nullable()->after('is_active')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('converted_at')->nullable()->after('converted_user_id');
            $table->timestamp('last_invited_at')->nullable()->after('converted_at');

            $table->index('ic_normalized');
            $table->index('phone_normalized');
            $table->index('converted_at');
        });

        Schema::create('event_supporter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('supporter_id')->constrained('supporters')->cascadeOnDelete();
            $table->string('source', 60)->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'supporter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_supporter');

        Schema::table('supporters', function (Blueprint $table) {
            $table->dropForeign(['converted_user_id']);
            $table->dropIndex(['ic_normalized']);
            $table->dropIndex(['phone_normalized']);
            $table->dropIndex(['converted_at']);
            $table->dropColumn([
                'ic_number',
                'ic_normalized',
                'phone_normalized',
                'converted_user_id',
                'converted_at',
                'last_invited_at',
            ]);
        });
    }
};
