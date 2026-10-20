<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\RegistrationDedupeService;
use Illuminate\Console\Command;

class DedupeRegistrations extends Command
{
    protected $signature = 'registrations:dedupe {--event= : Event ID} {--force : Jalankan tanpa pengesahan}';

    protected $description = 'Gabungkan rekod pendaftaran pendua (kehadiran & bayaran dikekalkan)';

    public function handle(RegistrationDedupeService $service): int
    {
        $events = Event::query()
            ->when($this->option('event'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get();

        if ($events->isEmpty()) {
            $this->warn('Tiada event dijumpai.');

            return self::SUCCESS;
        }

        $totalDeleted = 0;

        foreach ($events as $event) {
            $summary = $service->summary($event);

            if ($summary['duplicate_rows'] === 0) {
                continue;
            }

            $this->line(sprintf(
                '%s: %d rekod, %d unik, %d pendua',
                $event->title,
                $summary['total_rows'],
                $summary['unique_identities'],
                $summary['duplicate_rows'],
            ));

            if (! $this->option('force') && ! $this->confirm("Gabungkan {$summary['duplicate_rows']} rekod pendua untuk event ini?")) {
                continue;
            }

            $result = $service->merge($event);
            $totalDeleted += $result['deleted'];
            $this->info("  → {$result['deleted']} digabung, {$result['remaining']} peserta tinggal.");
        }

        $this->info("Selesai. Jumlah rekod digabung: {$totalDeleted}");

        return self::SUCCESS;
    }
}
