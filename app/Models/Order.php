<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    // 設定可批次寫入欄位
    protected $fillable = [
        'site_id',
        'order_number',
        'customer_name',
        'customer_phone',
        'customer_email',
        'recipient_name',
        'recipient_phone',
        'shipping_address',
        'shipping_method',
        'shipping_company',
        'subtotal',
        'shipping_fee',
        'discount_amount',
        'total',
        'payment_method',
        'payment_status',
        'order_status',
        'customer_note',
        'tracking_number',
        'shipped_at',
        'access_token',
        'checkout_token',
    ];

    // 設定欄位型別
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'shipped_at' => 'datetime',
        ];
    }

    // 訂單所屬網站
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    // 訂單商品明細
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
