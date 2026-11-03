<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    // 商品可批次寫入的欄位
    protected $fillable = [
        'site_id',
        'name',
        'description',
        'price',
        'stock',
        'image',
        'is_active',
    ];

    // 商品所屬網站
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    // 商品多規格關聯
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}
