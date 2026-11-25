<?php

namespace App\Console\Commands;

use App\Models\Registration;
use App\Models\Scopes\OrganizationScope;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class LinkRegistrationsToMembers extends Command
{
    protected $signature = 'registrations:link-members
        {--event= : Hadkan kepada event ID (kosong = semua event)}
        {--commit : Tulis perubahan ke database (default dry-run)}';

    protected $description = 'Pautkan pendaftaran peserta awam kepada akaun ahli melalui padanan No. IC';

    private const PREVIEW_LIMIT = 60;

    public function handle(): int
    {
        $eventId = $this->option('event') !== null ? (int) $this->option('event') : null;
        $commit = (bool) $this->option('commit');

        $query = Registration::query()
            ->with('event.organizations')
            ->whereNull('user_id')
            ->whereNotNull('ic_number')
            ->when($eventId !== null, fn ($q) => $q->where('event_id', $eventId));

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Tiada pendaftaran peserta awam dengan No. IC untuk diproses.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s: menyemak %d rekod peserta awam yang mempunyai No. IC.',
            $commit ? 'Mod COMMIT' : 'Mod DRY-RUN',
            $total,
        ));

        $linked = 0;
        $noMatch = 0;
        $ambiguous = 0;
        $preview = collect();

        $query->chunkById(200, function (Collection $registrations) use (
            &$linked, &$noMatch, &$ambiguous, &$preview, $commit
        ) {
            foreach ($registrations as $registration) {
                $digits = Registration::normalizeIc($registration->ic_number);

                if ($digits === null) {
                    $noMatch++;

                    continue;
                }

                $matches = User::withoutGlobalScope(OrganizationScope::class)
                    ->with('organization:id,name')
                    ->whereNotNull('ic_number')
                    ->whereRaw("REPLACE(REPLACE(REPLACE(ic_number, '-', ''), ' ', ''), '.', '') = ?", [$digits])
                    ->get(['id', 'name', 'member_no', 'current_organization_id']);

                if ($matches->isEmpty()) {
                    $noMatch++;

                    continue;
                }

                $match = $this->resolveMatch($matches, $registration);

                if ($match === null) {
                    $ambiguous++;

                    continue;
                }

                if ($commit) {
                    $registration->forceFill([
                        'user_id' => $match->id,
                        'member_no' => $match->member_no ?: $registration->member_no,
                        'organization_id' => $match->current_organization_id,
                    ])->saveQuietly();
                }

                $linked++;

                if ($preview->count() < self::PREVIEW_LIMIT) {
                    $preview->push([
                        $registration->registration_no,
                        $registration->name,
                        $registration->ic_number,
                        $match->name,
                        $match->member_no ?: '-',
                        $match->organization?->name ?? '-',
                    ]);
                }
            }
        });

        if ($preview->isNotEmpty()) {
            $this->newLine();
            $this->table(
                ['No. Daftar', 'Peserta Awam', 'No. IC', 'Akaun Ahli', 'No. Ahli', 'Organisasi'],
                $preview->all(),
            );

            if ($linked > $preview->count()) {
                $this->line(sprintf('... dan %d rekod lagi tidak dipaparkan.', $linked - $preview->count()));
            }
        }

        $this->newLine();
        $this->table(['Status', 'Jumlah'], [
            [$commit ? 'Dipautkan' : 'Akan dipautkan', $linked],
            ['Tiada padanan / IC tidak lengkap', $noMatch],
            ['IC berulang (ambiguous, dilangkau)', $ambiguous],
        ]);

        if (! $commit) {
            $this->newLine();
            $this->warn('DRY-RUN: tiada perubahan ditulis. Jalankan semula dengan --commit untuk apply.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, User>  $matches
     */
    private function resolveMatch(Collection $matches, Registration $registration): ?User
    {
        if ($matches->count() === 1) {
            return $matches->first();
        }

        $eventOrgIds = collect([$registration->event?->organization_id])
            ->merge($registration->event?->organizations?->pluck('id') ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($eventOrgIds !== []) {
            $preferred = $matches->first(
                fn (User $user) => in_array((int) $user->current_organization_id, $eventOrgIds, true)
            );

            if ($preferred !== null) {
                return $preferred;
            }
        }

        return null;
    }
}
