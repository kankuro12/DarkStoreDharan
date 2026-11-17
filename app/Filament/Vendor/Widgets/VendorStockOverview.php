<?php

namespace App\Filament\Vendor\Widgets;

use App\Models\ProductVariant;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** The supplier's own packs and how much DarkStore holds across all dark stores (no store names). */
class VendorStockOverview extends TableWidget
{
    public const LOW_STOCK = 5;

    protected static ?string $heading = 'Stock held for you';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ProductVariant::query()
                ->whereHas('product', fn (Builder $query) => $query->where('vendor_id', auth('vendor')->id()))
                ->with('product:id,name,status')
                ->withSum('inventories as on_hand', 'quantity')
                ->withSum('inventories as reserved', 'reserved_quantity'))
            ->columns([
                TextColumn::make('product.name')->label('Product')->searchable(),
                TextColumn::make('name')->label('Pack'),
                TextColumn::make('sku')->fontFamily('mono')->visibleFrom('md'), // keep "Available" on screen on phones
                TextColumn::make('available')
                    ->label('Available')
                    ->state(fn (ProductVariant $record) => max(0, (int) $record->on_hand - (int) $record->reserved))
                    ->badge()
                    ->color(fn (int $state) => $state < self::LOW_STOCK ? 'danger' : 'success'),
                TextColumn::make('product.status')->label('Listing')->badge(),
            ]);
    }
}
