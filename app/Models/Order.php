<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class Order extends Model
{
    use HasFactory;

    /**
     * Statuses the order lifecycle recognises.
     */
    public const STATUSES = [
        'pending',
        'paid',
        'processing',
        'shipped',
        'completed',
        'cancelled',
    ];

    /**
     * Statuses that mean the goods are no longer reserved for this order.
     */
    public const STOCK_RELEASING_STATUSES = ['cancelled'];

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

    /**
     * Tamper-proof receipt link for guests.
     *
     * Order IDs are sequential, so an unsigned public URL lets anyone walk the
     * range and read every buyer's name, phone and address. Signing the link
     * keeps guest access working while making enumeration useless.
     */
    public function publicUrl(): string
    {
        return URL::signedRoute('mall.order.show', ['order' => $this->id]);
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, self::STOCK_RELEASING_STATUSES, true);
    }
}
