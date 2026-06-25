<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ImportAbim extends Command
{
    protected $signature = 'import:abim {file? : Path ke CSV ABIM}';

    protected $description = 'Import ahli ABIM dari CSV (format laporan ABIM)';

    public function handle()
    {
        $file = $this->argument('file')
            ?? database_path('seeders/data/SENARAI NAMA MYWAP(Laporan Ahli).csv');

        if (! file_exists($file)) {
            $this->error("File tak jumpa: {$file}");
            return 1;
        }

        $abim = Organization::where('slug', 'abim')->first();
        if (! $abim) {
            $this->error('Organisasi ABIM tak jumpa');
            return 1;
        }

        $memberRole = \Spatie\Permission\Models\Role::where('name', 'Member')->first();
        if (! $memberRole) {
            $this->error('Role Member tak jumpa, jalankan db:seed dulu');
            return 1;
        }

        // ── Parse CSV (single pass) ────────────────────────────────────
        $this->line('📄 Membaca & memproses CSV...');
        $parsed = $this->parseAll($file);
        if (empty($parsed['rows'])) {
            $this->error('CSV kosong atau gagal parse');
            return 1;
        }

        $this->line("📊 {$parsed['rows']} rekod, {$parsed['branchNames']->count()} cawangan unik");

        // ── Create branches ─────────────────────────────────────────────
        $this->line('🏢 Create cawangan...');
        $branchMap = [];
        foreach ($parsed['branchNames'] as $name) {
            $branch = Branch::firstOrCreate(
                ['organization_id' => $abim->id, 'name' => $name],
                ['state' => $name, 'is_active' => true]
            );
            $branchMap[$name] = $branch->id;
        }
        $this->line('✅ ' . count($branchMap) . ' cawangan sedia');

        // ── Build records dengan branch_id ──────────────────────────────
        $this->line('🔍 Memeriksa duplikasi IC...');
        $existingIcs = User::withoutGlobalScopes()
            ->where('ic_number', '!=', '')
            ->pluck('id', 'ic_number')
            ->toArray();

        $defaultPw = Hash::make('password123');
        $now = now();
        $toInsert = [];
        $toUpdate = [];

        foreach ($parsed['records'] as $r) {
            $branchId = $r['branchName'] ? ($branchMap[$r['branchName']] ?? null) : null;

            $record = [
                'name'                    => $r['name'],
                'member_no'               => $r['member_no'],
                'member_no_sequence'      => $r['member_no_seq'],
                'password'                => $defaultPw,
                'email'                   => $r['email'],
                'phone'                   => $r['phone'],
                'dob'                     => $r['dob'],
                'gender'                  => $r['gender'],
                'address_1'               => $r['address_1'],
                'state'                   => $r['state'],
                'branch_id'               => $branchId,
                'registration_year'       => $r['reg_year'],
                'notes'                   => $r['notes'],
                'current_organization_id' => $abim->id,
                'ic_number'               => $r['ic'],
            ];

            $exists = isset($existingIcs[$r['ic']]);
            if ($exists) {
                $record['id'] = $existingIcs[$r['ic']];
                $toUpdate[] = $record;
            } else {
                $record['created_at'] = $now;
                $record['updated_at'] = $now;
                $toInsert[] = $record;
            }
        }

        $this->line("➡️  Baru: " . count($toInsert) . " | Sedia: " . count($toUpdate));

        // ── Bulk insert ─────────────────────────────────────────────────
        $insertedCount = 0;
        $newIds = [];
        if (! empty($toInsert)) {
            $this->line('⚡ Insert ahli baru...');
            $dupSkipped = 0;
            foreach (array_chunk($toInsert, 500) as $chunk) {
                foreach ($chunk as &$rec) unset($rec['id']);
                $inserted = DB::table('users')->insertOrIgnore($chunk);
                $insertedCount += count($chunk);
            }
            // Kira sebenar inserted — banding IC yang wujud sekarang
            $ics = array_column($toInsert, 'ic_number');
            $actualNew = User::withoutGlobalScopes()
                ->whereIn('ic_number', $ics)
                ->count();
            $dupSkipped = count($toInsert) - $actualNew;
            $insertedCount = $actualNew;

            $newUsers = User::withoutGlobalScopes()
                ->whereIn('ic_number', $ics)
                ->get(['id', 'ic_number', 'member_no']);
            $newIds = $newUsers->pluck('id')->toArray();

            foreach (array_chunk($newUsers->toArray(), 500) as $chunk) {
                $ids = array_column($chunk, 'id');
                DB::table('users')
                    ->whereIn('id', $ids)
                    ->whereNull('original_member_no')
                    ->update(['original_member_no' => DB::raw('member_no')]);
            }

            $msg = "✅ {$insertedCount} ahli baru";
            if ($dupSkipped) $msg .= " ({$dupSkipped} duplikasi IC dilewati)";
            $this->line($msg);
        }

        // ── Bulk update ─────────────────────────────────────────────────
        if (! empty($toUpdate)) {
            $this->line('⚡ Update ahli sedia ada...');
            foreach (array_chunk($toUpdate, 500) as $chunk) {
                $now = now();
                foreach ($chunk as $rec) {
                    $id = $rec['id'];
                    unset($rec['id'], $rec['created_at']);
                    $rec['updated_at'] = $now;
                    DB::table('users')->where('id', $id)->update($rec);
                }
            }
            $this->line("✅ " . count($toUpdate) . " ahli dikemaskini");
        }

        // ── Role ────────────────────────────────────────────────────────
        $allIds = array_merge($newIds, array_column($toUpdate, 'id'));
        if (! empty($allIds)) {
            $existingRole = DB::table('model_has_roles')
                ->where('role_id', $memberRole->id)
                ->where('model_type', User::class)
                ->whereIn('model_id', $allIds)
                ->pluck('model_id')
                ->toArray();

            $needRole = array_diff($allIds, $existingRole);
            if (! empty($needRole)) {
                $roleInserts = array_map(fn($uid) => [
                    'role_id' => $memberRole->id, 'model_type' => User::class, 'model_id' => $uid,
                ], $needRole);
                foreach (array_chunk($roleInserts, 500) as $chunk) {
                    DB::table('model_has_roles')->insertOrIgnore($chunk);
                }
                $this->line("✅ Role Member → " . count($needRole) . " ahli");
            }
        }

        $this->newLine();
        $this->info("🎉 Selesai! " . ($insertedCount + count($toUpdate)) . " ahli ABIM diproses");
        if (! empty($parsed['errors'])) {
            $this->warn('⚠️  ' . count($parsed['errors']) . ' rekod dilewati:');
            foreach ($parsed['errors'] as $e) $this->warn("  • {$e}");
        }

        return 0;
    }

    protected function parseAll(string $file): array
    {
        $handle = fopen($file, 'r');
        if (! $handle) return ['rows' => 0, 'branchNames' => collect(), 'records' => [], 'errors' => []];

        $headers = fgetcsv($handle);
        if (! $headers) { fclose($handle); return ['rows' => 0, 'branchNames' => collect(), 'records' => [], 'errors' => []]; }

        $col = array_flip($headers);
        $branchNames = collect();
        $records = [];
        $errors = [];
        $count = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($headers)) continue;

            $nama = $this->clean(trim($row[$col['NAMA']] ?? ''));
            $mykadRaw = $this->clean(trim($row[$col['MYKAD']] ?? ''));
            $noRuj = $this->clean(trim($row[$col['NO.RUJ']] ?? ''));

            $ic = ltrim($mykadRaw, "'");
            $ic = preg_replace('/[^0-9]/', '', $ic);

            if (empty($nama) && empty($ic)) continue;
            if (empty($ic) || strlen($ic) < 6) {
                $errors[] = "{$nama} — IC {$mykadRaw} tak sah";
                continue;
            }

            $count++;
            $icPadded = str_pad($ic, 12, '0', STR_PAD_RIGHT);

            $branchName = $this->extractBranchName($this->clean(trim($row[$col['LOKALITI'] ?? ''] ?? '')));
            if ($branchName) $branchNames->push($branchName);

            $records[] = [
                'name'        => $nama,
                'ic'          => $icPadded,
                'member_no'   => 'A' . $noRuj,
                'member_no_seq' => (int) $noRuj,
                'email'       => $this->val($row, $col, 'EMEL', strtolower($ic) . '@mywap.my'),
                'phone'       => ltrim($this->val($row, $col, 'MOBILE NO', ''), "'") ?: null,
                'dob'         => User::parseDobFromIc($ic),
                'gender'      => User::guessGenderFromIc($ic),
                'address_1'   => $this->cleanAddress($this->val($row, $col, 'ALAMAT', '')),
                'state'       => $branchName ? $this->normalizeState($branchName) : null,
                'branchName'  => $branchName,
                'reg_year'    => $this->parseRegYear($this->val($row, $col, 'TARIKH DAFTAR', '')),
                'notes'       => $this->wrapNotes($this->val($row, $col, 'PERANAN', '')),
            ];
        }

        fclose($handle);

        return [
            'rows' => $count,
            'branchNames' => $branchNames->unique()->sort()->values(),
            'records' => $records,
            'errors' => $errors,
        ];
    }

    protected function clean(string $str): string
    {
        return mb_convert_encoding($str, 'UTF-8', 'Windows-1252');
    }

    protected function extractBranchName(string $lokality): ?string
    {
        if (empty($lokality)) return null;
        if (preg_match('/NEGERI\s+(.+?)(?:,|$)/i', $lokality, $m)) {
            return ucwords(strtolower(trim($m[1])));
        }
        return 'Pusat';
    }

    protected function normalizeState(string $raw): string
    {
        $map = ['K.Lumpur' => 'Kuala Lumpur', 'N.Sembilan' => 'Negeri Sembilan', 'P.Pinang' => 'Pulau Pinang'];
        return $map[$raw] ?? $raw;
    }

    protected function val(array $row, array $col, string $key, string $default = ''): string
    {
        $v = isset($col[$key]) ? trim($row[$col[$key]] ?? '') : '';
        $clean = $v !== '' ? $this->clean($v) : '';
        return $clean !== '' ? $clean : $default;
    }

    protected function cleanAddress(string $v): ?string
    {
        return (! empty($v) && ! in_array($v, ['-', '#N/A', ' - '])) ? $v : null;
    }

    protected function parseRegYear(string $v): ?string
    {
        if (empty($v)) return null;
        $parts = explode('/', $v);
        return count($parts) === 3 ? $parts[2] : null;
    }

    protected function wrapNotes(string $v): ?string
    {
        return ! empty($v) ? "PERANAN: {$v}" : null;
    }
}
