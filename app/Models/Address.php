<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'city_id',
        'delivery_zone_id',
        'full_name',
        'phone',
        'area',
        'street',
        'landmark',
        'latitude',
        'longitude',
        'delivery_notes',
    ];

    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'latitude' => 'encrypted',
            'longitude' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function getFormattedAddressAttribute(): string
    {
        $parts = array_filter([$this->street, $this->area, $this->landmark, $this->city?->name]);

        return implode(', ', $parts);
    }
}
