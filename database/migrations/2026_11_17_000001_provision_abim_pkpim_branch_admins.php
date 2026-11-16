<?php

use App\Models\Branch;
use App\Services\BranchAdminProvisioner;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $provisioner = app(BranchAdminProvisioner::class);

        Branch::query()
            ->with('organization')
            ->whereHas('organization', fn ($query) => $query->whereIn('slug', ['abim', 'pkpim']))
            ->orderBy('id')
            ->each(fn (Branch $branch) => $provisioner->provision($branch));
    }

    public function down(): void
    {
        // Automatically provisioned accounts may already have been used.
    }
};
