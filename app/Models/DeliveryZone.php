<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
