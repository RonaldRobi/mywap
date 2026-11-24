<?php

namespace App\Exports;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EventRegistrationsExport extends StringValueBinder implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithCustomValueBinder
{
    /** @var array<int, string> */
    private array $headings;

    /** @var Collection<int, array<int, mixed>> */
    private Collection $rows;

    /**
     * @param  Collection<int, Registration>  $registrations
     * @param  Collection<int, mixed>  $questions
     */
    public function __construct(Collection $registrations, Collection $questions, Event $event)
    {
        $this->build($registrations, $questions, $event);
    }

    /**
     * @param  Collection<int, Registration>  $registrations
     * @param  Collection<int, mixed>  $questions
     */
    private function build(Collection $registrations, Collection $questions, Event $event): void
    {
        $questions = $questions
            ->merge($registrations->flatMap(fn (Registration $registration) => $registration->form?->questions ?? collect()))
            ->unique('id')
            ->sortBy(fn ($question) => [$question->form_id, $question->sort_order, $question->id])
            ->values();

        $questionColumns = [];
        $usedLabels = [];

        foreach ($questions as $question) {
            $label = trim((string) $question->label) ?: 'Soalan '.$question->id;
            $base = $label;
            $suffix = 2;

            while (in_array($label, $usedLabels, true)) {
                $label = $base.' ('.$suffix.')';
                $suffix++;
            }

            $usedLabels[] = $label;
            $questionColumns[$question->id] = $label;
        }

        $hasPayment = $registrations->contains(
            fn (Registration $r) => (float) $r->totalAmount() > 0 || ($r->form?->payment_required ?? false)
        );
        $hasTicketTypes = $registrations->contains(fn (Registration $r) => filled($r->ticket_type));
        $hasItems = $registrations->contains(fn (Registration $r) => $r->items->isNotEmpty());
        $hasAttendance = ($event->start_time?->isPast() ?? false)
            || $registrations->contains(fn (Registration $r) => $r->attendance !== null);

        $columns = [
            ['key' => 'registration_no', 'label' => 'No. Daftar'],
            ['key' => 'created_at', 'label' => 'Masa Daftar'],
        ];

        foreach ($questionColumns as $id => $label) {
            $columns[] = ['key' => 'question:'.$id, 'label' => $label];
        }

        $columns[] = ['key' => 'status', 'label' => 'Status'];

        if ($hasTicketTypes) {
            $columns[] = ['key' => 'ticket_type', 'label' => 'Kategori'];
        }

        if ($hasItems) {
            $columns[] = ['key' => 'items', 'label' => 'Item'];
        }

        if ($hasPayment) {
            $columns[] = ['key' => 'total', 'label' => 'Jumlah (RM)'];
            $columns[] = ['key' => 'payment', 'label' => 'Bayaran'];
        }

        if ($hasAttendance) {
            $columns[] = ['key' => 'attendance', 'label' => 'Kehadiran'];
            $columns[] = ['key' => 'attended_at', 'label' => 'Masa Hadir'];
        }

        $this->headings = array_column($columns, 'label');

        $this->rows = $registrations->map(function (Registration $registration) use ($columns, $event) {
            $answers = $registration->formResponse?->answers->keyBy('form_question_id') ?? collect();

            return collect($columns)
                ->map(fn (array $column) => $this->valueFor($column['key'], $registration, $answers, $event))
                ->values()
                ->all();
        });
    }

    /**
     * @param  Collection<int, mixed>  $answers
     */
    private function valueFor(string $key, Registration $registration, Collection $answers, Event $event): mixed
    {
        if (str_starts_with($key, 'question:')) {
            $value = $answers->get((int) substr($key, 9))?->value;

            if (is_string($value) && str_starts_with($value, 'form-uploads/')) {
                $value = Storage::disk('public')->url($value);
            }

            return $value ?? '';
        }

        return match ($key) {
            'registration_no' => $registration->registration_no,
            'created_at' => $registration->created_at?->toDateTimeString(),
            'status' => $registration->status->label(),
            'ticket_type' => $registration->ticket_type,
            'items' => $registration->items
                ->map(fn ($item) => trim($item->name
                    .($item->variant_name ? ' ('.$item->variant_name.')' : '')
                    .' x'.$item->quantity))
                ->implode('; ') ?: null,
            'total' => number_format($registration->totalAmount(), 2, '.', ''),
            'payment' => $registration->isFreeRegistration()
                ? 'Percuma'
                : ($registration->latestPayment?->status === 'successful'
                    ? 'Dibayar'.($registration->latestPayment?->uploaded_by !== null ? ' (manual)' : '')
                    : 'Menunggu Bayaran'),
            'attendance' => $registration->attendance !== null
                ? 'Hadir'
                : ($event->start_time?->isPast() ? 'Tidak Hadir' : ''),
            'attended_at' => $registration->attendance?->attended_at?->toDateTimeString(),
            default => null,
        };
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => '111827']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F3F4F6'],
                ],
            ],
        ];
    }
}
