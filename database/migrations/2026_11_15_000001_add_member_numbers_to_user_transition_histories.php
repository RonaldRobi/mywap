<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_transition_histories', function (Blueprint $table) {
            $table->string('from_member_no', 20)->nullable()->after('from_organization_id');
            $table->string('to_member_no', 20)->nullable()->after('to_organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_transition_histories', function (Blueprint $table) {
            $table->dropColumn(['from_member_no', 'to_member_no']);
        });
    }
};
