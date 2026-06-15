<?php

namespace App\Enums;

enum FeeStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Exempted = 'exempted';
    case LifeMember = 'life_member';
}
