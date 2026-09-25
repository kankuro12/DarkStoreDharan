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
        'gallery',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
        ];
    }

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
        return $this->resolveMediaUrl($value);
    }

    /**
     * All gallery images, main image first, for the storefront product gallery.
     *
     * @return array<int, string>
     */
    public function getGalleryImagesAttribute(): array
    {
        $urls = collect($this->gallery ?? [])
            ->map(fn ($ref) => $this->resolveMediaUrl($ref))
            ->filter()
            ->values()
            ->all();

        $mainImage = $this->image;
        if ($mainImage && ! in_array($mainImage, $urls, true)) {
            array_unshift($urls, $mainImage);
        }

        return $urls ?: array_filter([$mainImage]);
    }

    protected function resolveMediaUrl($value): ?string
    {
        if (is_numeric($value)) {
            $media = Media::find($value);

            return $media ? asset('storage/'.$media->path) : null;
        }

        return $value ? (filter_var($value, FILTER_VALIDATE_URL) ? $value : asset('storage/'.$value)) : null;
    }
}
