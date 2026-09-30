<?php

namespace App\Filament\Imports;

use App\Models\Product;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class ProductImporter extends Importer
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('Product Name')
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->example('Coca-Cola Refreshing Soft Drink'),

            ImportColumn::make('slug')
                ->label('Slug')
                ->rules(['nullable', 'max:255'])
                ->example('coca-cola-refreshing-soft-drink')
                ->helperText('Leave blank to auto-generate from the name. Matching an existing slug updates that product instead of creating a duplicate.'),

            ImportColumn::make('category')
                ->relationship(resolveUsing: 'name')
                ->example('Snacks & Beverages')
                ->helperText('Must exactly match an existing category name; leave blank for none.'),

            ImportColumn::make('brand')
                ->relationship(resolveUsing: 'name')
                ->example('Coca-Cola')
                ->helperText('Must exactly match an existing brand name; leave blank for none.'),

            ImportColumn::make('description')
                ->rules(['nullable', 'max:5000'])
                ->example('Ice-cold refreshing cola, perfect with any meal.'),

            ImportColumn::make('status')
                ->rules(['nullable', 'in:active,inactive,draft'])
                ->example('active')
                ->helperText('active, inactive, or draft. Defaults to active.'),

            ImportColumn::make('image')
                ->label('Image URL')
                ->rules(['nullable', 'url', 'max:2000'])
                ->example('https://example.com/images/coca-cola.jpg')
                ->helperText('A direct image URL. Leave blank to add a photo later from the media library.'),
        ];
    }

    /**
     * Upsert by slug (or a generated slug from the name) so re-importing the same file
     * updates existing products instead of creating duplicates — same convention Shopify's
     * CSV import uses with its "Handle" column.
     */
    public function resolveRecord(): Product
    {
        $slug = filled($this->data['slug'] ?? null)
            ? Str::slug($this->data['slug'])
            : Str::slug($this->data['name']);

        return Product::firstOrNew(['slug' => $slug]);
    }

    protected function beforeFill(): void
    {
        if (blank($this->data['slug'] ?? null)) {
            $this->data['slug'] = Str::slug($this->data['name']);
        }

        if (blank($this->data['status'] ?? null)) {
            $this->data['status'] = 'active';
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your product import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
