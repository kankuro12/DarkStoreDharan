<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

class InventoryMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'warehouse_id',
        'product_variant_id',
        'type',
        'quantity',
        'balance_after',
        'reference_type',
        'reference_id',
        'reference_code',
        'reason',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => InventoryMovementType::class,
            'quantity' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The ledger is an append-only record of truth: entries are written by stock
     * flows only and can never be edited or deleted, not even by a developer.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Inventory ledger entries are immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Inventory ledger entries are immutable and cannot be deleted.');
        });
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForWarehouse(Builder $query, ?int $warehouseId): Builder
    {
        return $query->when($warehouseId, fn (Builder $q) => $q->where('warehouse_id', $warehouseId));
    }

    public function scopeForVariant(Builder $query, int $variantId): Builder
    {
        return $query->where('product_variant_id', $variantId);
    }
}
