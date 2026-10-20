<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Imports\RawSpreadsheet;
use App\Models\Event;
use App\Models\Form;
use App\Models\Registration;
use App\Models\RegistrationImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Import Google Form responses (CSV/XLSX) into event registrations.
 *
 * Repeated uploads are safe: a row is matched within the event by IC, phone
 * or email and updated in place. Rows that disappear from a later export are
 * never deleted — admins often upload filtered/partial sheets while the form
 * is still accepting responses.
 */
class RegistrationImportService
{
    public const MAX_ROWS = 10000;

    private const MAX_ERRORS = 100;

    /**
     * Column aliases used for auto-detection. Headers are compared after
     * lowercasing and reducing punctuation to spaces.
     *
     * @var array<string, array<int, string>>
     */
    private const ALIASES = [
        'name' => ['nama', 'name', 'nama penuh', 'nama peserta', 'full name', 'fullname'],
        'ic_number' => [
            'no ic', 'no ic peserta', 'ic', 'ic number', 'nombor ic', 'no kad pengenalan',
            'kad pengenalan', 'nric', 'no kp', 'no kp peserta',
        ],
        'phone' => [
            'no telefon', 'no telefon bimbit', 'telefon', 'phone', 'phone number',
            'no hp', 'no handphone', 'no whatsapp', 'whatsapp', 'mobile', 'nombor telefon',
        ],
        'email' => ['emel', 'email', 'e mel', 'alamat emel', 'email address'],
        'member_no' => ['no ahli', 'no keahlian', 'member no', 'member number', 'no ahli lama'],
    ];

