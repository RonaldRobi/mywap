<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        Schema::create('polls_temp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 20)->default('poll');
            $table->string('target_type', 20)->default('all');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('show_results')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $columns = Schema::getColumnListing('polls');
        $cols = implode(',', array_intersect($columns, Schema::getColumnListing('polls_temp')));

        DB::statement("INSERT INTO polls_temp ($cols) SELECT $cols FROM polls");
        Schema::drop('polls');
        Schema::rename('polls_temp', 'polls');

        Schema::table('polls', function (Blueprint $table) {
            $table->index(['organization_id', 'is_active', 'ends_at']);
        });

        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function down(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        Schema::create('polls_temp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 20)->default('poll');
            $table->string('target_type', 20)->default('all');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('show_results')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $columns = Schema::getColumnListing('polls');
        $cols = implode(',', array_intersect($columns, Schema::getColumnListing('polls_temp')));

        DB::statement("INSERT INTO polls_temp ($cols) SELECT $cols FROM polls");
        Schema::drop('polls');
        Schema::rename('polls_temp', 'polls');

        Schema::table('polls', function (Blueprint $table) {
            $table->index(['organization_id', 'is_active', 'ends_at']);
        });

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
