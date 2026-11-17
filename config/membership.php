<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Membership Fee Start Year
    |--------------------------------------------------------------------------
    |
    | Fees only start being generated from this year onward. Any year before
    | this is treated as settled (no debt) for every member, including members
    | who register before the first fee year.
    |
    */

    'fee_start_year' => (int) env('MEMBERSHIP_FEE_START_YEAR', 2027),

];
