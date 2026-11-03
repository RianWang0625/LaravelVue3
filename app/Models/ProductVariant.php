<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    // 商品規格可批次寫入的欄位
    protected $fillable = [
        'product_id',
        'sku',
        'options',
        'price',
        'stock',
        'is_active',
    ];

    // 商品規格資料型別
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'price' => 'decimal:2',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // 商品規格所屬商品
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
