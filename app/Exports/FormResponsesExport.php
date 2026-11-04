<?php

namespace App\Exports;

use App\Models\Form;
use App\Models\FormResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FormResponsesExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    /** @var array<int, string> */
    protected array $headings = [];

    /** @var Collection<int, array<int, mixed>> */
    protected Collection $rows;

    public function __construct(
        protected Form $form,
    ) {
        $this->build();
    }

    protected function build(): void
    {
        $this->form->loadMissing('questions');

        // Nyah-pendua soalan mengikut label (case-preserved & trimmed)
        // bagi mengelakkan kolum berulang sekiranya soalan bertindih dalam database.
        $groupedQuestions = $this->form->questions
            ->filter(fn ($q) => trim((string) $q->label) !== '')
            ->groupBy(fn ($q) => trim((string) $q->label));

        $responses = $this->form->responses()
            ->with(['answers.question', 'user'])
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->get();

        // Semak sama ada soalan borang sudah merangkumi Nama, Emel, atau Telefon
        $hasNameQuestion = $groupedQuestions->keys()->contains(
            fn ($label) => (bool) preg_match('/\b(nama|name)\b/i', $label)
        );
        $hasEmailQuestion = $groupedQuestions->keys()->contains(
            fn ($label) => (bool) preg_match('/\b(emel|email)\b/i', $label)
        );
        $hasPhoneQuestion = $groupedQuestions->keys()->contains(
            fn ($label) => (bool) preg_match('/\b(telefon|phone|tel|hp|bimbit)\b/i', $label)
        );

        // Hanya sertakan kolum maklumat responden jika borang TIADA soalan khusus mengenainya
        // DAN ada sekurang-kurangnya satu respons yang mengandungi data tersebut.
        $prependColumns = [];
        if (! $hasNameQuestion && $responses->contains(fn ($r) => ! empty($r->respondent_name))) {
            $prependColumns['respondent_name'] = 'Nama';
        }
        if (! $hasEmailQuestion && $responses->contains(fn ($r) => ! empty($r->respondent_email))) {
            $prependColumns['respondent_email'] = 'Emel';
        }
        if (! $hasPhoneQuestion && $responses->contains(fn ($r) => ! empty($r->respondent_phone))) {
            $prependColumns['respondent_phone'] = 'Telefon';
        }

        // Susunan kolum seperti Google Form:
        // [Timestamp, (maklumat responden jika tiada soalan khusus), Soalan 1, Soalan 2, ...]
        $this->headings = array_merge(
            ['Timestamp'],
            array_values($prependColumns),
            $groupedQuestions->keys()->all(),
        );

        $this->rows = $responses->map(function (FormResponse $r) use ($groupedQuestions, $prependColumns) {
            $row = [
                $r->submitted_at?->format('Y-m-d H:i:s') ?? $r->created_at?->format('Y-m-d H:i:s') ?? '',
            ];

            foreach (array_keys($prependColumns) as $field) {
                $row[] = (string) ($r->{$field} ?? '');
            }

            foreach ($groupedQuestions as $label => $questionGroup) {
                $questionIds = $questionGroup->pluck('id')->all();

                // Cari jawapan responden yang sepadan dengan mana-mana ID soalan atau label soalan
                $answer = $r->answers->first(function ($a) use ($questionIds, $label) {
                    return in_array($a->form_question_id, $questionIds, true)
                        || trim((string) $a->question?->label) === $label;
                });

                $val = $answer?->value;

                // Fallback kepada maklumat responden jika medan soalan ini ialah Nama/Emel/Telefon
                // tetapi jawapan dalam FormAnswer kosong (cth: pendaftaran awal / import).
                if ($val === null || $val === '') {
                    $isNotGuardian = ! preg_match('/waris|penjaga|majikan|bapa|ibu|pasangan/i', $label);

                    if ($isNotGuardian && preg_match('/\b(nama|name)\b/i', $label) && ! empty($r->respondent_name)) {
                        $val = $r->respondent_name;
                    } elseif (preg_match('/\b(emel|email)\b/i', $label) && ! empty($r->respondent_email)) {
                        $val = $r->respondent_email;
                    } elseif (preg_match('/\b(telefon|phone|tel|hp|bimbit)\b/i', $label) && ! empty($r->respondent_phone)) {
                        $val = $r->respondent_phone;
                    }
                }

                // Jika fail muat naik, sediakan URL penuh
                if (is_string($val) && str_starts_with($val, 'form-uploads/')) {
                    $val = Storage::disk('public')->url($val);
                }

                $row[] = $val ?? '';
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

