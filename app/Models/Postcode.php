<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Postcode extends Model
{
    protected $fillable = [
        'postcode',
        'city',
        'state',
        'country',
    ];

    public $incrementing = false;

    protected $primaryKey = 'postcode';

    protected $keyType = 'string';
}
