<?php

use App\Models\Event;
use App\Services\RegistrationDedupeService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Cleanup susulan: gabungkan semula pendaftaran pendua yang mungkin
     * tercipta daripada beberapa kali import selepas cleanup sebelum ini.
     *
     * Kehadiran, bayaran dan jawapan borang dipindahkan ke rekod yang
     * dikekalkan, jadi tiada sejarah peserta hilang.
     */
    public function up(): void
    {
        $service = app(RegistrationDedupeService::class);

        Event::query()
            ->orderBy('id')
            ->chunkById(100, function ($events) use ($service) {
                foreach ($events as $event) {
                    try {
                        $service->merge($event);
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                }
            });
    }

    public function down(): void
    {
        // Rekod pendua yang telah digabung tidak boleh dibina semula.
    }
};
