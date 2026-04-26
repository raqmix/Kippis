<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComboItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'combo_product_id',
        'product_id',
        'quantity',
        'is_optional',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_optional' => 'boolean',
        ];
    }

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'combo_product_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
