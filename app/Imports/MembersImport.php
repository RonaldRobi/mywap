<?php

namespace App\Imports;

use App\Models\Branch;
use App\Models\BranchTransitionHistory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Concerns\WithStartRow;

class MembersImport implements ToCollection, WithHeadingRow, WithStartRow, WithLimit
{
    protected $organizationId;
    protected $prefix;
    protected $startRow;
    protected $limit;
    protected $padding;
    public $processedCount = 0;
    public $errors = [];

    public function __construct($organizationId, $prefix, $startRow = 2, $limit = 100)
    {
        $this->organizationId = $organizationId;
        $this->prefix = $prefix;
        $this->startRow = $startRow;
        $this->limit = $limit;
        $this->padding = $prefix === 'W' ? 4 : 5;
    }

    public function startRow(): int
    {
        return $this->startRow;
    }

    public function limit(): int
    {
        return $this->limit;
    }

    protected function nextMemberNo(): string
    {
        $max = User::where('member_no', 'like', $this->prefix . '%')
            ->max('member_no_sequence');

        $next = ($max ?? 0) + 1;
        return $this->prefix . str_pad($next, $this->padding, '0', STR_PAD_LEFT);
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $name = trim((string) ($row['nama'] ?? ''));
            $ic = Str::upper(preg_replace('/\s+/', '', trim((string) ($row['ic'] ?? ''))));

            if (empty($name) && empty($ic)) {
                continue;
            }

            if (empty($ic)) {
                $this->errors[] = "Nama '{$name}' — tiada IC, dilewati.";
                continue;
            }

            if (strlen(preg_replace('/[^0-9]/', '', $ic)) < 6) {
                $this->errors[] = "Nama '{$name}' — IC '{$ic}' tidak sah (minimum 6 digit), dilewati.";
                continue;
            }

            // Auto-fill DOB from IC
            $parsedDob = User::parseDobFromIc($ic);
            $rawDob = trim((string) ($row['tarikh_lahir'] ?? ''));
            $dob = !empty($rawDob) ? (date('Y-m-d', strtotime($rawDob)) ?: $parsedDob) : $parsedDob;

            // Auto-fill gender from IC
            $rawGender = trim((string) ($row['jantina'] ?? ''));
            $gender = !empty($rawGender)
                ? (in_array(strtolower($rawGender), ['lelaki', 'perempuan']) ? strtolower($rawGender) : User::guessGenderFromIc($ic))
                : User::guessGenderFromIc($ic);

            // Email fallback
            $email = trim((string) ($row['email'] ?? ''));
            if (empty($email)) {
                $email = strtolower($ic) . '@mywap.my';
            }

            // Branch lookup from Excel column 'cawangan' or 'branch'
            $branchId = null;
            $branchName = trim((string) ($row['cawangan'] ?? $row['branch'] ?? ''));
            if (!empty($branchName)) {
                $branch = Branch::where('organization_id', $this->organizationId)
                    ->where('name', $branchName)
                    ->orWhere('name', 'like', $branchName . '%')
                    ->first();
                if ($branch) {
                    $branchId = $branch->id;
                }
            }

            // Member number: use provided or auto-generate
            $memberNo = trim((string) ($row['no_ahli'] ?? $row['member_no'] ?? ''));
            if (!empty($memberNo)) {
                $memberNo = $this->ensurePrefix($memberNo);
            }

            $user = User::updateOrCreate(
                ['ic_number' => $ic],
                [
                    'member_no' => $memberNo ?: null,
                    'original_member_no' => DB::raw('IFNULL(original_member_no, member_no)'),
                    'name' => $name ?: 'Unknown',
                    'dob' => $dob,
                    'gender' => $gender,
                    'phone' => trim((string) ($row['no_telefon'] ?? '')) ?: null,
                    'email' => $email,
                    'education_level' => trim((string) ($row['pendidikan'] ?? '')) ?: null,
                    'current_profession' => trim((string) ($row['profesion'] ?? '')) ?: null,
                    'address_1' => trim((string) ($row['alamat_1'] ?? '')) ?: null,
                    'address_2' => trim((string) ($row['alamat_2'] ?? '')) ?: null,
                    'postcode' => trim((string) ($row['poskod'] ?? '')) ?: null,
                    'city' => trim((string) ($row['bandar'] ?? '')) ?: null,
                    'state' => trim((string) ($row['negeri'] ?? '')) ?: null,
                    'registration_year' => trim((string) ($row['tahun_daftar'] ?? '')) ?: null,
                    'notes' => trim((string) ($row['catatan'] ?? '')) ?: null,
                    'password' => Hash::make($memberNo ?: 'password123'),
                    'current_organization_id' => $this->organizationId,
                    'branch_id' => $branchId,
                ]
            );

            // If this is a new user (no member_no yet), auto-generate one
            if (!$user->member_no) {
                $newMemberNo = $this->nextMemberNo();
                $seq = (int) substr($newMemberNo, strlen($this->prefix));
                $user->update(['member_no' => $newMemberNo, 'member_no_sequence' => $seq]);
            }

            // Set original_member_no on first creation
            if (!$user->original_member_no) {
                $user->update(['original_member_no' => $user->member_no]);
            }

            // Record branch transition history if branch was set
            if ($branchId && ($user->wasRecentlyCreated || $user->branch_id != $branchId)) {
                BranchTransitionHistory::create([
                    'user_id' => $user->id,
                    'from_branch_id' => $user->wasRecentlyCreated ? null : $user->getOriginal('branch_id'),
                    'to_branch_id' => $branchId,
                    'changed_by' => $user->id,
                    'change_type' => 'import',
                ]);
            }

            $user->assignRole('Member');
            $this->processedCount++;
        }
    }

    protected function ensurePrefix(string $no): string
    {
        $no = strtoupper(trim($no));
        $known = ['W', 'A', 'P', 'WA', 'AP', 'WP', 'WAP', 'WPA', 'AWP', 'APW', 'PWA', 'PAW'];

        foreach ($known as $p) {
            if (str_starts_with($no, $p)) {
                return $no;
            }
        }

        return $this->prefix . ltrim($no, 'WAPwapr');
    }
}