    /**
     * Read the first sheet and return headers, sample rows and a best-effort
     * column mapping for the admin to confirm before committing.
     *
     * @return array{headers: array<int, string>, mapping: array<string, int|null>, sample: array<int, array<int, mixed>>, total_rows: int}
     */
    public function preview(UploadedFile $file): array
    {
        $rows = $this->readRows($file);

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'Fail kosong atau tiada baris data.']);
        }

        $headers = array_map(fn ($value) => $this->cleanHeader($value), array_values($rows[0]));
        $dataRows = array_slice($rows, 1);

        if (count($dataRows) > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'file' => 'Fail mengandungi lebih '.self::MAX_ROWS.' baris. Sila pecahkan kepada beberapa fail.',
            ]);
        }

        return [
            'headers' => $headers,
            'mapping' => $this->detectMapping($headers),
            'sample' => array_slice($dataRows, 0, 5),
            'total_rows' => count($dataRows),
        ];
    }

    /**
     * Upsert every data row into the event's registrations.
     *
     * @param  array<string, int|null>  $mapping
     * @return array{total_rows: int, created: int, updated: int, skipped: int, errors: array<int, string>, import_id: int}
     */
    public function import(Event $event, UploadedFile $file, array $mapping, ?User $user = null): array
    {
        $rows = $this->readRows($file);

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'Fail kosong atau tiada baris data.']);
        }

        $headers = array_map(fn ($value) => $this->cleanHeader($value), array_values($rows[0]));
        $headerKeys = $this->headerKeys($headers);
        $dataRows = array_slice($rows, 1);

        if (count($dataRows) > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'file' => 'Fail mengandungi lebih '.self::MAX_ROWS.' baris. Sila pecahkan kepada beberapa fail.',
            ]);
        }

        $mapping = $this->validatedMapping($mapping, count($headers));

        $result = DB::transaction(function () use ($event, $dataRows, $headerKeys, $mapping) {
            $created = 0;
            $updated = 0;
            $skipped = 0;
            $total = 0;
            $errors = [];
            $seen = [];

            foreach ($dataRows as $offset => $row) {
                $rowNumber = $offset + 2;

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $total++;

                $name = $this->cell($row, $mapping['name'] ?? null);
                $ic = $this->cell($row, $mapping['ic_number'] ?? null);
                $phone = $this->cell($row, $mapping['phone'] ?? null);
                $email = $this->cell($row, $mapping['email'] ?? null);
                $memberNo = $this->cell($row, $mapping['member_no'] ?? null);

                $rawData = [];
                foreach ($headerKeys as $index => $header) {
                    $value = $this->rawCell($row[$index] ?? null);
                    if ($value !== null) {
                        $rawData[$header] = $value;
                    }
                }

                if ($name === null) {
                    $skipped++;
                    $this->pushError($errors, "Baris {$rowNumber}: nama kosong — dilewati.");

                    continue;
                }

                $ic = $this->cleanIc($ic);
                $phone = $this->cleanPhone($phone);
                $email = $this->cleanEmail($email);
                $memberNo = $memberNo !== null ? trim($memberNo) : null;

                $icNormalized = Registration::normalizeIc($ic);
                $phoneNormalized = Registration::normalizePhone($phone);
                $emailNormalized = $email !== null ? mb_strtolower($email) : null;

                if ($icNormalized === null && $phoneNormalized === null && $emailNormalized === null) {
                    $skipped++;
                    $this->pushError($errors, "Baris {$rowNumber}: tiada No IC / telefon / emel — tidak boleh dipadankan, dilewati.");

                    continue;
                }

                $key = $icNormalized !== null
                    ? 'ic:'.$icNormalized
                    : ($phoneNormalized !== null ? 'phone:'.$phoneNormalized : 'email:'.$emailNormalized);

                if (isset($seen[$key])) {
                    $skipped++;
                    $this->pushError($errors, "Baris {$rowNumber}: pendua dalam fail yang sama (baris {$seen[$key]}), dilewati.");

                    continue;
                }
                $seen[$key] = $rowNumber;

                $registration = $this->findMatching($event->id, $icNormalized, $phoneNormalized, $emailNormalized);

                $attributes = ['name' => mb_substr($name, 0, 255)];
                if ($ic !== null) {
                    $attributes['ic_number'] = mb_substr($ic, 0, 255);
                }
                if ($phone !== null) {
                    $attributes['phone'] = mb_substr($phone, 0, 30);
                }
                if ($email !== null) {
                    $attributes['email'] = mb_substr($email, 0, 255);
                }
                if ($memberNo !== null && $memberNo !== '') {
                    $attributes['member_no'] = mb_substr($memberNo, 0, 255);
                }
                if ($rawData !== []) {
                    $attributes['raw_data'] = $rawData;
                }

                if ($registration) {
                    // Re-imports are often filtered/partial exports. Merge the
                    // incoming columns so columns saved by an earlier upload are
                    // not lost when the newer file contains fewer columns.
                    if ($rawData !== []) {
                        $attributes['raw_data'] = array_merge(
                            $registration->raw_data ?? [],
                            $rawData,
                        );
                    }

                    $registration->fill($attributes)->save();
                    $updated++;

                    continue;
                }

                Registration::create(array_merge([
                    'event_id' => $event->id,
                    'organization_id' => $event->organization_id,
                    'status' => RegistrationStatus::Confirmed,
                ], $attributes));

                $created++;
            }

            return compact('total', 'created', 'updated', 'skipped', 'errors');
        });

        $filePath = null;

        try {
            $filePath = $file->store('registration-imports', 'local');
        } catch (\Throwable) {
            // Storing the source file is best-effort; the imported rows are
            // already committed and remain authoritative.
        }

        $import = RegistrationImport::create([
            'event_id' => $event->id,
            'user_id' => $user?->id,
            'filename' => $file->getClientOriginalName(),
            'file_path' => $filePath,
            'headers' => array_values($headerKeys),
            'total_rows' => $result['total'],
            'created_count' => $result['created'],
            'updated_count' => $result['updated'],
            'skipped_count' => $result['skipped'],
            'errors' => $result['errors'] !== [] ? $result['errors'] : null,
        ]);

        return [
            'total_rows' => $result['total'],
            'created' => $result['created'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
            'errors' => $result['errors'],
            'import_id' => $import->id,
        ];
    }

    /**
     * Every original Google Form / spreadsheet column known for an event, in
     * first-seen order. Columns live in raw_data, so no schema change is
     * needed when the form gains new questions.
     *
     * @return array<int, string>
     */
    public function rawColumns(Event $event): array
    {
        $rawHeaders = [];

        $importHeaders = RegistrationImport::query()
            ->where('event_id', $event->id)
            ->whereNotNull('headers')
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('headers')
            ->flatten()
            ->filter(fn ($header) => is_string($header) && $header !== '')
            ->values();

        foreach ($importHeaders as $header) {
            if (! in_array($header, $rawHeaders, true)) {
                $rawHeaders[] = $header;
            }
        }

        // Registrations imported before the headers snapshot existed still
        // carry their columns inside raw_data.
        Registration::query()
            ->where('event_id', $event->id)
            ->whereNotNull('raw_data')
            ->select(['id', 'raw_data'])
            ->chunkById(500, function ($rows) use (&$rawHeaders) {
                foreach ($rows as $row) {
                    foreach (array_keys($row->raw_data ?? []) as $header) {
                        if (! in_array($header, $rawHeaders, true)) {
                            $rawHeaders[] = $header;
                        }
                    }
                }
            });

        return $rawHeaders;
    }

    /**
     * Build the export column definition for an event.
     *
     * CSV/XLSX import headers come first, followed by questions answered in
     * the myWAP registration form, then system columns. Form questions that
     * map to a core participant field already present in the import reuse that
     * column, so a name/IC/phone is never exported twice under a different
     * label just because the CSV and the form named it differently.
     *
     * @return array{headers: array<int, string>, raw: array<int, string>, raw_questions: array<string, int>, form: array<int, array{id: int, label: string, field: string|null}>, overrides: array<string, string>, system: array<string, string>}
     */
    public function exportDefinition(Event $event): array
    {
        $rawHeaders = $this->rawColumns($event);
        $normalizedRaw = array_map(fn (string $header) => $this->normalizeHeader($header), $rawHeaders);
        $seen = $normalizedRaw;

        $overrides = [];
        foreach ($this->detectMapping($rawHeaders) as $field => $index) {
            if ($index !== null && isset($rawHeaders[$index])) {
                $overrides[$rawHeaders[$index]] = $field;
            }
        }

        $coveredFields = array_values(array_unique($overrides));
        $rawQuestions = [];
        $formColumns = [];

        foreach ($this->eventFormQuestions($event) as $question) {
            $field = $this->detectField($question['label']);
            $rawIndex = array_search($this->normalizeHeader($question['label']), $normalizedRaw, true);

            if ($rawIndex !== false) {
                if (! isset($rawQuestions[$rawHeaders[$rawIndex]])) {
                    $rawQuestions[$rawHeaders[$rawIndex]] = $question['id'];
                }

                continue;
            }

            // The import already carries this core field (e.g. "Nama Penuh"
            // vs a form question called "Nama"); reuse the existing column.
            if ($field !== null && in_array($field, $coveredFields, true)) {
                continue;
            }

            $label = $question['label'];
            $suffix = 2;

            while (in_array($this->normalizeHeader($label), $seen, true)) {
                $label = $question['label'].' ('.$suffix.')';
                $suffix++;
            }

            $seen[] = $this->normalizeHeader($label);
            $formColumns[] = ['id' => $question['id'], 'label' => $label, 'field' => $field];

            if ($field !== null) {
                $coveredFields[] = $field;
            }
        }

        $system = [];

        foreach ($this->systemColumns() as $key => $label) {
            if (! in_array($this->normalizeHeader($label), $seen, true)) {
                $system[$key] = $label;
            }
        }

        return [
            'headers' => array_merge($rawHeaders, array_column($formColumns, 'label'), array_values($system)),
            'raw' => $rawHeaders,
            'raw_questions' => $rawQuestions,
            'form' => $formColumns,
            'overrides' => $overrides,
            'system' => $system,
        ];
    }

    /**
     * @param  array{headers: array<int, string>, raw: array<int, string>, raw_questions: array<string, int>, form: array<int, array{id: int, label: string, field: string|null}>, overrides: array<string, string>, system: array<string, string>}  $definition
     * @return array<int, string|null>
     */
    public function exportRow(Registration $registration, array $definition): array
    {
        $raw = $registration->raw_data ?? [];
        $row = [];

        foreach ($definition['raw'] as $header) {
            $field = $definition['overrides'][$header] ?? null;
            $value = $field !== null
                ? $this->registrationValue($registration, $field)
                : ($raw[$header] ?? null);

            if ($value === null && isset($definition['raw_questions'][$header])) {
                $value = $this->answerValue($registration, $definition['raw_questions'][$header]);
            }

            if ($field === 'ic_number' || $field === 'phone') {
                $value = $this->excelText($value);
            }

            $row[] = $value;
        }

        foreach ($definition['form'] as $column) {
            $row[] = $column['field'] !== null
                ? $this->registrationValue($registration, $column['field'])
                : $this->answerValue($registration, $column['id']);
        }

        foreach (array_keys($definition['system']) as $key) {
            $row[] = $this->systemValue($registration, $key);
        }

        return $row;
    }

    /**
     * Questions that belong to forms used by this event's registrations.
     *
     * @return array<int, array{id: int, label: string}>
     */
    private function eventFormQuestions(Event $event): array
    {
        $formIds = Registration::query()
            ->where('event_id', $event->id)
            ->whereNotNull('form_id')
            ->distinct()
            ->pluck('form_id');

        $forms = Form::query()
            ->with(['questions' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->where(function ($query) use ($event, $formIds) {
                $query->where('event_id', $event->id);

                if ($formIds->isNotEmpty()) {
                    $query->orWhereIn('id', $formIds);
                }
            })
            ->orderBy('id')
            ->get();

        $questions = [];

        foreach ($forms as $form) {
            foreach ($form->questions as $question) {
                $label = trim((string) $question->label);
                $questions[] = [
                    'id' => (int) $question->id,
                    'label' => $label !== '' ? $label : 'Soalan '.$question->id,
                ];
            }
        }

        return $questions;
    }

    private function answerValue(Registration $registration, int $questionId): ?string
    {
        $answer = $registration->formResponse?->answers
            ->firstWhere('form_question_id', $questionId);

        $value = $answer?->value;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, string>
     */
    private function systemColumns(): array
    {
        return [
            'status' => 'Status',
            'ticket_type' => 'Kategori',
            'organization' => 'Organisasi',
            'created_at' => 'Tarikh Daftar',
        ];
    }

    private function registrationValue(Registration $registration, string $field): ?string
    {
        return match ($field) {
            'name' => $registration->name,
            'ic_number' => $registration->ic_number,
            'phone' => $registration->phone,
            'email' => $registration->email,
            'member_no' => $registration->member_no,
            default => null,
        };
    }

    private function systemValue(Registration $registration, string $key): ?string
    {
        return match ($key) {
            'registration_no' => $registration->registration_no,
            'status' => $registration->status?->label(),
            'ticket_type' => $registration->ticket_type,
            'organization' => $registration->organization?->name,
            'created_at' => $registration->created_at?->toDateTimeString(),
            default => null,
        };
    }

    /**
     * Keep leading-zero identifiers as text when the CSV is opened in Excel.
     */
    private function excelText(?string $value): ?string
    {
        if ($value !== null && preg_match('/^0\d+$/', $value)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<int, string>
     */
    private function headerKeys(array $headers): array
    {
        $keys = [];
        $seen = [];

        foreach ($headers as $index => $header) {
            $key = $header !== '' ? $header : 'Lajur '.($index + 1);
            $base = $key;
            $suffix = 2;

            while (isset($seen[$key])) {
                $key = $base.' ('.$suffix.')';
                $suffix++;
            }

            $seen[$key] = true;
            $keys[$index] = $key;
        }

        return $keys;
    }

    private function rawCell(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value) && floor($value) === $value) {
            $value = number_format($value, 0, '', '');
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readRows(UploadedFile $file): array
    {
        $sheets = Excel::toArray(new RawSpreadsheet, $file);

        return $sheets[0] ?? [];
    }

    /**
     * @param  array<string, int|string|null>  $mapping
     * @return array<string, int|null>
     */
    private function validatedMapping(array $mapping, int $headerCount): array
    {
        $validated = [];

        foreach (['name', 'ic_number', 'phone', 'email', 'member_no'] as $field) {
            $value = $mapping[$field] ?? null;

            if ($value === null || $value === '') {
                $validated[$field] = null;

                continue;
            }

            $index = (int) $value;
            if ($index < 0 || $index >= $headerCount) {
                throw ValidationException::withMessages([
                    'mapping' => "Pemetaan lajur untuk {$field} tidak sah.",
                ]);
            }

            $validated[$field] = $index;
        }

        if ($validated['name'] === null) {
            throw ValidationException::withMessages([
                'mapping' => 'Lajur Nama wajib dipilih.',
            ]);
        }

        return $validated;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<string, int|null>
     */
    private function detectMapping(array $headers): array
    {
        $normalized = array_map(fn (string $header) => $this->normalizeHeader($header), $headers);
        $mapping = [];

        foreach (self::ALIASES as $field => $needles) {
            $mapping[$field] = null;

            foreach ($normalized as $index => $header) {
                if ($header !== '' && in_array($header, $needles, true)) {
                    $mapping[$field] = $index;
                    break;
                }
            }
        }

        // Second pass: tolerant "contains" matching for headers such as
        // "No. IC Peserta" or "Nombor Telefon Bimbit".
        foreach (self::ALIASES as $field => $needles) {
            if ($mapping[$field] !== null) {
                continue;
            }

            foreach ($normalized as $index => $header) {
                foreach ($needles as $needle) {
                    if ($header !== '' && str_contains($header, $needle)) {
                        $mapping[$field] = $index;
                        break 2;
                    }
                }
            }
        }

        return $mapping;
    }

    private function detectField(string $label): ?string
    {
        $normalized = $this->normalizeHeader($label);

        if ($normalized === '') {
            return null;
        }

        foreach (self::ALIASES as $field => $needles) {
            if (in_array($normalized, $needles, true)) {
                return $field;
            }
        }

        foreach (self::ALIASES as $field => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($normalized, $needle)) {
                    return $field;
                }
            }
        }

        return null;
    }

    private function findMatching(int $eventId, ?string $ic, ?string $phone, ?string $email): ?Registration
    {
        $query = Registration::query()->where('event_id', $eventId);

        if ($ic !== null && $match = (clone $query)->where('ic_normalized', $ic)->latest()->first()) {
            return $match;
        }

        if ($phone !== null && $match = (clone $query)->where('phone_normalized', $phone)->latest()->first()) {
            return $match;
        }

        if ($email !== null && $match = (clone $query)->whereRaw('LOWER(email) = ?', [$email])->latest()->first()) {
            return $match;
        }

        return null;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function cell(array $row, ?int $index): ?string
    {
        if ($index === null || ! array_key_exists($index, $row)) {
            return null;
        }

        $value = $row[$index];

        if (is_float($value) && floor($value) === $value) {
            $value = number_format($value, 0, '', '');
        }

        $value = trim((string) $value);

        // Excel treats an explicit apostrophe as a text-escape marker.
        if (str_starts_with($value, "'")) {
            $value = substr($value, 1);
        }

        return $value !== '' ? $value : null;
    }

    private function cleanIc(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strtoupper(trim($value));
        $digits = preg_replace('/\D+/', '', $value);

        return $digits !== '' ? $digits : $value;
    }

    private function cleanPhone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return trim($value);
    }

    private function cleanEmail(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    private function cleanHeader(mixed $value): string
    {
        return trim(str_replace("\xEF\xBB\xBF", '', (string) $value));
    }

    private function normalizeHeader(string $header): string
    {
        $header = str_replace("\xEF\xBB\xBF", '', $header);
        $header = strtolower($header);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $header) ?? '');
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function pushError(array &$errors, string $message): void
    {
        if (count($errors) < self::MAX_ERRORS) {
            $errors[] = $message;
        }
    }
}
