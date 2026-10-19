<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('ic_normalized', 32)->nullable()->after('ic_number')->index();
            $table->string('phone_normalized', 32)->nullable()->after('phone')->index();
        });

        DB::table('registrations')
            ->select(['id', 'ic_number', 'phone'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $ic = preg_replace('/\D+/', '', (string) $row->ic_number);
                    $phone = preg_replace('/\D+/', '', (string) $row->phone);

                    if ($phone !== '' && str_starts_with($phone, '60') && strlen($phone) > 10) {
                        $phone = '0'.substr($phone, 2);
                    }

                    DB::table('registrations')->where('id', $row->id)->update([
                        'ic_normalized' => $ic !== '' ? $ic : null,
                        'phone_normalized' => $phone !== '' ? $phone : null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex(['ic_normalized']);
            $table->dropIndex(['phone_normalized']);
            $table->dropColumn(['ic_normalized', 'phone_normalized']);
        });
    }
};
