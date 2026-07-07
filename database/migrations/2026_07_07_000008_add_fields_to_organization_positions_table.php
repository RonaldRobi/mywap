<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_positions', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('category')->nullable()->after('description');
            $table->foreignId('parent_id')->nullable()->after('category')
                ->constrained('organization_positions')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('parent_id');
            $table->string('color', 7)->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('organization_positions', function (Blueprint $table) {
            $table->dropColumn(['description', 'category', 'parent_id', 'is_active', 'color']);
        });
    }
};
