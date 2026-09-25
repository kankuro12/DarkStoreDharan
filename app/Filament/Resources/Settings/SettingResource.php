<?php

namespace App\Filament\Resources\Settings;

use App\Filament\Resources\Settings\Pages\ManageSettings;
use App\Models\Setting;
use Awcodes\Curator\Components\Forms\CuratorPicker;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'keykey';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->required(),
                Select::make('type')
                    ->options([
                        'text' => 'Text',
                        'textarea' => 'Textarea',
                        'image' => 'Image',
                    ])
                    ->required()
                    ->live()
                    ->default('text'),
                TextInput::make('value')
                    ->label('Value (Text)')
                    ->hidden(fn (Get $get) => $get('type') !== 'text'),
                Textarea::make('value')
                    ->label('Value (Textarea)')
                    ->hidden(fn (Get $get) => $get('type') !== 'textarea')
                    ->columnSpanFull(),
                CuratorPicker::make('value')
                    ->label('Value (Image)')
                    ->hidden(fn (Get $get) => $get('type') !== 'image')
                    ->columnSpanFull(),
                TextInput::make('group')
                    ->required()
                    ->default('general'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('keykey')
            ->columns([
                TextColumn::make('key')
                    ->searchable(),
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('group')
                    ->searchable(),
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
            'index' => ManageSettings::route('/'),
        ];
    }
}
