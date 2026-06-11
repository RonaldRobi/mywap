<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithLimit;

class MembersImport implements ToCollection, WithStartRow, WithLimit
{
    protected $organizationId;
    protected $prefix;
    protected $startRow;
    protected $limit;
    public $processedCount = 0;

    public function __construct($organizationId, $prefix, $startRow = 2, $limit = 100)
    {
        $this->organizationId = $organizationId;
        $this->prefix = $prefix;
        $this->startRow = $startRow;
        $this->limit = $limit;
    }

    public function startRow(): int
    {
        return $this->startRow;
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Check if essential fields (NAMA and NO KP) are somewhat present
            if (empty($row[1]) && empty($row[5])) {
                continue;
            }

            $icNumber = $row[5] ?? '';
            $icNumber = Str::upper(preg_replace('/\s+/', '', trim((string) $icNumber)));

            if (empty($icNumber)) {
                $icNumber = 'NO-IC-' . Str::random(8); // Fallback if IC is completely empty but name exists
            }

            // Generate an email if not provided
            $email = $row[14] ?? '';
            if (empty(trim($email))) {
                $email = strtolower($icNumber) . '@mywap.my';
            } else {
                $email = trim($email);
            }

            // Handle Key-in date
            $keyInDate = null;
            if (!empty($row[3])) {
                try {
                    $keyInDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row[3])->format('Y-m-d');
                } catch (\Throwable $e) { // Changed to Throwable to catch TypeError in PHP 8
                    $keyInDate = date('Y-m-d', strtotime($row[3]));
                }
            }
            
            // Year of birth -> dob
            $dob = null;
            $birthYear = trim((string)($row[6] ?? ''));
            if (!empty($birthYear) && is_numeric($birthYear)) {
                $dob = $birthYear . '-01-01';
            } else {
                $dob = '1970-01-01';
            }

            // Prefix handling for member_no
            $memberNo = $row[0] ?? null;
            if (!empty($memberNo)) {
                $memberNo = trim((string) $memberNo);
                // Strip existing prefix if user accidentally typed it so we don't get WW0001
                if (!empty($this->prefix)) {
                    $memberNo = ltrim($memberNo, $this->prefix);
                    $memberNo = $this->prefix . $memberNo;
                }
            }

            User::updateOrCreate(
                ['ic_number' => $icNumber], // Match based on IC Number
                [
                    'member_no' => $memberNo,
                    'name' => $row[1] ?? 'Unknown',
                    'wadah_state' => $row[2] ?? null,
                    'key_in_date' => $keyInDate,
                    'registration_year' => $row[4] ?? null,
                    'birth_year' => $birthYear,
                    'address_1' => $row[8] ?? null,
                    'address_2' => $row[9] ?? null,
                    'postcode' => $row[10] ?? null,
                    'city' => $row[11] ?? null,
                    'state' => $row[12] ?? null,
                    'phone' => $row[13] ?? null,
                    'email' => $email,
                    'office_phone' => $row[15] ?? null,
                    'home_phone' => $row[16] ?? null,
                    'fax_number' => $row[17] ?? null,
                    'current_profession' => $row[18] ?? null,
                    'education_level' => $row[19] ?? null,
                    'legacy_update_note' => $row[20] ?? null,
                    'notes' => $row[21] ?? null,
                    'dob' => $dob,
                    'password' => Hash::make($memberNo ?? 'password123'),
                    'current_organization_id' => $this->organizationId,
                ]
            )->assignRole('Member');

            $this->processedCount++;
        }
    }
}
