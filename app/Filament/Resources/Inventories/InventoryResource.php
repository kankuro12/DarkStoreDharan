<?php

namespace App\Filament\Resources\Inventories;

use App\Enums\InventoryMovementType;
use App\Filament\Resources\Inventories\Pages\ManageInventories;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;

    protected static ?string $navigationLabel = 'Inventory & Stock';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    /**
     * Only the reorder level is hand-editable. Quantity changes flow through the
     * ledger actions so every unit in or out leaves a permanent movement record.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('reorder_level')
                    ->label('Reorder Level')
                    ->helperText('Low-stock alerts fire when available stock drops to this number.')
                    ->required()
                    ->numeric()
                    ->default(5),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('variant.product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('variant.sku')
                    ->label('SKU')
                    ->fontFamily('mono')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reserved_quantity')
                    ->label('Reserved')
                    ->numeric()
                    ->color('warning')
                    ->sortable(),
                TextColumn::make('available')
                    ->label('Available')
                    ->numeric()
                    ->badge()
                    ->color(fn (Inventory $record) => $record->isLowStock() ? 'danger' : 'success')
                    ->sortable(),
                TextColumn::make('reorder_level')
                    ->label('Reorder At')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('quantity', 'asc')
            ->filters([
                Filter::make('low_stock')
                    ->label('Low stock only')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereRaw('(quantity - reserved_quantity) <= reorder_level')),
            ])
            ->recordActions([
                self::adjustStockAction(),
                self::transferAction(),
                self::historyAction(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkAction::make('addStockBulk')
                    ->label('Add Stock to Selected')
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->color('success')
                    ->form([
                        TextInput::make('quantity')
                            ->label('Quantity to add (per selected row)')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('reason')
                            ->label('Reason')
                            ->placeholder('e.g. Supplier delivery, Stock count')
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $service = app(InventoryService::class);

                        foreach ($records as $record) {
                            $service->adjustStock(
                                $record->warehouse_id,
                                $record->product_variant_id,
                                (int) $data['quantity'],
                                $data['reason'],
                                auth()->id(),
                            );
                        }

                        Notification::make()
                            ->title("Added {$data['quantity']} units to {$records->count()} inventory row(s).")
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function adjustStockAction(): Action
    {
        return Action::make('adjust_stock')
            ->label('Adjust Stock')
            ->icon(Heroicon::OutlinedArrowsUpDown)
            ->color('warning')
            ->form([
                TextInput::make('quantity_delta')
                    ->label('Adjustment Quantity (+ or -)')
                    ->helperText('Positive adds stock, negative deducts (e.g. -3 for damage).')
                    ->numeric()
                    ->required(),
                TextInput::make('reason')
                    ->label('Mandatory Reason')
                    ->placeholder('e.g. Damage during transit, Stock recount, Shrinkage')
                    ->required(),
            ])
            ->action(function (Inventory $record, array $data) {
                app(InventoryService::class)->adjustStock(
                    $record->warehouse_id,
                    $record->product_variant_id,
                    (int) $data['quantity_delta'],
                    $data['reason'],
                    auth()->id()
                );

                Notification::make()
                    ->title('Stock adjusted and recorded in the ledger.')
                    ->success()
                    ->send();
            });
    }

    public static function transferAction(): Action
    {
        return Action::make('transfer')
            ->label('Transfer')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('info')
            ->visible(fn (Inventory $record) => $record->available > 0)
            ->form([
                Select::make('to_warehouse_id')
                    ->label('Destination Warehouse')
                    ->options(fn (Inventory $record) => Warehouse::query()
                        ->whereKeyNot($record->warehouse_id)
                        ->pluck('name', 'id'))
                    ->required(),
                TextInput::make('quantity')
                    ->label('Quantity to Transfer')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(fn (Inventory $record) => $record->available)
                    ->required(),
                TextInput::make('reason')
                    ->label('Reason')
                    ->required(),
            ])
            ->action(function (Inventory $record, array $data) {
                try {
                    app(InventoryService::class)->transfer(
                        $record->warehouse_id,
                        (int) $data['to_warehouse_id'],
                        $record->product_variant_id,
                        (int) $data['quantity'],
                        $data['reason'],
                        auth()->id(),
                    );
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title('Transfer recorded in both warehouses\' ledgers.')
                    ->success()
                    ->send();
            });
    }

    public static function historyAction(): Action
    {
        return Action::make('history')
            ->label('Ledger')
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->color('gray')
            ->modalHeading(fn (Inventory $record) => "Ledger — {$record->variant?->sku}")
            ->modalContent(fn (Inventory $record) => view('filament.inventory-ledger-history', [
                'movements' => InventoryMovement::query()
                    ->with('creator')
                    ->where('warehouse_id', $record->warehouse_id)
                    ->where('product_variant_id', $record->product_variant_id)
                    ->latest('id')
                    ->limit(25)
                    ->get(),
                'inboundTypes' => [InventoryMovementType::Opening, InventoryMovementType::In, InventoryMovementType::TransferIn],
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInventories::route('/'),
        ];
    }
}
