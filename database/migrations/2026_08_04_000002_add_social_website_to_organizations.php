<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('website_url', 500)->nullable()->after('bayarcash_environment');
            $table->string('facebook_url', 500)->nullable()->after('website_url');
            $table->string('instagram_url', 500)->nullable()->after('facebook_url');
            $table->string('twitter_url', 500)->nullable()->after('instagram_url');
            $table->string('youtube_url', 500)->nullable()->after('twitter_url');
            $table->string('tiktok_url', 500)->nullable()->after('youtube_url');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['website_url', 'facebook_url', 'instagram_url', 'twitter_url', 'youtube_url', 'tiktok_url']);
        });
    }
};
