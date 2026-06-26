<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboard_banners', function (Blueprint $table) {
            $table->string('link_url')->nullable()->after('image_path');
            $table->string('link_target', 10)->nullable()->default('_blank')->after('link_url');
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_banners', function (Blueprint $table) {
            $table->dropColumn(['link_url', 'link_target']);
        });
    }
};
