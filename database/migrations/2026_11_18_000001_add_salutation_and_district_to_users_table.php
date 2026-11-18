<?php

use App\Support\Malaysia;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('salutation', 40)->nullable()->after('name');
            $table->string('district', 100)->nullable()->after('postcode');
        });

        DB::table('users')
            ->select(['id', 'phone', 'emergency_contact_phone'])
            ->orderBy('id')
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    $phone = Malaysia::normalizePhone($user->phone);
                    $emergencyPhone = Malaysia::normalizePhone($user->emergency_contact_phone);

                    if ($phone === $user->phone && $emergencyPhone === $user->emergency_contact_phone) {
                        continue;
                    }

                    DB::table('users')->where('id', $user->id)->update([
                        'phone' => $phone,
                        'emergency_contact_phone' => $emergencyPhone,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['salutation', 'district']);
        });
    }
};
