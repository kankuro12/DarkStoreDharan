<?php

namespace App\Models;

use Awcodes\Curator\Models\Media;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'brand_id',
        'category_id',
        'description',
        'image',
        'status',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->oldestOfMany();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getImageAttribute($value)
    {
        if (is_numeric($value)) {
            $media = Media::find($value);

            return $media ? asset('storage/'.$media->path) : null;
        }

        return $value ? (filter_var($value, FILTER_VALIDATE_URL) ? $value : asset('storage/'.$value)) : null;
    }
}
