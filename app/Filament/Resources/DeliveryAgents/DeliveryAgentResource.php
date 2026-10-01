<?php

namespace App\Filament\Resources\DeliveryAgents;

use App\Filament\Concerns\HidesFromWarehouseManagers;
use App\Filament\Resources\DeliveryAgents\Pages\ManageDeliveryAgents;
use App\Models\DeliveryAgent;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeliveryAgentResource extends Resource
{
    use HidesFromWarehouseManagers;

    protected static ?string $model = DeliveryAgent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('phone')
                    ->tel()
                    ->required(),
                TextInput::make('email')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->helperText('Rider panel login at /rider.'),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->dehydrated(fn ($state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->helperText('Leave blank to keep the current password.'),
                Select::make('city_id')
                    ->relationship('city', 'name')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('active'),
                TextInput::make('availability')
                    ->required()
                    ->default('available'),
                TextInput::make('current_latitude')
                    ->numeric(),
                TextInput::make('current_longitude')
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('city.name')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('availability')
                    ->searchable(),
                TextColumn::make('current_latitude')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('current_longitude')
                    ->numeric()
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
            'index' => ManageDeliveryAgents::route('/'),
        ];
    }
}
