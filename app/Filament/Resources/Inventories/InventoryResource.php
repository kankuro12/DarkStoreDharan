<?php

namespace App\Filament\Resources\Inventories;

use App\Filament\Resources\Inventories\Pages\ManageInventories;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('warehouse_id')
                    ->relationship('warehouse', 'name')
                    ->required(),
                Select::make('product_variant_id')
                    ->label('Product Variant')
                    ->options(function () {
                        return ProductVariant::with('product')->get()->mapWithKeys(function ($v) {
                            return [$v->id => "{$v->product?->name} - {$v->name} ({$v->sku})"];
                        });
                    })
                    ->searchable()
                    ->required(),
                TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('reserved_quantity')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('reorder_level')
                    ->required()
                    ->numeric()
                    ->default(5),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('warehouse.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('variant.product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('variant.sku')
                    ->label('SKU')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reserved_quantity')
                    ->numeric()
                    ->color('warning')
                    ->sortable(),
                TextColumn::make('available')
                    ->label('Available Stock')
                    ->numeric()
                    ->badge()
                    ->color(fn (Inventory $record) => $record->isLowStock() ? 'danger' : 'success')
                    ->sortable(),
                TextColumn::make('reorder_level')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('adjust_stock')
                    ->label('Adjust Stock')
                    ->icon('heroicon-m-arrows-up-down')
                    ->color('warning')
                    ->form([
                        TextInput::make('quantity_delta')
                            ->label('Adjustment Quantity (+ or -)')
                            ->helperText('Use positive number to add stock (e.g. 10), negative to deduct (e.g. -5)')
                            ->numeric()
                            ->required(),
                        TextInput::make('reason')
                            ->label('Mandatory Reason for Adjustment (§31.3)')
                            ->placeholder('e.g. Damage during transit, Stock recount, Shrinkage')
                            ->required(),
                    ])
                    ->action(function (Inventory $record, array $data) {
                        $delta = (int) $data['quantity_delta'];
                        $reason = $data['reason'];

                        app(InventoryService::class)->adjustStock(
                            $record->warehouse_id,
                            $record->product_variant_id,
                            $delta,
                            $reason,
                            auth()->id()
                        );

                        Notification::make()
                            ->title('Stock adjusted and recorded in Audit Log.')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInventories::route('/'),
        ];
    }
}
