<?php

namespace App\Filament\Resources\Popups;

use App\Filament\Concerns\HidesFromWarehouseManagers;
use App\Filament\Resources\Popups\Pages\ManagePopups;
use App\Models\Popup;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PopupResource extends Resource
{
    use HidesFromWarehouseManagers;

    protected static ?string $model = Popup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Storefront popups';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Content')->columnSpanFull()->columns(2)->schema([
                TextInput::make('title')->required()->maxLength(120)->columnSpanFull(),
                Textarea::make('body')->rows(3)->maxLength(1000)->columnSpanFull(),
                FileUpload::make('image')->image()->disk('public')->directory('popups')->maxSize(2048)->columnSpanFull(),
                TextInput::make('button_label')->maxLength(40)->requiredWith('button_url'),
                TextInput::make('button_url')
                    ->label('Button link')
                    ->helperText('A page on this site like /products/ilam-tea, or a full https:// link.')
                    ->maxLength(255)
                    ->regex('/^(https?:\/\/|\/)\S*$/'),
            ]),
            Section::make('Where and when')->columnSpanFull()->columns(2)->schema([
                Select::make('placement')->options(Popup::PLACEMENTS)->default('all')->required()->live(),
                Select::make('frequency')->options(Popup::FREQUENCIES)->default('once')->required(),
                Select::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Leave empty to show on every product page.')
                    ->visible(fn (Get $get) => $get('placement') === 'product'),
                TextInput::make('target_path')
                    ->label('Page path')
                    ->placeholder('/checkout')
                    ->helperText('Path after the domain. Use * as a wildcard, e.g. /help* or /products/*.')
                    ->required(fn (Get $get) => $get('placement') === 'path')
                    ->visible(fn (Get $get) => $get('placement') === 'path'),
                DateTimePicker::make('starts_at')->label('Show from'),
                DateTimePicker::make('ends_at')->label('Show until')->after('starts_at'),
                Toggle::make('is_active')->label('Active')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->wrap(),
                TextColumn::make('placement')
                    ->formatStateUsing(fn (Popup $record) => match ($record->placement) {
                        'product' => $record->product?->name ?? 'All product pages',
                        'path' => $record->target_path,
                        default => Popup::PLACEMENTS[$record->placement] ?? $record->placement,
                    }),
                TextColumn::make('ends_at')->label('Until')->dateTime()->placeholder('No end')->visibleFrom('md'),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePopups::route('/'),
        ];
    }
}
