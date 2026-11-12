<?php

use App\Enums\EventVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('branches')
                ->nullOnDelete();

            $table->string('visibility', 20)
                ->default(EventVisibility::Open->value)
                ->after('branch_id');

            $table->index(['branch_id', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'start_time']);
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn('visibility');
        });
    }
};
