<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductModifierGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'modifier_id',
        'is_required',
        'min_select',
        'max_select',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'min_select' => 'integer',
            'max_select' => 'integer',
        ];
    }

    /**
     * Get the product that owns this modifier group assignment.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the modifier assigned to this product.
     */
    public function modifier(): BelongsTo
    {
        return $this->belongsTo(Modifier::class);
    }
}

