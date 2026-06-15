<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // users
        Schema::table('users', function (Blueprint $table) {
            $table->index('member_no');
            $table->index('email');
            $table->index('phone');
            $table->index('is_public_in_directory');
            $table->index('postcode');
            $table->index('state');
        });

        // branches — compound index for org-scoped active listing
        Schema::table('branches', function (Blueprint $table) {
            $table->index(['organization_id', 'is_active']);
        });

        // orders — compound index for user-scoped status filtering
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
        });

        // products — compound indexes for listing
        Schema::table('products', function (Blueprint $table) {
            $table->index(['category_id', 'status']);
            $table->index(['organisasi_id', 'status']);
        });

        // infaq — org_id index
        Schema::table('infaq', function (Blueprint $table) {
            $table->index('organization_id');
        });

        // membership_fees — compound index for org/year reporting
        Schema::table('membership_fees', function (Blueprint $table) {
            $table->index(['organization_id', 'year', 'status']);
        });

        // article_comments — compound index (parity with news_post_comments)
        Schema::table('article_comments', function (Blueprint $table) {
            $table->index(['article_id', 'created_at']);
        });

        // postcodes — state index for lookups
        Schema::table('postcodes', function (Blueprint $table) {
            $table->index('state');
        });

        // activity_logs — compound index for org-scoped log viewing
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['organization_id', 'created_at']);
        });

        // payments — reference index for lookups
        Schema::table('payments', function (Blueprint $table) {
            $table->index('reference');
        });

        // fee_imports — compound index for import history
        Schema::table('fee_imports', function (Blueprint $table) {
            $table->index(['user_id', 'year']);
        });

        // organizations — sort_order index
        Schema::table('organizations', function (Blueprint $table) {
            $table->index('sort_order');
        });

        // categories — name index
        Schema::table('categories', function (Blueprint $table) {
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['member_no']);
            $table->dropIndex(['email']);
            $table->dropIndex(['phone']);
            $table->dropIndex(['is_public_in_directory']);
            $table->dropIndex(['postcode']);
            $table->dropIndex(['state']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'is_active']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'status']);
            $table->dropIndex(['organisasi_id', 'status']);
        });

        Schema::table('infaq', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
        });

        Schema::table('membership_fees', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'year', 'status']);
        });

        Schema::table('article_comments', function (Blueprint $table) {
            $table->dropIndex(['article_id', 'created_at']);
        });

        Schema::table('postcodes', function (Blueprint $table) {
            $table->dropIndex(['state']);
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'created_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['reference']);
        });

        Schema::table('fee_imports', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'year']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });
    }
};
