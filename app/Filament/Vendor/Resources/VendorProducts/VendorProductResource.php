<?php

namespace App\Filament\Vendor\Resources\VendorProducts;

use App\Filament\Vendor\Resources\VendorProducts\Pages\CreateVendorProduct;
use App\Filament\Vendor\Resources\VendorProducts\Pages\EditVendorProduct;
use App\Filament\Vendor\Resources\VendorProducts\Pages\ListVendorProducts;
use App\Models\Product;
use App\Models\ProductVariant;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Supplier catalog submissions. Vendors propose products and their supply (cost) price; DarkStore
 * staff set selling prices, photos and stock, then approve. Vendors never touch status or prices.
 */
class VendorProductResource extends Resource
{
    public const EDITABLE_STATUSES = ['draft', 'pending_review'];

    protected static ?string $model = Product::class;

    protected static ?string $slug = 'products';

    protected static ?string $navigationLabel = 'My products';

    protected static ?string $modelLabel = 'product';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('vendor_id', auth('vendor')->id());
    }

    public static function canEdit(Model $record): bool
    {
        return in_array($record->status, self::EDITABLE_STATUSES, true);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(200),
            Select::make('category_id')->relationship('category', 'name')->searchable()->preload()->required(),
            Select::make('brand_id')->relationship('brand', 'name')->searchable()->preload(),
            Textarea::make('description')->label('Description, ingredients, storage, origin')->rows(4)->columnSpanFull(),
            Repeater::make('variants')
                ->relationship()
                ->label('Pack sizes')
                ->minItems(1)
                ->columns(5)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')->label('Pack (e.g. 500g)')->required()->maxLength(60),
                    TextInput::make('sku')
                        ->label('Your SKU')
                        ->required()
                        ->maxLength(60)
                        ->unique(ProductVariant::class, 'sku', ignoreRecord: true),
                    TextInput::make('barcode')->maxLength(60),
                    TextInput::make('weight_kg')->numeric()->minValue(0),
                    TextInput::make('supply_price')->label('Your price to DarkStore (Rs)')->numeric()->minValue(1)->required(),
                ])
                // supply_price is $hidden on the model, so it is not in the fill data by default.
                ->mutateRelationshipDataBeforeFillUsing(fn (array $data) => array_merge($data, [
                    'supply_price' => ProductVariant::whereKey($data['id'] ?? null)->value('supply_price'),
                ]))
                // Selling price is set by DarkStore staff; start the base price at cost until they do.
                ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => array_merge($data, ['price' => $data['supply_price'], 'status' => 'active']))
                ->mutateRelationshipDataBeforeSaveUsing(fn (array $data) => collect($data)->except(['price', 'status'])->all()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable(),
                // Secondary columns hide on phones so the review status stays visible.
                TextColumn::make('category.name')->visibleFrom('md'),
                TextColumn::make('variants_count')->counts('variants')->label('Packs')->visibleFrom('md'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending_review' => 'In review',
                        'active' => 'Live',
                        'inactive' => 'Paused',
                        default => 'Draft (changes requested)',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'pending_review' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('updated_at')->since(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendorProducts::route('/'),
            'create' => CreateVendorProduct::route('/create'),
            'edit' => EditVendorProduct::route('/{record}/edit'),
        ];
    }
}
