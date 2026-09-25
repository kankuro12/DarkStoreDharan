<?php

namespace App\Models;

use Awcodes\Curator\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group'];

    public static function get(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    public function getValueAttribute($value)
    {
        if ($this->type === 'image' && is_numeric($value)) {
            $media = Media::find($value);

            return $media ? $media->path : null;
        }

        return $value;
    }

    protected static function booted()
    {
        static::saved(function () {
            Cache::forget('store_settings');
        });
        static::deleted(function () {
            Cache::forget('store_settings');
        });
    }
}
