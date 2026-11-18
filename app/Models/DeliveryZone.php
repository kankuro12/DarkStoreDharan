<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class DeliveryZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'city_id',
        'name',
        'postal_code',
        'latitude',
        'longitude',
        'radius_km',
        'delivery_fee',
        'minimum_order',
        'estimated_minutes',
        'cod_enabled',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'radius_km' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'minimum_order' => 'decimal:2',
            'estimated_minutes' => 'integer',
            'cod_enabled' => 'boolean',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Area / Chowk / Ward for a new address. When the city has admin-preset areas the customer
     * must pick one (typed text is ignored); cities with none yet still accept typed text.
     *
     * @return array{delivery_zone_id: ?int, area: string}
     *
     * @throws ValidationException
     */
    public static function resolveAddressArea(int $cityId, mixed $zoneId, ?string $typedArea): array
    {
        if (! static::active()->where('city_id', $cityId)->exists()) {
            if (blank($typedArea)) {
                throw ValidationException::withMessages(['area' => 'Please enter your area, chowk or ward.']);
            }

            return ['delivery_zone_id' => null, 'area' => trim($typedArea)];
        }

        $zone = $zoneId ? static::active()->where('city_id', $cityId)->find($zoneId) : null;

        if (! $zone) {
            throw ValidationException::withMessages(['delivery_zone_id' => 'Please choose your area, chowk or ward from the list.']);
        }

        return ['delivery_zone_id' => $zone->id, 'area' => $zone->name];
    }
}
