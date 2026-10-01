<?php

namespace App\Filament\Resources\InventoryMovements;

use App\Enums\InventoryMovementType;
use App\Filament\Resources\InventoryMovements\Pages\ListInventoryMovements;
use App\Models\InventoryMovement;
use App\Models\Warehouse;
use App\Support\WarehouseScope;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static ?string $navigationLabel = 'Stock Ledger';

    protected static ?string $modelLabel = 'ledger entry';

    protected static ?string $pluralModelLabel = 'stock ledger';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    /**
     * The ledger is append-only and system-written. Nobody — not even an admin —
     * creates, edits or deletes entries from this screen.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->label('Type'),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('variant.sku')
                    ->label('SKU')
                    ->fontFamily('mono')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('variant.product.name')
                    ->label('Product')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->sortable()
                    ->color(fn (InventoryMovement $record) => $record->quantity >= 0 ? 'success' : 'danger'),
                TextColumn::make('balance_after')
                    ->label('Balance After')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reference_code')
                    ->label('Reference')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('reason')
                    ->limit(40)
                    ->tooltip(fn (InventoryMovement $record) => $record->reason)
                    ->toggleable(),
                TextColumn::make('creator.name')
                    ->label('By')
                    ->placeholder('System')
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([25, 50, 100])
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->relationship('warehouse', 'name')
                    ->options(fn (): array => WarehouseScope::managedOnly()
                        ? [(string) WarehouseScope::managedWarehouseId() => WarehouseScope::managedWarehouseName()]
                        : Warehouse::active()->pluck('name', 'id')->toArray()
                    ),
                SelectFilter::make('type')
                    ->options(InventoryMovementType::class)
                    ->multiple(),
                SelectFilter::make('product_variant_id')
                    ->label('Product Variant')
                    ->relationship('variant', 'sku')
                    ->searchable()
                    ->preload(),
                Filter::make('created_at')
                    ->label('Date range')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryMovements::route('/'),
        ];
    }
}
