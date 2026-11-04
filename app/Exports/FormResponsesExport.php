<?php

namespace App\Exports;

use App\Models\Form;
use App\Models\FormResponse;
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

class FormResponsesExport extends StringValueBinder implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithCustomValueBinder
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
            ->with(['answers.question', 'user', 'registration'])
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
                $isIcField = (bool) preg_match('/(kad\s*pengenalan|mykad|nric|\bic\b|\bkp\b|pengenalan)/i', $label);

                // Fallback kepada maklumat responden jika medan soalan ini ialah Nama/Emel/Telefon/IC
                // tetapi jawapan dalam FormAnswer kosong (cth: pendaftaran awal / import).
                if ($val === null || $val === '') {
                    $isNotGuardian = ! preg_match('/waris|penjaga|majikan|bapa|ibu|pasangan/i', $label);

                    if ($isNotGuardian && preg_match('/\b(nama|name)\b/i', $label) && ! empty($r->respondent_name)) {
                        $val = $r->respondent_name;
                    } elseif (preg_match('/\b(emel|email)\b/i', $label) && ! empty($r->respondent_email)) {
                        $val = $r->respondent_email;
                    } elseif (preg_match('/\b(telefon|phone|tel|hp|bimbit)\b/i', $label) && ! empty($r->respondent_phone)) {
                        $val = $r->respondent_phone;
                    } elseif ($isIcField) {
                        $val = $r->registration?->ic_number ?: $r->user?->ic_number;
                    }
                }

                // Standardkan No. Kad Pengenalan kepada format XXXXXX-XX-XXXX
                // bagi mengelakkan penukaran automatik kepada format saintifik (cth: 9.70218E+11) dalam Excel/CSV.
                if ($val !== null && $val !== '' && ($isIcField || static::isLikelyIc($val))) {
                    $val = static::formatIc($val);
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

    /**
     * Standardkan nombor kad pengenalan 12-digit kepada format XXXXXX-XX-XXXX.
     */
    public static function formatIc(mixed $value): string
    {
        $str = trim((string) $value);
        if ($str === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $str);
        if (strlen($digits) === 12) {
            return substr($digits, 0, 6).'-'.substr($digits, 6, 2).'-'.substr($digits, 8, 4);
        }

        return $str;
    }

    /**
     * Semak sama ada nilai kelihatan seperti nombor MyKad 12-digit
     * (bermula dengan tarikh lahir sah YYMMDD).
     */
    public static function isLikelyIc(mixed $value): bool
    {
        $str = trim((string) $value);
        if ($str === '') {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $str);

        return (bool) preg_match('/^\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\d{6}$/', $digits);
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
