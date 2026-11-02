<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'site_id',
        'name',
        'description',
        'price',
        'stock',
        'image',
        'is_active',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
