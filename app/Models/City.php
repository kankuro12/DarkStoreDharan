<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'province',
        'status',
        'cod_enabled',
        'prepaid_enabled',
        'default_delivery_fee',
        'free_delivery_minimum',
        'estimated_delivery_minutes',
        'banners',
        'featured_products',
    ];

    protected function casts(): array
    {
        return [
            'cod_enabled' => 'boolean',
            'prepaid_enabled' => 'boolean',
            'default_delivery_fee' => 'decimal:2',
            'free_delivery_minimum' => 'decimal:2',
            'estimated_delivery_minutes' => 'integer',
            'banners' => 'array',
            'featured_products' => 'array',
        ];
    }

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'warehouse_city')
            ->withPivot(['id', 'priority', 'delivery_minutes', 'delivery_fee', 'status'])
            ->withTimestamps()
            ->orderByPivot('priority', 'asc');
    }

    public function activeWarehouses(): BelongsToMany
    {
        return $this->warehouses()
            ->wherePivot('status', 'active')
            ->where('warehouses.status', 'active');
    }

    public function deliveryZones(): HasMany
    {
        return $this->hasMany(DeliveryZone::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function deliveryAgents(): HasMany
    {
        return $this->hasMany(DeliveryAgent::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
