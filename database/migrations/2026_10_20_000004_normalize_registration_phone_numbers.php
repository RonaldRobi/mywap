<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('registrations')
            ->select(['id', 'phone', 'phone_normalized'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $digits = preg_replace('/\D+/', '', (string) $row->phone);

                    if ($digits === '') {
                        $normalized = null;
                    } elseif (str_starts_with($digits, '60')) {
                        $digits = substr($digits, 2);
                        $normalized = $digits === ''
                            ? null
                            : (str_starts_with($digits, '0') ? $digits : '0'.$digits);
                    } else {
                        $normalized = str_starts_with($digits, '0') ? $digits : '0'.$digits;
                    }

                    if ($normalized !== $row->phone_normalized) {
                        DB::table('registrations')
                            ->where('id', $row->id)
                            ->update(['phone_normalized' => $normalized]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Normalisation is idempotent; the previous truncated form cannot be
        // restored reliably, so there is nothing to roll back.
    }
};
