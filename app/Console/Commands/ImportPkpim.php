<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ImportPkpim extends Command
{
    protected $signature = 'import:pkpim {file? : Path ke CSV PKPIM}';

    protected $description = 'Import ahli PKPIM dari CSV';

    public function handle()
    {
        $file = $this->argument('file')
            ?? database_path('seeders/data/pkpim.csv');

        if (! file_exists($file)) {
            $this->error("File tak jumpa: {$file}");
            return 1;
        }

        $pkpim = Organization::where('slug', 'pkpim')->first();
        if (! $pkpim) {
            $this->error('Organisasi PKPIM tak jumpa');
            return 1;
        }

        $memberRole = \Spatie\Permission\Models\Role::where('name', 'Member')->first();
        if (! $memberRole) {
            $this->error('Role Member tak jumpa, jalankan db:seed dulu');
            return 1;
        }

        // ── Parse + proses (single pass) ───────────────────────────────
        $this->line('📄 Membaca & memproses CSV...');
        $parsed = $this->parseAll($file);
        if (empty($parsed['records'])) {
            $this->error('CSV kosong atau gagal parse');
            return 1;
        }

        $this->line("📊 {$parsed['rows']} rekod, {$parsed['branchNames']->count()} cawangan unik");
        if (! empty($parsed['errors'])) {
            $this->warn('⚠️  ' . count($parsed['errors']) . ' rekod dilewati (IC tak sah)');
        }

        // ── Cari max sequence no ahli P─ ────────────────────────────────
        $maxSeq = User::withoutGlobalScopes()
            ->where('member_no', 'like', 'P%')
            ->max('member_no_sequence') ?? 0;

        // ── Create branches ─────────────────────────────────────────────
        $this->line('🏢 Create cawangan...');
        $branchMap = [];
        foreach ($parsed['branchNames'] as $name) {
            $branch = Branch::firstOrCreate(
                ['organization_id' => $pkpim->id, 'name' => $name],
                ['state' => $name !== 'Pusat' ? $name : null, 'is_active' => true]
            );
            $branchMap[$name] = $branch->id;
        }
        $this->line('✅ ' . count($branchMap) . ' cawangan sedia');

        // ── Build records ───────────────────────────────────────────────
        $this->line('🔍 Memeriksa duplikasi IC...');
        $existingIcs = User::withoutGlobalScopes()
            ->where('ic_number', '!=', '')
            ->pluck('id', 'ic_number')
            ->toArray();

        $defaultPw = Hash::make('password123');
        $now = now();
        $toInsert = [];
        $toUpdate = [];
        $seq = $maxSeq;

        foreach ($parsed['records'] as $r) {
            $seq++;
            $memberNo = 'P' . str_pad($seq, 5, '0', STR_PAD_LEFT);
            $branchId = $r['branchName'] ? ($branchMap[$r['branchName']] ?? null) : null;

            $record = [
                'name'                    => $r['name'],
                'member_no'               => $memberNo,
                'member_no_sequence'      => $seq,
                'password'                => $defaultPw,
                'email'                   => $r['email'],
                'phone'                   => $r['phone'],
                'dob'                     => $r['dob'],
                'gender'                  => $r['gender'],
                'branch_id'               => $branchId,
                'notes'                   => $r['notes'],
                'current_organization_id' => $pkpim->id,
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
            foreach (array_chunk($toInsert, 500) as $chunk) {
                foreach ($chunk as &$rec) unset($rec['id']);
                DB::table('users')->insert($chunk);
                $insertedCount += count($chunk);
            }

            $ics = array_column($toInsert, 'ic_number');
            $newUsers = User::withoutGlobalScopes()
                ->whereIn('ic_number', $ics)
                ->get(['id', 'ic_number']);
            $newIds = $newUsers->pluck('id')->toArray();

            foreach (array_chunk($newUsers->toArray(), 500) as $chunk) {
                $ids = array_column($chunk, 'id');
                DB::table('users')
                    ->whereIn('id', $ids)
                    ->whereNull('original_member_no')
                    ->update(['original_member_no' => DB::raw('member_no')]);
            }
            $this->line("✅ {$insertedCount} ahli baru");
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
        $this->info("🎉 Selesai! " . ($insertedCount + count($toUpdate)) . " ahli PKPIM diproses");
        if (! empty($parsed['errors'])) {
            $this->warn('⚠️  Rekod dilewati (IC tak sah — sila semak & upload semula):');
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

        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        $col = array_flip($headers);

        if (! isset($col['FullName'], $col['icNumber'])) {
            fclose($handle);
            return ['rows' => 0, 'branchNames' => collect(), 'records' => [], 'errors' => []];
        }

        $branchNames = collect();
        $records = [];
        $errors = [];
        $count = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($headers)) continue;

            $name = $this->clean(trim($row[$col['FullName']] ?? ''));
            $icRaw = $this->clean(trim($row[$col['icNumber']] ?? ''));
            $digits = preg_replace('/[^0-9]/', '', $icRaw);

            if (empty($name) && empty($digits)) continue;

            if (strlen($digits) < 11) {
                $persatuan = $this->clean(trim($row[$col['Persatuan'] ?? ''] ?? ''));
                $errors[] = "{$name} — IC=\"{$icRaw}\" (no telefon?), Persatuan: {$persatuan}";
                continue;
            }

            $count++;
            $icPadded = str_pad($digits, 12, '0', STR_PAD_RIGHT);

            $persatuan = $this->clean(trim($row[$col['Persatuan'] ?? ''] ?? ''));
            $branchName = ! empty($persatuan) ? $persatuan : 'Pusat';
            $branchNames->push($branchName);

            $email = $this->clean(trim($row[$col['email'] ?? ''] ?? ''));
            if (empty($email)) $email = strtolower($digits) . '@mywap.my';

            $phone = ltrim($this->clean(trim($row[$col['phoneNo'] ?? ''] ?? '')), "'");

            $records[] = [
                'name'       => $name,
                'ic'         => $icPadded,
                'email'      => $email,
                'phone'      => $phone ?: null,
                'dob'        => User::parseDobFromIc($digits),
                'gender'     => User::guessGenderFromIc($digits),
                'branchName' => $branchName,
                'notes'      => $branchName !== 'Pusat' ? "Persatuan: {$branchName}" : null,
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
}
