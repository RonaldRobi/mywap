<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infaq', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('title');
        });

        $infaqs = DB::table('infaq')->get();
        foreach ($infaqs as $infaq) {
            DB::table('infaq')->where('id', $infaq->id)->update([
                'slug' => Str::slug($infaq->title) . '-' . $infaq->id
            ]);
        }

        Schema::table('infaq', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('infaq', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
