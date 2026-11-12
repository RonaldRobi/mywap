<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportWadahMaster extends Command
{
    protected $signature = 'import:wadah-master {file? : Path ke CSV master WADAH} {--commit : Commit ke database (default dry-run)}';

    protected $description = 'Import ahli WADAH dari CSV master (dry-run secara default)';

    private bool $commit = false;

    private int $orgId = 0;

    private ?int $pusatBranchId = null;

    private ?int $deathPositionId = null;

    private int $memberRoleId = 0;

    private string $dryRunHash = '';

    private array $branchMap = [];

    private array $existingMemberNos = [];

    private array $existingEmails = [];

    private array $existingIcs = [];

    private array $existingIcCandidates = [];

    private array $records = [];

    private int $baselineWadahCount = 0;

    private array $ambiguousIc = [
        '530605025277' => '441/700',
        '630413045650' => '685/721',
        '660902075540' => '1488/2519',
        '770809016383' => '1767/2063',
    ];

    private array $explicitSkips = [
        '59' => 'bertindih W71078',
        '1340' => 'bertindih W1340',
        '1799' => 'bertindih W71080',
        '1922' => 'bertindih W71081',
    ];

    public function handle(): int
    {
        $this->commit = (bool) $this->option('commit');
        $this->dryRunHash = Hash::make('dryrun');

        $file = $this->argument('file')
            ?: storage_path('app/wadah-reconciliation/1_MASTER_WADAH_SEMUA_NEGERI_TERKINI.csv');

        if (! is_file($file)) {
            $this->error("Fail tak jumpa: {$file}");

            return self::FAILURE;
        }

        $org = Organization::where('slug', 'wadah')->first();
        if (! $org) {
            $this->error('Organisasi WADAH tak jumpa');

            return self::FAILURE;
        }
        $this->orgId = (int) $org->id;

        $this->memberRoleId = (int) DB::table('roles')->where('name', 'Member')->value('id');
        if ($this->memberRoleId === 0) {
            $this->error('Role Member tak jumpa');

            return self::FAILURE;
        }

        $rows = $this->readCsv($file);
        if ($rows === []) {
            $this->error('CSV kosong atau gagal dibaca');

            return self::FAILURE;
        }

        $this->loadExistingUsers();
        $this->baselineWadahCount = DB::table('users')
            ->where('current_organization_id', $this->orgId)
            ->whereNull('deleted_at')
            ->count();

        DB::beginTransaction();

        try {
            $this->ensurePusatBranch();
            $this->loadBranches();
            $this->ensureDeathPosition();

            $this->buildRecords($rows);
            $this->insertRecords();
            $this->validateCounts();
            $this->writePreview();

            if ($this->commit) {
                DB::commit();
                $this->info('COMMIT selesai.');
            } else {
                DB::rollBack();
                $this->warn('DRY RUN — semua perubahan DB di-rollback.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('RALAT: '.$e->getMessage());
            $this->error($e->getFile().':'.$e->getLine());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function readCsv(string $file): array
    {
        $handle = fopen($file, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);

            return [];
        }

        $headers = array_map(
            fn ($header) => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $header)),
            $headers
        );

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) !== count($headers)) {
                continue;
            }

            $row = array_combine($headers, $line);
            if ($row === false) {
                continue;
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function loadExistingUsers(): void
    {
        $users = DB::table('users')
            ->select(
                'id',
                'member_no',
                'name',
                'email',
                'phone',
                'address_1',
                'original_member_no',
                'ic_number',
                'current_organization_id',
                'deleted_at',
                'first_login_at',
                'is_active'
            )
            ->get();

        foreach ($users as $user) {
            if (! empty($user->member_no)) {
                $this->existingMemberNos[(string) $user->member_no] = (int) $user->id;
            }

            if (! empty($user->email)) {
                $this->existingEmails[strtolower(trim((string) $user->email))] = (int) $user->id;
            }

            $ic = $this->cleanIc($user->ic_number);
            if ($ic === null) {
                continue;
            }

            $this->existingIcs[$ic] = (int) $user->id;

            $this->existingIcCandidates[$ic][] = [
                'id' => (int) $user->id,
                'member_no' => (string) $user->member_no,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'phone' => (string) $user->phone,
                'address_1' => (string) $user->address_1,
                'original_member_no' => (string) $user->original_member_no,
                'ic_number' => (string) $user->ic_number,
                'org_id' => (int) $user->current_organization_id,
                'deleted_at' => $user->deleted_at,
                'first_login_at' => $user->first_login_at,
                'is_active' => (bool) $user->is_active,
            ];
        }
    }

    private function ensurePusatBranch(): void
    {
        $branch = DB::table('branches')
            ->where('organization_id', $this->orgId)
            ->whereRaw('UPPER(name) = ?', ['PUSAT'])
            ->first();

        if ($branch) {
            $this->pusatBranchId = (int) $branch->id;

            return;
        }

        $this->pusatBranchId = (int) DB::table('branches')->insertGetId([
            'organization_id' => $this->orgId,
            'name' => 'PUSAT',
            'state' => null,
            'address' => null,
            'phone' => null,
            'email' => null,
            'logo_path' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function loadBranches(): void
    {
        $this->branchMap = [];

        foreach (DB::table('branches')->where('organization_id', $this->orgId)->get() as $branch) {
            $this->branchMap[$this->normalizePlace((string) $branch->name)] = $branch;
        }
    }

    private function ensureDeathPosition(): void
    {
        $position = DB::table('organization_positions')
            ->where('organization_id', $this->orgId)
            ->whereRaw('UPPER(name) = ?', ['MENINGGAL DUNIA'])
            ->first();

        if ($position) {
            $this->deathPositionId = (int) $position->id;

            return;
        }

        $this->deathPositionId = (int) DB::table('organization_positions')->insertGetId([
            'organization_id' => $this->orgId,
            'name' => 'Meninggal Dunia',
            'description' => 'Status ahli yang telah meninggal dunia',
            'category' => 'Status',
            'parent_id' => null,
            'display_order' => 999,
            'is_active' => true,
            'color' => '#6b7280',
            'show_badge' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function buildRecords(array $rows): void
    {
        $this->records = [];
        $seenMemberNos = [];

        foreach ($rows as $row) {
            $noAhli = trim((string) ($row['No Ahli'] ?? ''));
            if ($noAhli === '') {
                continue;
            }

            $memberNo = 'W'.str_pad($noAhli, 4, '0', STR_PAD_LEFT);
            $name = (string) User::formatName($this->normalizeName((string) ($row['Nama'] ?? '')));
            $negeri = strtoupper(trim((string) ($row['Negeri'] ?? '')));
            $rawIc = trim((string) ($row['No IC'] ?? ''));
            $ic = $this->cleanIc($rawIc);
            $email = trim((string) ($row['Email'] ?? ''));
            $phone = trim((string) ($row['No Telefon'] ?? ''));
            $lifetime = str_contains(strtolower((string) ($row['Remark Yuran (Kuning)'] ?? '')), 'tak perlu kutip');
            $died = str_contains(strtolower((string) ($row['Remark Status (Merah)'] ?? '')), 'meninggal');

            $record = [
                'no_ahli' => $noAhli,
                'member_no' => $memberNo,
                'name' => $name,
                'raw_ic' => $rawIc,
                'ic' => $ic,
                'ic_status' => $rawIc === '' ? 'kosong' : ($ic === null ? 'tidak_sah' : 'ok'),
                'email' => $email,
                'email_status' => '',
                'phone' => $phone,
                'negeri' => $negeri,
                'branch_id' => null,
                'branch_name' => '',
                'state' => null,
                'wadah_state' => null,
                'address' => trim((string) ($row['Alamat'] ?? '')),
                'fee_status' => $lifetime ? 'life_member' : 'paid',
                'lifetime' => $lifetime,
                'died' => $died,
                'action' => 'insert',
                'reason' => null,
                'move_user_id' => null,
                'move_from_member_no' => null,
                'move_from_org' => null,
            ];

            if ($name === '') {
                $record['action'] = 'skip';
                $record['reason'] = 'tiada nama';
                $this->records[] = $record;
                continue;
            }

            if (isset($this->explicitSkips[$noAhli])) {
                $record['action'] = 'skip';
                $record['reason'] = $this->explicitSkips[$noAhli];
                $this->records[] = $record;
                continue;
            }

            if (isset($seenMemberNos[$memberNo])) {
                $record['action'] = 'skip';
                $record['reason'] = 'member_no pendua dalam CSV';
                $this->records[] = $record;
                continue;
            }
            $seenMemberNos[$memberNo] = true;

            if (isset($this->existingMemberNos[$memberNo])) {
                $record['action'] = 'skip';
                $record['reason'] = 'member_no sudah wujud';
                $this->records[] = $record;
                continue;
            }

            if ($ic !== null && isset($this->existingIcs[$ic])) {
                $move = $this->chooseAbimMove($ic, $name);

                if ($move === null) {
                    $record['action'] = 'skip';
                    $record['reason'] = 'IC sudah wujud (bukan ABIM aktif)';
                    $this->records[] = $record;
                    continue;
                }

                $record['action'] = 'move';
                $record['ic_status'] = 'pindah_abim';
                $record['move_user_id'] = $move['id'];
                $record['move_from_member_no'] = $move['member_no'];
                $record['move_from_org'] = $move['org_id'];
                $record['reason'] = 'Pindah ABIM ('.$move['member_no'].') ke WADAH';

                if ($move['email'] !== '') {
                    $record['email'] = $move['email'];
                    $record['email_status'] = 'kekal_abim';
                }
            }

            if ($email === '' && $record['email'] === '') {
                $record['email'] = strtolower($memberNo).'@mywap.my';
                $record['email_status'] = 'placeholder';
            } elseif ($record['email_status'] === '') {
                if (filter_var($record['email'], FILTER_VALIDATE_EMAIL) === false) {
                    $record['email_status'] = 'tidak_sah';
                } else {
                    $record['email_status'] = 'ok';
                }
            }

            [$branchId, $branchName] = $this->resolveBranch($negeri);
            if ($branchId === null) {
                $record['action'] = 'skip';
                $record['reason'] = 'cawangan tidak ditemui: '.$negeri;
                $this->records[] = $record;
                continue;
            }

            $record['branch_id'] = $branchId;
            $record['branch_name'] = $branchName;

            if ($negeri !== '' && $negeri !== 'TIADA NEGERI') {
                $record['state'] = Str::title(strtolower($negeri));
                $record['wadah_state'] = $negeri;
            }

            $this->records[] = $record;
        }

        $this->resolveDuplicateIcs();
    }

    private function chooseAbimMove(string $ic, string $csvName): ?array
    {
        $candidates = $this->existingIcCandidates[$ic] ?? [];

        $candidates = array_values(array_filter(
            $candidates,
            fn (array $candidate) => $candidate['org_id'] === 2
                && $candidate['deleted_at'] === null
                && $candidate['is_active']
        ));

        if ($candidates === []) {
            return null;
        }

        $csvKey = $this->normalizePersonName($csvName);

        usort($candidates, function (array $a, array $b) use ($csvKey) {
            $aLogin = $a['first_login_at'] !== null ? 1 : 0;
            $bLogin = $b['first_login_at'] !== null ? 1 : 0;
            if ($aLogin !== $bLogin) {
                return $bLogin <=> $aLogin;
            }

            $aExact = $this->normalizePersonName($a['name']) === $csvKey ? 1 : 0;
            $bExact = $this->normalizePersonName($b['name']) === $csvKey ? 1 : 0;
            if ($aExact !== $bExact) {
                return $bExact <=> $aExact;
            }

            $aPrefix = str_starts_with($a['member_no'], 'AWAW') ? 2 : (str_starts_with($a['member_no'], 'AW') ? 1 : 0);
            $bPrefix = str_starts_with($b['member_no'], 'AWAW') ? 2 : (str_starts_with($b['member_no'], 'AW') ? 1 : 0);
            if ($aPrefix !== $bPrefix) {
                return $bPrefix <=> $aPrefix;
            }

            return $a['id'] <=> $b['id'];
        });

        return $candidates[0];
    }

    private function normalizePersonName(string $value): string
    {
        $value = strtoupper($value);
        $value = (string) preg_replace('/\b(BINTI|BIN|BT)\b/', ' ', $value);

        return (string) preg_replace('/[^A-Z]/', '', $value);
    }

    private function resolveDuplicateIcs(): void
    {
        $moveByIc = [];

        foreach ($this->records as $index => $record) {
            if ($record['action'] !== 'move' || $record['ic'] === null) {
                continue;
            }

            $moveByIc[$record['ic']][] = $index;
        }

        foreach ($moveByIc as $indices) {
            if (count($indices) < 2) {
                continue;
            }

            array_shift($indices);

            foreach ($indices as $index) {
                $this->records[$index]['action'] = 'skip';
                $this->records[$index]['reason'] = 'IC pendua orang sama (pindah)';
            }
        }

        $byIc = [];

        foreach ($this->records as $index => $record) {
            if ($record['action'] !== 'insert' || $record['ic'] === null) {
                continue;
            }

            $byIc[$record['ic']][] = $index;
        }

        foreach ($byIc as $ic => $indices) {
            if (count($indices) < 2) {
                continue;
            }

            array_shift($indices);

            if (isset($this->ambiguousIc[$ic])) {
                foreach ($indices as $index) {
                    $this->records[$index]['ic'] = null;
                    $this->records[$index]['ic_status'] = 'manual_duplicate';
                    $this->records[$index]['reason'] = 'IC bertindih; semak manual ('.$this->ambiguousIc[$ic].')';
                }

                continue;
            }

            foreach ($indices as $index) {
                $this->records[$index]['action'] = 'skip';
                $this->records[$index]['reason'] = 'IC pendua orang sama';
            }
        }
    }

    private function insertRecords(): void
    {
        $inserted = array_values(array_filter(
            $this->records,
            fn (array $record) => $record['action'] === 'insert'
        ));

        $moved = array_values(array_filter(
            $this->records,
            fn (array $record) => $record['action'] === 'move'
        ));

        $now = now();

        foreach (array_chunk($inserted, 500) as $chunk) {
            $users = [];

            foreach ($chunk as $record) {
                $users[] = [
                    'name' => $record['name'],
                    'email' => $record['email'],
                    'password' => $this->commit
                        ? password_hash($record['member_no'], PASSWORD_BCRYPT, ['cost' => 10])
                        : $this->dryRunHash,
                    'phone' => $record['phone'] !== '' ? $record['phone'] : null,
                    'dob' => User::parseDobFromIc($record['ic']),
                    'gender' => User::guessGenderFromIc($record['ic']),
                    'current_organization_id' => $this->orgId,
                    'branch_id' => $record['branch_id'],
                    'member_no' => $record['member_no'],
                    'member_no_sequence' => (int) $record['no_ahli'],
                    'original_member_no' => $record['member_no'],
                    'ic_number' => $record['ic'],
                    'wadah_state' => $record['wadah_state'],
                    'state' => $record['state'],
                    'address_1' => $record['address'] !== '' ? $record['address'] : null,
                    'position' => $record['died'] ? 'Meninggal Dunia' : null,
                    'position_id' => $record['died'] ? $this->deathPositionId : null,
                    'notes' => $record['died'] ? 'Meninggal Dunia' : null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('users')->insert($users);

            $memberNos = array_column($chunk, 'member_no');
            $ids = DB::table('users')
                ->whereIn('member_no', $memberNos)
                ->pluck('id', 'member_no');

            $fees = [];
            $roles = [];

            foreach ($chunk as $record) {
                $userId = (int) ($ids[$record['member_no']] ?? 0);
                if ($userId === 0) {
                    throw new \RuntimeException('Gagal dapat user_id untuk '.$record['member_no']);
                }

                $fees[] = $this->newFeeRow($userId, $record, $now);
                $roles[] = [
                    'role_id' => $this->memberRoleId,
                    'model_type' => User::class,
                    'model_id' => $userId,
                ];
            }

            DB::table('membership_fees')->insert($fees);
            DB::table('model_has_roles')->insert($roles);
        }

        foreach ($moved as $record) {
            $this->moveAbimUser($record, $now);
        }
    }

    private function moveAbimUser(array $record, $now): void
    {
        $user = DB::table('users')->where('id', $record['move_user_id'])->first();
        if (! $user) {
            throw new \RuntimeException('Akaun ABIM tak jumpa: '.$record['move_from_member_no']);
        }

        $update = [
            'current_organization_id' => $this->orgId,
            'branch_id' => $record['branch_id'],
            'member_no' => $record['member_no'],
            'member_no_sequence' => (int) $record['no_ahli'],
            'state' => $record['state'],
            'wadah_state' => $record['wadah_state'],
            'position' => $record['died'] ? 'Meninggal Dunia' : null,
            'position_id' => $record['died'] ? $this->deathPositionId : null,
            'updated_at' => $now,
        ];

        if (! $user->original_member_no) {
            $update['original_member_no'] = $user->member_no;
        }

        if (trim((string) $user->name) === '') {
            $update['name'] = $record['name'];
        }

        if (trim((string) $user->email) === '') {
            $update['email'] = $record['email'];
        }

        if (trim((string) $user->phone) === '') {
            $update['phone'] = $record['phone'] !== '' ? $record['phone'] : null;
        }

        if (trim((string) $user->address_1) === '') {
            $update['address_1'] = $record['address'] !== '' ? $record['address'] : null;
        }

        if ($record['died'] && trim((string) $user->notes) === '') {
            $update['notes'] = 'Meninggal Dunia';
        }

        DB::table('users')->where('id', $user->id)->update($update);

        $existingFee = DB::table('membership_fees')
            ->where('user_id', $user->id)
            ->where('year', 2026)
            ->first();

        if ($existingFee) {
            $feeUpdate = [
                'organization_id' => $this->orgId,
                'updated_at' => $now,
            ];

            if ($existingFee->status === 'life_member') {
                $feeUpdate['organization_id'] = $this->orgId;
            } elseif ($record['fee_status'] === 'life_member') {
                $feeUpdate['status'] = 'life_member';
                $feeUpdate['amount'] = 0;
                $feeUpdate['paid_at'] = $now;
                $feeUpdate['notes'] = 'Ahli seumur hidup';
            } elseif ($existingFee->status !== 'paid' || (float) $existingFee->amount <= 0) {
                $feeUpdate['status'] = 'paid';
                $feeUpdate['amount'] = 0;
                $feeUpdate['paid_at'] = $now;
                $feeUpdate['notes'] = 'Pengecualian Yuran 2026';
            }

            DB::table('membership_fees')->where('id', $existingFee->id)->update($feeUpdate);
        } else {
            DB::table('membership_fees')->insert([$this->newFeeRow((int) $user->id, $record, $now)]);
        }

        $hasRole = DB::table('model_has_roles')
            ->where('role_id', $this->memberRoleId)
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->exists();

        if (! $hasRole) {
            DB::table('model_has_roles')->insert([
                'role_id' => $this->memberRoleId,
                'model_type' => User::class,
                'model_id' => $user->id,
            ]);
        }
    }

    private function newFeeRow(int $userId, array $record, $now): array
    {
        return [
            'user_id' => $userId,
            'organization_id' => $this->orgId,
            'year' => 2026,
            'amount' => 0,
            'status' => $record['fee_status'],
            'paid_at' => $now,
            'payment_id' => null,
            'notes' => $record['fee_status'] === 'life_member'
                ? 'Ahli seumur hidup'
                : 'Pengecualian Yuran 2026',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function validateCounts(): void
    {
        $inserted = array_values(array_filter(
            $this->records,
            fn (array $record) => $record['action'] === 'insert'
        ));

        $moved = array_values(array_filter(
            $this->records,
            fn (array $record) => $record['action'] === 'move'
        ));

        $skipped = array_values(array_filter(
            $this->records,
            fn (array $record) => $record['action'] === 'skip'
        ));

        $processed = array_merge($inserted, $moved);

        if ($processed === []) {
            $this->printSummary([], [], $skipped);

            return;
        }

        $insertedMemberNos = array_column($inserted, 'member_no');
        $userCount = $inserted === []
            ? 0
            : DB::table('users')->whereIn('member_no', $insertedMemberNos)->count();

        if ($userCount !== count($inserted)) {
            throw new \RuntimeException("Jumlah user baru tidak sepadan: {$userCount} vs ".count($inserted));
        }

        $insertedIds = $inserted === []
            ? []
            : DB::table('users')->whereIn('member_no', $insertedMemberNos)->pluck('id')->all();

        $movedIds = array_column($moved, 'move_user_id');
        $movedCount = $movedIds === []
            ? 0
            : DB::table('users')
                ->whereIn('id', $movedIds)
                ->where('current_organization_id', $this->orgId)
                ->count();

        if ($movedCount !== count($moved)) {
            throw new \RuntimeException("Jumlah pindah ABIM tidak sepadan: {$movedCount} vs ".count($moved));
        }

        $userIds = array_merge($insertedIds, $movedIds);
        $expected = $this->baselineWadahCount + count($inserted) + count($moved);
        $actual = DB::table('users')
            ->where('current_organization_id', $this->orgId)
            ->whereNull('deleted_at')
            ->count();

        if ($actual !== $expected) {
            throw new \RuntimeException("Jumlah WADAH tidak sepadan: {$actual} vs {$expected}");
        }

        $feeCount = DB::table('membership_fees')
            ->whereIn('user_id', $userIds)
            ->where('year', 2026)
            ->count();

        if ($feeCount !== count($processed)) {
            throw new \RuntimeException("Jumlah fee tidak sepadan: {$feeCount} vs ".count($processed));
        }

        $roleCount = DB::table('model_has_roles')
            ->where('role_id', $this->memberRoleId)
            ->where('model_type', User::class)
            ->whereIn('model_id', $userIds)
            ->count();

        if ($roleCount !== count($processed)) {
            throw new \RuntimeException("Jumlah role tidak sepadan: {$roleCount} vs ".count($processed));
        }

        if ($insertedIds !== []) {
            $duplicateIc = DB::select(
                'SELECT ic_number, count(*) AS jumlah FROM users WHERE ic_number IS NOT NULL AND id IN ('
                .implode(',', array_map('intval', $insertedIds))
                .') GROUP BY ic_number HAVING count(*) > 1 LIMIT 5'
            );

            if ($duplicateIc !== []) {
                throw new \RuntimeException('Masih ada IC pendua dalam batch: '.json_encode($duplicateIc));
            }
        }

        $this->printSummary($inserted, $moved, $skipped);
    }

    private function printSummary(array $inserted, array $moved, array $skipped): void
    {
        $processed = array_merge($inserted, $moved);

        $lifetime = count(array_filter($processed, fn (array $record) => $record['lifetime']));
        $paid = count($processed) - $lifetime;
        $died = count(array_filter($processed, fn (array $record) => $record['died']));
        $placeholder = count(array_filter($inserted, fn (array $record) => $record['email_status'] === 'placeholder'));
        $invalidEmail = count(array_filter($inserted, fn (array $record) => $record['email_status'] === 'tidak_sah'));
        $blankIc = count(array_filter($inserted, fn (array $record) => $record['ic_status'] === 'kosong'));
        $invalidIc = count(array_filter($inserted, fn (array $record) => $record['ic_status'] === 'tidak_sah'));
        $manualIc = count(array_filter($inserted, fn (array $record) => $record['ic_status'] === 'manual_duplicate'));
        $okIc = count(array_filter($inserted, fn (array $record) => $record['ic_status'] === 'ok'));

        $branchCounts = [];
        foreach ($processed as $record) {
            $branchCounts[$record['branch_name']] = ($branchCounts[$record['branch_name']] ?? 0) + 1;
        }
        ksort($branchCounts);

        $skipReasons = [];
        foreach ($skipped as $record) {
            $skipReasons[$record['reason']] = ($skipReasons[$record['reason']] ?? 0) + 1;
        }
        ksort($skipReasons);

        $this->info($this->commit ? 'WADAH MASTER IMPORT — COMMIT' : 'WADAH MASTER IMPORT — DRY RUN');
        $this->table(['Item', 'Bilangan'], [
            ['Baris CSV', count($this->records)],
            ['Akan dimasukkan', count($inserted)],
            ['Dipindahkan ABIM→WADAH', count($moved)],
            ['Di-skip', count($skipped)],
            ['Yuran life_member', $lifetime],
            ['Yuran Paid RM0 2026', $paid],
            ['Badge Meninggal Dunia', $died],
            ['Email placeholder (baru)', $placeholder],
            ['Email tidak sah (baru)', $invalidEmail],
            ['IC 12 digit (baru)', $okIc],
            ['IC 12 digit (pindah)', count($moved)],
            ['IC kosong (NULL)', $blankIc],
            ['IC tidak sah (NULL)', $invalidIc],
            ['IC manual duplicate (NULL)', $manualIc],
            ['ID cawangan PUSAT', $this->pusatBranchId],
            ['ID position Meninggal Dunia', $this->deathPositionId],
        ]);

        $this->table(['Cawangan', 'Bilangan'], array_map(
            fn ($name, $count) => [$name, $count],
            array_keys($branchCounts),
            array_values($branchCounts)
        ));

        if ($skipReasons !== []) {
            $this->table(['Sebab skip', 'Bilangan'], array_map(
                fn ($reason, $count) => [$reason, $count],
                array_keys($skipReasons),
                array_values($skipReasons)
            ));
        }

        if ($moved !== []) {
            $this->table(['No Ahli', 'Nama', 'Member baru', 'ABIM lama'], array_map(
                fn (array $record) => [
                    $record['no_ahli'],
                    $record['name'],
                    $record['member_no'],
                    $record['move_from_member_no'],
                ],
                array_slice($moved, 0, 20)
            ));

            if (count($moved) > 20) {
                $this->line('... '.count($moved).' pindahan ABIM; senarai penuh dalam wadah_master_abim_moved.csv');
            }

            $this->writeMovedReport($moved);
        }

        $manual = array_values(array_filter(
            $inserted,
            fn (array $record) => $record['ic_status'] === 'manual_duplicate'
        ));

        if ($manual !== []) {
            $this->table(['No Ahli', 'Nama', 'IC asal', 'Sebab'], array_map(
                fn (array $record) => [$record['no_ahli'], $record['name'], $record['raw_ic'], $record['reason']],
                $manual
            ));
        }
    }

    private function writeMovedReport(array $moved): void
    {
        $path = storage_path('app/wadah-reconciliation/wadah_master_abim_moved.csv');
        File::ensureDirectoryExists(dirname($path));

        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Gagal buka fail pindahan ABIM: '.$path);
        }

        fputcsv($handle, ['no_ahli', 'member_no_baru', 'nama', 'ic_asal', 'abim_lama', 'org_lama']);

        foreach ($moved as $record) {
            fputcsv($handle, [
                $record['no_ahli'],
                $record['member_no'],
                $record['name'],
                $record['raw_ic'],
                $record['move_from_member_no'],
                $record['move_from_org'],
            ]);
        }

        fclose($handle);
    }

    private function writePreview(): void
    {
        $path = storage_path('app/wadah-reconciliation/wadah_master_dryrun_preview.csv');
        File::ensureDirectoryExists(dirname($path));

        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Gagal buka fail preview: '.$path);
        }

        fputcsv($handle, [
            'no_ahli',
            'member_no',
            'nama',
            'negeri',
            'cawangan',
            'ic_asal',
            'ic_bersih',
            'status_ic',
            'email',
            'status_email',
            'yuran_2026',
            'meninggal',
            'abim_lama',
            'tindakan',
            'sebab',
        ]);

        foreach ($this->records as $record) {
            fputcsv($handle, [
                $record['no_ahli'],
                $record['member_no'],
                $record['name'],
                $record['negeri'],
                $record['branch_name'],
                $record['raw_ic'],
                $record['ic'],
                $record['ic_status'],
                $record['email'],
                $record['email_status'],
                $record['fee_status'],
                $record['died'] ? 'ya' : 'tidak',
                $record['move_from_member_no'],
                $record['action'],
                $record['reason'],
            ]);
        }

        fclose($handle);

        $this->line('Preview: '.$path);
    }

    private function resolveBranch(string $negeri): array
    {
        $upper = strtoupper(trim($negeri));

        if ($upper === '' || $upper === 'TIADA NEGERI') {
            return [$this->pusatBranchId, 'PUSAT'];
        }

        $normalized = $this->normalizePlace($upper);

        if (str_starts_with($normalized, 'WILAYAH')) {
            foreach ($this->branchMap as $key => $branch) {
                if (str_starts_with($key, 'WILAYAH')) {
                    return [(int) $branch->id, (string) $branch->name];
                }
            }
        }

        if (isset($this->branchMap[$normalized])) {
            $branch = $this->branchMap[$normalized];

            return [(int) $branch->id, (string) $branch->name];
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($this->branchMap as $key => $branch) {
            similar_text($normalized, $key, $score);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $branch;
            }
        }

        if ($best !== null && $bestScore >= 80.0) {
            return [(int) $best->id, (string) $best->name];
        }

        return [null, ''];
    }

    private function normalizePlace(string $value): string
    {
        return (string) preg_replace('/[^A-Z]/', '', strtoupper(trim($value)));
    }

    private function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function cleanIc(?string $value): ?string
    {
        $raw = strtoupper(trim((string) $value));
        if ($raw === '') {
            return null;
        }

        $raw = str_replace(['-', ' '], '', $raw);

        if (preg_match('/^(\d{12})\.0$/', $raw, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^\d{11}\.0$/', $raw)) {
            return null;
        }

        $digits = (string) preg_replace('/\D/', '', $raw);

        return strlen($digits) === 12 ? $digits : null;
    }
}
