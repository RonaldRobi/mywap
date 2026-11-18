<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_statements', function (Blueprint $table) {
            $table->string('content_type')->default('text')->after('title');
        });

        DB::table('media_statements')
            ->whereNotNull('pdf_path')
            ->update(['content_type' => 'pdf']);

        DB::table('media_statements')
            ->whereNotNull('pdf_path')
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->update(['content_type' => 'both']);

        Schema::table('media_statements', function (Blueprint $table) {
            $table->longText('content')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('media_statements')->whereNull('content')->update(['content' => '']);

        Schema::table('media_statements', function (Blueprint $table) {
            $table->longText('content')->nullable(false)->change();
            $table->dropColumn('content_type');
        });
    }
};
