<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Token Sanctum sedia ada sebelum ini tidak pernah luput (expires_at null).
     * Beri tempoh tamat 30 hari mulai tarikh deploy supaya semua peranti terpaksa
     * berputar token baharu (yang ada abilities + expiration) selepas 30 hari.
     */
    public function up(): void
    {
        DB::table('personal_access_tokens')
            ->whereNull('expires_at')
            ->update(['expires_at' => now()->addDays(30)]);
    }

    public function down(): void
    {
        // Tidak boleh pulihkan semula tarikh asal — sengaja.
    }
};
