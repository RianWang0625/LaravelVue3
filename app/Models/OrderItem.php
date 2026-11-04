<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    // 設定可批次寫入欄位
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'variant_options',
        'unit_price',
        'quantity',
        'subtotal',
    ];

    // 設定規格及金額型別
    protected function casts(): array
    {
        return [
            'variant_options' => 'array',
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'subtotal' => 'decimal:2',
        ];
    }

    // 訂單明細所屬訂單
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // 訂單明細對應商品
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // 訂單明細對應商品規格
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
