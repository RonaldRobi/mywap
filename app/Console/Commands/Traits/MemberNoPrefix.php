<?php

namespace App\Console\Commands\Traits;

trait MemberNoPrefix
{
    public function prefixForOrg($org): ?string
    {
        return match ($org?->slug) {
            'pkpim' => 'P',
            'abim'  => 'A',
            'wadah' => 'W',
            default => null,
        };
    }

    public function orgSlugForId(int $orgId): ?string
    {
        return \App\Models\Organization::find($orgId)?->slug;
    }
}
