<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Variants (Pack Sizes)';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Variant Name')
                    ->placeholder('e.g. 500ml Pouch, 1kg Bag')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, callable $set, $get) {
                        if ($operation === 'create' && blank($get('sku'))) {
                            $set('sku', Str::upper(Str::slug($state, '-')));
                        }
                    }),
                TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('barcode')
                    ->maxLength(255),
                TextInput::make('price')
                    ->label('Base Price (Rs)')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                TextInput::make('weight_kg')
                    ->label('Weight (kg)')
                    ->numeric()
                    ->minValue(0),
                Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->default('active')
                    ->required(),
                Select::make('cod_allowed')
                    ->label('Cash on Delivery')
                    ->options([1 => 'Allowed', 0 => 'Not Allowed'])
                    ->default(1)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->weight(FontWeight::Bold)
                    ->searchable(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('price')
                    ->money('NPR')
                    ->sortable(),
                TextColumn::make('inventories_sum_quantity')
                    ->label('Total Stock')
                    ->sum('inventories', 'quantity')
                    ->badge()
                    ->color(fn ($state) => ($state ?? 0) > 0 ? 'success' : 'danger'),
                IconColumn::make('cod_allowed')
                    ->label('COD')
                    ->boolean(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'gray'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
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
}
