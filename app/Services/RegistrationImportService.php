<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Imports\RawSpreadsheet;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Registration;
use App\Models\RegistrationImport;
use App\Models\User;
use App\Support\NameMatcher;
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

        $mapping = $this->detectMapping($headers);
        $stats = $this->previewStats($dataRows, $mapping);

        return [
            'headers' => $headers,
            'mapping' => $mapping,
            'sample' => array_slice($dataRows, 0, 5),
            'total_rows' => count($dataRows),
            'stats' => $stats,
            'warnings' => $this->previewWarnings($stats),
        ];
    }

    /**
     * Upsert every data row into the event's registrations.
     *
     * @param  array<string, int|null>  $mapping
     * @return array{total_rows: int, created: int, updated: int, skipped: int, removed: int, errors: array<int, string>, import_id: int}
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
            $touched = [];

            foreach ($dataRows as $offset => $row) {
                $rowNumber = $offset + 2;

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $total++;

                $icRaw = $mapping['ic_number'] !== null ? ($row[$mapping['ic_number']] ?? null) : null;
                $icNumeric = is_int($icRaw) || is_float($icRaw);

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

                // A numeric IC cell in XLSX has already lost leading digits and
                // may be scientific notation under the hood. Never use it as an
                // identity key, even if it ends up looking like 12 digits.
                $icNormalized = $icNumeric ? null : Registration::normalizeIc($ic);
                $phoneNormalized = Registration::normalizePhone($phone);
                $emailNormalized = $email !== null ? mb_strtolower($email) : null;

                if ($icNormalized === null && $phoneNormalized === null && $emailNormalized === null) {
                    $skipped++;
                    $this->pushError($errors, "Baris {$rowNumber}: tiada No IC / telefon / emel — tidak boleh dipadankan, dilewati.");

                    continue;
                }

                $identityKeys = $this->identityKeys($icNormalized, $phoneNormalized, $emailNormalized);
                $duplicateRow = $this->seenDuplicate($seen, $identityKeys, $name);

                if ($duplicateRow !== null) {
                    $skipped++;
                    $this->pushError($errors, "Baris {$rowNumber}: pendua dalam fail yang sama (baris {$duplicateRow}), dilewati.");

                    continue;
                }

                $this->rememberSeen($seen, $identityKeys, $name, $rowNumber);

                $registration = $this->findMatching(
                    $event->id,
                    $icNormalized,
                    $phoneNormalized,
                    $emailNormalized,
                    $name,
                    $ic,
                    $icNumeric,
                );

                $attributes = ['name' => mb_substr($name, 0, 255)];

                // Do not replace a valid IC with a scientific/truncated value
                // from a later upload.
                $keepExistingIc = $registration !== null
                    && $icNormalized === null
                    && $registration->ic_normalized !== null;

                if ($ic !== null && ! $keepExistingIc) {
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

                    // A myWAP registration (or walk-in) must survive future
                    // CSV syncs; everything else follows the latest import.
                    if (! in_array($registration->source, [
                        Registration::SOURCE_FORM,
                        Registration::SOURCE_WALKIN,
                    ], true)) {
                        $attributes['source'] = Registration::SOURCE_IMPORT;
                    }

                    $registration->fill($attributes)->save();
                    $touched[] = $registration->id;
                    $updated++;

                    continue;
                }

                $createdModel = Registration::create(array_merge([
                    'event_id' => $event->id,
                    'organization_id' => $event->organization_id,
                    'status' => RegistrationStatus::Confirmed,
                    'source' => Registration::SOURCE_IMPORT,
                ], $attributes));

                $touched[] = $createdModel->id;
                $created++;
            }

            // The latest CSV is the authoritative import snapshot. Remove
            // imported rows that disappeared from it, but never touch myWAP
            // registrations, walk-ins, or records with attendance/payment/form
            // history.
            $removed = 0;

            if ($touched !== [] && $total > 0) {
                $staleIds = Registration::query()
                    ->where('event_id', $event->id)
                    ->where('source', Registration::SOURCE_IMPORT)
                    ->whereNotIn('id', $touched)
                    ->whereDoesntHave('attendance')
                    ->whereDoesntHave('payments')
                    ->whereDoesntHave('formResponse')
                    ->pluck('id');

                if ($staleIds->isNotEmpty()) {
                    $removed = $staleIds->count();
                    Registration::query()->whereKey($staleIds)->delete();
                }
            }

            return compact('total', 'created', 'updated', 'skipped', 'removed', 'errors');
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
            'removed_count' => $result['removed'],
            'errors' => $result['errors'] !== [] ? $result['errors'] : null,
        ]);

        return [
            'total_rows' => $result['total'],
            'created' => $result['created'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
            'removed' => $result['removed'],
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

            if ($rawIndex === false) {
                foreach ($rawHeaders as $candidate) {
                    if ($this->labelsMatch($candidate, $question['label'])) {
                        $rawIndex = array_search($candidate, $rawHeaders, true);
                        break;
                    }
                }
            }

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
            $value = $column['field'] !== null
                ? $this->registrationValue($registration, $column['field'])
                : $this->answerValue($registration, $column['id']);

            if ($value === null) {
                $value = $this->matchRawValue($raw, $column['label']);
            }

            $row[] = $value;
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
        $answer = $this->formResponseFor($registration)?->answers
            ->firstWhere('form_question_id', $questionId);

        $value = $answer?->value;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Older responses may not have registration_id yet; match the response by
     * form plus user/email/name and memoize it on the registration model.
     */
    private function formResponseFor(Registration $registration): ?FormResponse
    {
        if ($registration->relationLoaded('formResponse')) {
            $response = $registration->getRelation('formResponse');
        } else {
            $response = $registration->formResponse;
        }

        if ($response !== null || ! $registration->form_id) {
            return $response;
        }

        $response = FormResponse::query()
            ->where('form_id', $registration->form_id)
            ->where(function ($query) use ($registration) {
                if ($registration->user_id) {
                    $query->orWhere('user_id', $registration->user_id);
                }
                if ($registration->email) {
                    $query->orWhereRaw('LOWER(respondent_email) = ?', [mb_strtolower($registration->email)]);
                }
                if ($registration->name) {
                    $query->orWhere('respondent_name', $registration->name);
                }
            })
            ->with('answers')
            ->latest('submitted_at')
            ->latest('id')
            ->first();

        $registration->setRelation('formResponse', $response);

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function systemColumns(): array
    {
        return [
            'status' => 'Status',
            'ticket_type' => 'Kategori',
            'organization' => 'Organisasi myWAP',
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
        // Read CSV as raw text so scientific-notation ICs ("9.50329E+11")
        // remain detectable instead of being silently converted to numbers.
        if (in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'txt'], true)) {
            return $this->readCsvRows($file->getRealPath());
        }

        $sheets = Excel::toArray(new RawSpreadsheet, $file);

        return $sheets[0] ?? [];
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $delimiter = $this->detectDelimiter($path);
        $rows = [];

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $rows[] = array_map(
                fn ($value) => $value === null ? null : (string) $value,
                $row,
            );
        }

        fclose($handle);

        return $rows;
    }

    private function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'rb');
        $line = $handle ? (string) fgets($handle) : '';

        if ($handle) {
            fclose($handle);
        }

        $counts = [
            ',' => substr_count($line, ','),
            ';' => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
        ];

        arsort($counts);
        $delimiter = array_key_first($counts);

        return $delimiter !== null && $counts[$delimiter] > 0 ? $delimiter : ',';
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

            // Prefer aliases in priority order (e.g. "EMEL" before the
            // Google account column named "Email Address").
            foreach ($needles as $needle) {
                foreach ($normalized as $index => $header) {
                    if ($header !== '' && ! $this->isCompanyLabel($header) && $header === $needle) {
                        $mapping[$field] = $index;
                        break 2;
                    }
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
                    if ($header !== '' && ! $this->isCompanyLabel($header) && str_contains($header, $needle)) {
                        $mapping[$field] = $index;
                        break 2;
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Labels such as "Nama Syarikat/Agensi/Organisasi/NGO" are custom fields,
     * not the participant's own name/phone/email.
     */
    private function isCompanyLabel(string $header): bool
    {
        foreach ([
            'syarikat', 'agensi', 'organisasi', 'ngo', 'jabatan', 'institusi',
            'universiti', 'sekolah', 'kilang', 'pertubuhan', 'badan', 'kementerian',
        ] as $word) {
            if (str_contains($header, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match two column labels even when the CSV and the myWAP form used
     * different wording (e.g. "ORGANISASI/SYARIKAT" vs
     * "NAMA SYARIKAT/AGENSI/ORGANISASI/NGO").
     */
    public function labelsMatch(string $a, string $b): bool
    {
        $a = $this->normalizeHeader($a);
        $b = $this->normalizeHeader($b);

        if ($a === '' || $b === '') {
            return false;
        }

        if ($a === $b) {
            return true;
        }

        if ($this->isCompanyLabel($a) && $this->isCompanyLabel($b)) {
            return true;
        }

        $aTokens = $this->labelTokens($a);
        $bTokens = $this->labelTokens($b);

        if ($aTokens === [] || $bTokens === []) {
            return false;
        }

        $shared = array_intersect($aTokens, $bTokens);

        return $shared !== []
            && count($shared) / max(1, min(count($aTokens), count($bTokens))) >= 0.5;
    }

    /**
     * Pick the raw_data value whose header best matches a form question.
     *
     * @param  array<string, mixed>  $rawData
     */
    public function matchRawValue(array $rawData, string $label): ?string
    {
        foreach ($rawData as $header => $value) {
            if ($this->labelsMatch((string) $header, $label)) {
                return is_scalar($value) ? (string) $value : null;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function labelTokens(string $label): array
    {
        $stopWords = ['nama', 'no', 'nombor', 'tarikh', 'masa', 'penuh'];
        $tokens = preg_split('/\s+/', $label) ?: [];

        return array_values(array_filter(
            array_unique($tokens),
            fn (string $token) => strlen($token) > 2 && ! in_array($token, $stopWords, true),
        ));
    }

    private function detectField(string $label): ?string
    {
        $normalized = $this->normalizeHeader($label);

        if ($normalized === '' || $this->isCompanyLabel($normalized)) {
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

    /**
     * @return array<int, string>
     */
    private function phoneVariants(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return [];
        }

        $variants = [$phone, $digits];

        if (str_starts_with($digits, '60')) {
            $variants[] = '0'.substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            $variants[] = substr($digits, 1);
        }
        if (! str_starts_with($digits, '0') && ! str_starts_with($digits, '60')) {
            $variants[] = '0'.$digits;
        }

        $variants[] = '60'.ltrim($digits, '0');

        return array_values(array_unique(array_filter($variants)));
    }

    private function findMatching(
        int $eventId,
        ?string $ic,
        ?string $phone,
        ?string $email,
        string $name,
        ?string $rawIc = null,
        bool $icNumeric = false,
    ): ?Registration {
        $query = Registration::query()->where('event_id', $eventId);

        if ($ic !== null && $match = (clone $query)->where('ic_normalized', $ic)->latest()->first()) {
            return $match;
        }

        // Passports / other IDs that are not 12-digit MyKad values can still
        // match exactly, but only when the name is consistent.
        if ($rawIc !== null && ! $icNumeric && $ic === null && ! preg_match('/[eE][+-]?\d+/', $rawIc)) {
            foreach ((clone $query)->where('ic_number', $rawIc)->latest()->limit(5)->get() as $candidate) {
                if (NameMatcher::similar($name, $candidate->name)) {
                    return $candidate;
                }
            }
        }

        if ($phone !== null) {
            foreach ((clone $query)->where('phone_normalized', $phone)->latest()->limit(5)->get() as $candidate) {
                if (NameMatcher::similar($name, $candidate->name)) {
                    return $candidate;
                }
            }

            // Legacy rows may store 123456789 instead of 0123456789.
            $variants = $this->phoneVariants($phone);

            foreach ((clone $query)
                ->where(fn ($q) => $q->whereIn('phone_normalized', $variants)->orWhereIn('phone', $variants))
                ->latest()
                ->limit(10)
                ->get() as $candidate) {
                if (NameMatcher::similar($name, $candidate->name)) {
                    return $candidate;
                }
            }
        }

        if ($email !== null) {
            foreach ((clone $query)->whereRaw('LOWER(email) = ?', [$email])->latest()->limit(5)->get() as $candidate) {
                if (NameMatcher::similar($name, $candidate->name)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function identityKeys(?string $ic, ?string $phone, ?string $email): array
    {
        $keys = [];

        if ($ic !== null) {
            $keys[] = 'ic:'.$ic;
        }
        if ($phone !== null) {
            $keys[] = 'phone:'.$phone;
        }
        if ($email !== null) {
            $keys[] = 'email:'.$email;
        }

        return $keys;
    }

    /**
     * @param  array<string, array<int, array{row: int, name: string}>>  $seen
     * @param  array<int, string>  $keys
     */
    private function seenDuplicate(array $seen, array $keys, string $name): ?int
    {
        foreach ($keys as $key) {
            foreach ($seen[$key] ?? [] as $entry) {
                if (str_starts_with($key, 'ic:') || NameMatcher::similar($name, $entry['name'])) {
                    return $entry['row'];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, array<int, array{row: int, name: string}>>  $seen
     * @param  array<int, string>  $keys
     */
    private function rememberSeen(array &$seen, array $keys, string $name, int $rowNumber): void
    {
        foreach ($keys as $key) {
            $seen[$key][] = ['row' => $rowNumber, 'name' => $name];
        }
    }

    /**
     * @param  array<int, array<int, mixed>>  $dataRows
     * @param  array<string, int|null>  $mapping
     * @return array{total_rows: int, unique_identities: int, duplicate_rows: int, invalid_ic_rows: int, numeric_ic_rows: int}
     */
    private function previewStats(array $dataRows, array $mapping): array
    {
        $total = 0;
        $unique = 0;
        $duplicates = 0;
        $invalidIc = 0;
        $numericIc = 0;
        $seen = [];

        foreach ($dataRows as $row) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $name = $this->cell($row, $mapping['name'] ?? null);

            if ($name === null) {
                continue;
            }

            $total++;

            $icRaw = $mapping['ic_number'] !== null ? ($row[$mapping['ic_number']] ?? null) : null;
            $icNumeric = is_int($icRaw) || is_float($icRaw);
            $ic = $this->cleanIc($this->cell($row, $mapping['ic_number'] ?? null));
            $phone = $this->cleanPhone($this->cell($row, $mapping['phone'] ?? null));
            $email = $this->cleanEmail($this->cell($row, $mapping['email'] ?? null));

            $icNormalized = $icNumeric ? null : Registration::normalizeIc($ic);
            $phoneNormalized = Registration::normalizePhone($phone);
            $emailNormalized = $email !== null ? mb_strtolower($email) : null;

            if ($icNumeric) {
                $numericIc++;
            } elseif ($ic !== null && $icNormalized === null) {
                $invalidIc++;
            }

            if ($icNormalized === null && $phoneNormalized === null && $emailNormalized === null) {
                continue;
            }

            $keys = $this->identityKeys($icNormalized, $phoneNormalized, $emailNormalized);

            if ($this->seenDuplicate($seen, $keys, $name) !== null) {
                $duplicates++;

                continue;
            }

            $this->rememberSeen($seen, $keys, $name, $total);
            $unique++;
        }

        return [
            'total_rows' => $total,
            'unique_identities' => $unique,
            'duplicate_rows' => $duplicates,
            'invalid_ic_rows' => $invalidIc,
            'numeric_ic_rows' => $numericIc,
        ];
    }

    /**
     * @param  array{total_rows: int, unique_identities: int, duplicate_rows: int, invalid_ic_rows: int, numeric_ic_rows: int}  $stats
     * @return array<int, string>
     */
    private function previewWarnings(array $stats): array
    {
        $warnings = [];

        if ($stats['invalid_ic_rows'] > 0) {
            $warnings[] = "{$stats['invalid_ic_rows']} baris No. IC tidak sah atau dalam format saintifik. Padanan akan guna telefon + nama.";
        }

        if ($stats['numeric_ic_rows'] > 0) {
            $warnings[] = "{$stats['numeric_ic_rows']} No. IC dibaca sebagai nombor. Format kolum IC sebagai Teks sebelum export.";
        }

        if ($stats['duplicate_rows'] > 0) {
            $warnings[] = "{$stats['duplicate_rows']} baris dikesan pendua (nama/telefon sama) dan akan digabungkan.";
        }

        return $warnings;
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

        return $value !== '' ? $value : null;
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
