<?php

namespace App\Enums;

enum EventVisibility: string
{
    case BranchOnly = 'branch_only';
    case Open = 'open';

    public function label(): string
    {
        return match ($this) {
            self::BranchOnly => 'Ahli cawangan sahaja',
            self::Open => 'Terbuka kepada semua',
        };
    }
}
