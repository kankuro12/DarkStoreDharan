<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'name',
        'price',
        'weight_kg',
        'cod_allowed',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'weight_kg' => 'decimal:3',
            'cod_allowed' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    /**
     * Resolve effective price for a city/warehouse.
     */
    public function getPriceForCity(?int $cityId = null, ?int $warehouseId = null): array
    {
        $query = $this->prices();

        if ($warehouseId) {
            $warehousePrice = (clone $query)->where('warehouse_id', $warehouseId)
                ->where(function ($q) {
                    $q->whereNull('start_at')->orWhere('start_at', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('end_at')->orWhere('end_at', '>=', now());
                })
                ->first();

            if ($warehousePrice) {
                return [
                    'price' => (float) ($warehousePrice->sale_price ?? $warehousePrice->price),
                    'regular_price' => (float) $warehousePrice->price,
                    'is_sale' => $warehousePrice->sale_price !== null,
                ];
            }
        }

        if ($cityId) {
            $cityPrice = (clone $query)->where('city_id', $cityId)
                ->where(function ($q) {
                    $q->whereNull('start_at')->orWhere('start_at', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('end_at')->orWhere('end_at', '>=', now());
                })
                ->first();

            if ($cityPrice) {
                return [
                    'price' => (float) ($cityPrice->sale_price ?? $cityPrice->price),
                    'regular_price' => (float) $cityPrice->price,
                    'is_sale' => $cityPrice->sale_price !== null,
                ];
            }
        }

        return [
            'price' => (float) $this->price,
            'regular_price' => (float) $this->price,
            'is_sale' => false,
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
