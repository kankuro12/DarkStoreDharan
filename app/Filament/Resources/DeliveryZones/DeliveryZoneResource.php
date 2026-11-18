<?php

namespace App\Filament\Resources\DeliveryZones;

use App\Filament\Concerns\HidesFromWarehouseManagers;
use App\Filament\Resources\DeliveryZones\Pages\ManageDeliveryZones;
use App\Models\DeliveryZone;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Admin-preset Area / Chowk / Ward list customers must pick from at checkout. */
class DeliveryZoneResource extends Resource
{
    use HidesFromWarehouseManagers;

    protected static ?string $model = DeliveryZone::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $navigationLabel = 'Delivery areas';

    protected static ?string $modelLabel = 'delivery area';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('city_id')->relationship('city', 'name')->required()->preload(),
            TextInput::make('name')
                ->label('Area / Chowk / Ward')
                ->helperText('Shown to customers exactly like this, e.g. "Bhanuchowk, Ward 3".')
                ->required()->maxLength(100),
            TextInput::make('delivery_fee')->label('Delivery fee (Rs)')->numeric()->minValue(0)->prefix('Rs')->default(40)->required(),
            TextInput::make('estimated_minutes')->label('Delivery time (minutes)')->numeric()->integer()->minValue(1)->default(30)->required(),
            TextInput::make('minimum_order')->label('Minimum order (Rs)')->numeric()->minValue(0)->prefix('Rs')->default(0),
            Toggle::make('cod_enabled')->label('Cash on delivery allowed')->default(true),
            Select::make('status')->options(['active' => 'Active', 'inactive' => 'Hidden'])->default('active')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Area / Chowk / Ward')->searchable()->sortable(),
                TextColumn::make('city.name')->label('City')->sortable(),
                TextColumn::make('delivery_fee')->money('NPR')->sortable(),
                TextColumn::make('estimated_minutes')->suffix(' min'),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'active' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('city_id')->relationship('city', 'name')->label('City'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDeliveryZones::route('/')];
    }
}
