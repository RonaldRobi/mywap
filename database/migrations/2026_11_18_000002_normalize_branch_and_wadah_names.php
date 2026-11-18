<?php

use App\Support\Malaysia;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('branches')
            ->select(['id', 'name', 'state'])
            ->orderBy('id')
            ->chunkById(500, function ($branches): void {
                foreach ($branches as $branch) {
                    $name = Malaysia::titleCase($branch->name);
                    $state = Malaysia::titleCase($branch->state);

                    if ($name === $branch->name && $state === $branch->state) {
                        continue;
                    }

                    DB::table('branches')->where('id', $branch->id)->update([
                        'name' => $name,
                        'state' => $state,
                    ]);
                }
            });

        DB::table('organizations')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->chunkById(500, function ($organizations): void {
                foreach ($organizations as $organization) {
                    $name = Malaysia::titleCase($organization->name);

                    if ($name === $organization->name) {
                        continue;
                    }

                    DB::table('organizations')->where('id', $organization->id)->update([
                        'name' => $name,
                    ]);
                }
            });

        DB::table('users')
            ->select(['id', 'wadah_state', 'branch_name'])
            ->where(function ($query): void {
                $query->whereNotNull('wadah_state')->orWhereNotNull('branch_name');
            })
            ->orderBy('id')
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    $state = Malaysia::titleCase($user->wadah_state);
                    $branchName = Malaysia::titleCase($user->branch_name);

                    if ($state === $user->wadah_state && $branchName === $user->branch_name) {
                        continue;
                    }

                    DB::table('users')->where('id', $user->id)->update([
                        'wadah_state' => $state,
                        'branch_name' => $branchName,
                    ]);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
