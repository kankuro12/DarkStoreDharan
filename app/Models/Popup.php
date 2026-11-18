<?php

namespace App\Models;

use Awcodes\Curator\Models\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Storefront announcement popup, shown on every page, the home page, product pages or a given URL path.
 */
class Popup extends Model
{
    public const PLACEMENTS = [
        'all' => 'Every storefront page',
        'home' => 'Home page only',
        'product' => 'Product page(s)',
        'path' => 'Specific page (URL path)',
    ];

    public const FREQUENCIES = [
        'once' => 'Once per visitor (until edited)',
        'session' => 'Once per browser session',
        'always' => 'Every page load',
    ];

    /** Most specific placement wins when several popups match a page. */
    private const SPECIFICITY = ['product' => 0, 'path' => 1, 'home' => 2, 'all' => 3];

    protected $fillable = [
        'title', 'body', 'image', 'button_label', 'button_url', 'placement',
        'product_id', 'target_path', 'frequency', 'is_active', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /** The single popup to show for this storefront request, if any. */
    public static function forRequest(Request $request): ?self
    {
        $path = trim($request->path(), '/');
        $isHome = $request->routeIs('storefront.home');
        $productId = $request->routeIs('storefront.product')
            ? Product::where('slug', $request->route('slug'))->value('id')
            : null;

        return static::query()->live()->get()
            ->filter(fn (self $popup) => match ($popup->placement) {
                'all' => true,
                'home' => $isHome,
                'product' => $productId !== null && ($popup->product_id === null || $popup->product_id === $productId),
                'path' => $popup->target_path !== null && Str::is(trim($popup->target_path, '/') ?: '/', $path),
                default => false,
            })
            ->sortBy(fn (self $popup) => [self::SPECIFICITY[$popup->placement] ?? 9, -$popup->id])
            ->first();
    }

    public function imageUrl(): ?string
    {
        if (! $this->image) {
            return null;
        }

        if (is_numeric($this->image)) {
            // Curator media: let the media resolve its own (disk-aware) URL.
            return Media::find($this->image)?->url;
        }

        return Storage::disk('public')->url($this->image);
    }

    /** Changes when the popup is edited, so "once" popups show again after an update. */
    public function storageKey(): string
    {
        return 'popup-'.$this->id.'-'.$this->updated_at?->timestamp;
    }
}
