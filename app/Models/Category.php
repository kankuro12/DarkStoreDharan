<?php

namespace App\Models;

use Awcodes\Curator\Models\Media;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'image',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Root-first chain of ancestors (empty for top-level categories).
     */
    public function ancestors(): Collection
    {
        $chain = collect();
        $seen = [];
        $node = $this->parent;

        while ($node && ! in_array($node->getKey(), $seen, true)) {
            $seen[] = $node->getKey();
            $chain->prepend($node);
            $node = $node->parent;
        }

        return $chain;
    }

    /**
     * Own id plus every descendant id, cycle-safe.
     *
     * @return array<int>
     */
    public function descendantIds(): array
    {
        $ids = [$id = (int) $this->getKey()];
        $seen = [$id => true];
        $queue = [$id];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach (static::where('parent_id', $current)->pluck('id') as $childId) {
                $childId = (int) $childId;

                if (isset($seen[$childId])) {
                    continue;
                }

                $seen[$childId] = true;
                $ids[] = $childId;
                $queue[] = $childId;
            }
        }

        return $ids;
    }

    public function getDepthAttribute(): int
    {
        return $this->ancestors()->count();
    }

    public function getPathAttribute(): string
    {
        return $this->ancestors()->pluck('name')->push($this->name)->implode(' › ');
    }

    public function isDescendantOf(Category $category): bool
    {
        return $this->ancestors()->contains(
            fn (Category $ancestor) => (int) $ancestor->getKey() === (int) $category->getKey()
        );
    }

    /**
     * Resolve "Parent > Child > Grandchild" import paths, or a plain name.
     */
    public static function findByPath(string $path): ?static
    {
        $parts = array_filter(array_map('trim', explode('>', $path)));

        if ($parts === []) {
            return null;
        }

        $category = null;

        foreach ($parts as $part) {
            $category = static::query()
                ->where('name', $part)
                ->when(
                    $category,
                    fn ($query) => $query->where('parent_id', $category->getKey()),
                    fn ($query) => $query->whereNull('parent_id'),
                )
                ->first();

            if (! $category) {
                return null;
            }
        }

        return $category;
    }

    protected static function booted(): void
    {
        // A category can never sit under itself or one of its own descendants.
        static::saving(function (Category $category): void {
            if (! $category->parent_id) {
                return;
            }

            if ($category->exists && (int) $category->parent_id === (int) $category->getKey()) {
                throw new InvalidArgumentException('A category cannot be its own parent.');
            }

            $seen = [];
            $node = static::find($category->parent_id);

            while ($node && ! in_array($node->getKey(), $seen, true)) {
                if ($category->exists && (int) $node->getKey() === (int) $category->getKey()) {
                    throw new InvalidArgumentException('A category cannot be placed under one of its own subcategories.');
                }

                $seen[] = $node->getKey();
                $node = $node->parent;
            }
        });
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
