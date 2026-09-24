<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'city_id',
        'discount_type',
        'discount_value',
        'minimum_order',
        'max_discount',
        'starts_at',
        'expires_at',
        'usage_limit',
        'used_count',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'minimum_order' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function isValidFor(?int $cityId, float $subtotal): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at && now()->gt($this->expires_at)) {
            return false;
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        if ($this->city_id !== null && $cityId !== null && (int) $this->city_id !== (int) $cityId) {
            return false;
        }

        if ($subtotal < (float) $this->minimum_order) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(float $subtotal): float
    {
        if ($this->discount_type === 'percent') {
            $discount = ($subtotal * (float) $this->discount_value) / 100.0;
            if ($this->max_discount !== null) {
                $discount = min($discount, (float) $this->max_discount);
            }

            return round($discount, 2);
        }

        return min((float) $this->discount_value, $subtotal);
    }
}
