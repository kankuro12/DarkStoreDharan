<?php

namespace App\Filament\Resources\Cities;

use App\Filament\Resources\Cities\Pages\ManageCities;
use App\Models\City;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('City Name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                TextInput::make('slug')
                    ->label('URL Slug')
                    ->required()
                    ->unique(City::class, 'slug', ignoreRecord: true),
                TextInput::make('province')
                    ->label('Province / State')
                    ->placeholder('e.g. Koshi, Bagmati'),
                Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->default('active')
                    ->required(),
                Select::make('warehouses')
                    ->relationship('warehouses', 'name')
                    ->multiple()
                    ->preload()
                    ->label('Assigned Dark Stores / Warehouses')
                    ->helperText('Select which fulfillment centers serve this city.'),
                Toggle::make('cod_enabled')
                    ->label('Cash on Delivery (COD) Allowed')
                    ->default(true)
                    ->required(),
                Toggle::make('prepaid_enabled')
                    ->label('Online Prepaid (eSewa / Khalti) Allowed')
                    ->default(true)
                    ->required(),
                TextInput::make('default_delivery_fee')
                    ->label('Base Delivery Fee (Rs)')
                    ->required()
                    ->numeric()
                    ->default(40),
                TextInput::make('free_delivery_minimum')
                    ->label('Free Delivery Minimum (Rs)')
                    ->numeric()
                    ->default(500)
                    ->helperText('Orders exceeding this amount receive free shipping.'),
                TextInput::make('estimated_delivery_minutes')
                    ->label('Target SLA (Minutes)')
                    ->required()
                    ->numeric()
                    ->default(30)
                    ->helperText('Expected door-to-door delivery promise time.'),
                Repeater::make('banners')
                    ->label('Homepage Banners')
                    ->schema([
                        TextInput::make('image_url')->required()->label('Image URL'),
                        TextInput::make('link_url')->nullable()->label('Target Link'),
                    ])
                    ->columnSpanFull()
                    ->collapsible(),
                Select::make('featured_products')
                    ->label('Featured Products')
                    ->multiple()
                    ->options(Product::pluck('name', 'id'))
                    ->searchable()
                    ->columnSpanFull()
                    ->helperText('Select products to highlight on the city homepage.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('City')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('province')
                    ->searchable(),
                TextColumn::make('warehouses.name')
                    ->badge()
                    ->separator(',')
                    ->label('Fulfillment Hubs'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        default => 'gray',
                    }),
                IconColumn::make('cod_enabled')
                    ->boolean()
                    ->label('COD'),
                IconColumn::make('prepaid_enabled')
                    ->boolean()
                    ->label('Prepaid'),
                TextColumn::make('default_delivery_fee')
                    ->prefix('Rs ')
                    ->sortable(),
                TextColumn::make('free_delivery_minimum')
                    ->prefix('Rs ')
                    ->sortable(),
                TextColumn::make('estimated_delivery_minutes')
                    ->suffix('m SLA')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
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
            'index' => ManageCities::route('/'),
        ];
    }
}
