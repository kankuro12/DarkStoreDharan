<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreeDeliveryRule extends Model
{
    protected $fillable = [
        'name',
        'city_id',
        'category_id',
        'product_id',
        'min_subtotal',
        'min_quantity',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'min_subtotal' => 'decimal:2',
            'min_quantity' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Whether this rule applies to the given cart: right city (or every city, when city_id is
     * null), and the product/category/subtotal condition it defines is satisfied.
     *
     * @param  array<int, array{product_id: int, category_id: ?int, quantity: int}>  $cartItems
     */
    public function matches(int $cityId, array $cartItems, float $subtotal): bool
    {
        if ($this->city_id !== null && $this->city_id !== $cityId) {
            return false;
        }

        $requiredQuantity = $this->min_quantity ?? 1;

        if ($this->product_id) {
            $matchedQuantity = collect($cartItems)
                ->where('product_id', $this->product_id)
                ->sum('quantity');

            if ($matchedQuantity < $requiredQuantity) {
                return false;
            }
        } elseif ($this->category_id) {
            // A parent category also covers products in its subcategories.
            $categoryIds = Category::find($this->category_id)?->descendantIds() ?? [$this->category_id];

            $matchedQuantity = collect($cartItems)
                ->whereIn('category_id', $categoryIds)
                ->sum('quantity');

            if ($matchedQuantity < $requiredQuantity) {
                return false;
            }
        }

        if ($this->min_subtotal !== null && $subtotal < (float) $this->min_subtotal) {
            return false;
        }

        // A rule with no product/category/subtotal condition at all is misconfigured; never match it.
        if (! $this->product_id && ! $this->category_id && $this->min_subtotal === null) {
            return false;
        }

        return true;
    }
}
