<?php

namespace App\Exports;

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
    public function __construct(Collection $registrations, Collection $questions)
    {
        $this->build($registrations, $questions);
    }

    /**
     * @param  Collection<int, Registration>  $registrations
     * @param  Collection<int, mixed>  $questions
     */
    private function build(Collection $registrations, Collection $questions): void
    {
        $questions = $questions
            ->merge($registrations->flatMap(fn (Registration $registration) => $registration->form?->questions ?? collect()))
            ->unique('id')
            ->sortBy(fn ($question) => [$question->form_id, $question->sort_order, $question->id])
            ->values();

        $labels = [];
        $questionColumns = $questions->mapWithKeys(function ($question) use (&$labels) {
            $label = trim((string) $question->label) ?: 'Soalan '.$question->id;
            $base = $label;
            $suffix = 2;

            while (in_array($label, $labels, true)) {
                $label = $base.' ('.$suffix.')';
                $suffix++;
            }

            $labels[] = $label;

            return [$question->id => $label];
        });

        $this->headings = array_merge([
            'No. Daftar', 'Nama', 'No. IC', 'No. Ahli', 'Telefon', 'Emel',
            'Organisasi akaun', 'Borang', 'Kategori', 'Item', 'Jumlah (RM)',
            'Bayaran', 'Status', 'Kehadiran', 'Masa Hadir', 'Masa Daftar',
        ], $questionColumns->values()->all());

        $this->rows = $registrations->map(function (Registration $registration) use ($questionColumns) {
            $answers = $registration->formResponse?->answers
                ->keyBy('form_question_id') ?? collect();

            $items = $registration->items
                ->map(fn ($item) => trim($item->name
                    .($item->variant_name ? ' ('.$item->variant_name.')' : '')
                    .' x'.$item->quantity))
                ->implode('; ');

            $payment = $registration->isFreeRegistration()
                ? 'Percuma'
                : ($registration->latestPayment?->status === 'successful'
                    ? 'Dibayar'.($registration->latestPayment?->uploaded_by !== null ? ' (manual)' : '')
                    : 'Menunggu Bayaran');

            $row = [
                $registration->registration_no,
                $registration->name,
                $registration->ic_number,
                $registration->member_no,
                $registration->phone,
                $registration->email,
                $registration->isPublicParticipant() ? 'Orang awam' : $registration->organization?->name,
                $registration->form?->title,
                $registration->ticket_type,
                $items !== '' ? $items : null,
                number_format($registration->totalAmount(), 2, '.', ''),
                $payment,
                $registration->status->label(),
                $registration->attendance ? 'Hadir' : 'Tidak Hadir',
                $registration->attendance?->attended_at?->toDateTimeString(),
                $registration->created_at?->toDateTimeString(),
            ];

            foreach ($questionColumns as $questionId => $label) {
                $value = $answers->get($questionId)?->value;

                if (is_string($value) && str_starts_with($value, 'form-uploads/')) {
                    $value = Storage::disk('public')->url($value);
                }

                $row[] = $value ?? '';
            }

            return $row;
        });
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
