<?php

use App\Models\Event;
use App\Services\RegistrationDedupeService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * One-off cleanup for duplicate registrations created before manual/public
     * submissions were deduplicated. Attendance, payments and form responses
     * are moved to the surviving record.
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
        // Duplicate rows cannot be reconstructed; nothing to roll back.
    }
};
