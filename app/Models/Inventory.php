<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'product_variant_id',
        'quantity',
        'reserved_quantity',
        'reorder_level',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'reorder_level' => 'integer',
        ];
    }

    protected $appends = [
        'available',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Calculated available stock (quantity - reserved_quantity)
     */
    protected function available(): Attribute
    {
        return Attribute::make(
            get: fn () => max(0, (int) $this->quantity - (int) $this->reserved_quantity)
        );
    }

    public function isLowStock(): bool
    {
        return $this->available <= $this->reorder_level;
    }
}
