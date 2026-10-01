<?php

namespace App\Filament\Resources\FreeDeliveryRules;

use App\Filament\Concerns\HidesFromWarehouseManagers;
use App\Filament\Resources\FreeDeliveryRules\Pages\ManageFreeDeliveryRules;
use App\Models\FreeDeliveryRule;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FreeDeliveryRuleResource extends Resource
{
    use HidesFromWarehouseManagers;

    protected static ?string $model = FreeDeliveryRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Free Delivery Matrix';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Rule Name (shown to customers as the reason)')
                    ->required()
                    ->maxLength(150)
                    ->columnSpanFull(),
                Select::make('city_id')
                    ->label('City')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->helperText('Leave empty to apply in every city.'),
                Toggle::make('active')
                    ->default(true),
                Select::make('product_id')
                    ->label('Specific Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->helperText('Free delivery when this product is in the cart.'),
                Select::make('category_id')
                    ->label('Or: Any Product in Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->helperText('Free delivery when any product from this category is in the cart. Ignored if a specific product is set above.'),
                TextInput::make('min_quantity')
                    ->label('Minimum Quantity')
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Required quantity of the product/category above. Defaults to 1.'),
                TextInput::make('min_subtotal')
                    ->label('Minimum Cart Subtotal (Rs)')
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Optional extra condition. Can also be used alone (no product/category) as an additional city-wide threshold.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('city.name')
                    ->label('City')
                    ->placeholder('All cities')
                    ->badge(),
                TextColumn::make('product.name')
                    ->label('Product')
                    ->placeholder('—'),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->placeholder('—'),
                TextColumn::make('min_quantity')
                    ->label('Min Qty')
                    ->placeholder('—'),
                TextColumn::make('min_subtotal')
                    ->label('Min Subtotal')
                    ->money('NPR')
                    ->placeholder('—'),
                IconColumn::make('active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
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
            'index' => ManageFreeDeliveryRules::route('/'),
        ];
    }
}
