<?php

namespace App\Console\Commands\Traits;

use App\Models\Organization;

trait MemberNoPrefix
{
    public function prefixForOrg($org): ?string
    {
        return match ($org?->slug) {
            'pkpim' => 'P',
            'abim' => 'A',
            'wadah' => 'W',
            default => null,
        };
    }

    public function orgSlugForId(int $orgId): ?string
    {
        return Organization::find($orgId)?->slug;
    }
}
