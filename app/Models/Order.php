<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'organisasi_id',
        'total',
        'postage_cost',
        'status',
        'tracking_no',
        'shipping_address',
        'shipping_postcode',
        'shipping_phone',
        'shipping_name',
        'courier',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organisasi_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'payable_id')->where('payable_type', 'order');
    }
}
