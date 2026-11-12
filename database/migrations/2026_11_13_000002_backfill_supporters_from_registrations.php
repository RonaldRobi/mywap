<?php

use App\Models\Registration;
use App\Services\SupporterService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Isi direktori Pendukung dengan peserta bukan ahli yang sudah ada dalam
     * rekod pendaftaran program, supaya data lama tidak hilang daripada
     * senarai baharu.
     */
    public function up(): void
    {
        DB::table('supporters')
            ->select(['id', 'phone'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $normalized = Registration::normalizePhone($row->phone);

                    if ($normalized !== null) {
                        DB::table('supporters')
                            ->where('id', $row->id)
                            ->update(['phone_normalized' => $normalized]);
                    }
                }
            });

        app(SupporterService::class)->backfillFromRegistrations();
    }

    public function down(): void
    {
        // Data Pendukung yang sudah dipautkan tidak perlu dibuang semula.
    }
};
