<?php

namespace App\Models;

use Awcodes\Curator\Models\Media;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'status',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getLogoAttribute($value)
    {
        if (is_numeric($value)) {
            $media = Media::find($value);

            return $media ? asset('storage/'.$media->path) : null;
        }

        return $value ? (filter_var($value, FILTER_VALIDATE_URL) ? $value : asset('storage/'.$value)) : null;
    }
}
