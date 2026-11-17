<?php

namespace App\Filament\Resources\Vendors;

use App\Enums\BusinessType;
use App\Enums\PayoutMethod;
use App\Filament\Concerns\HidesFromWarehouseManagers;
use App\Filament\Resources\Vendors\Pages\ManageVendors;
use App\Filament\Resources\Vendors\Pages\ViewVendor;
use App\Filament\Shared\AuditedUpdate;
use App\Filament\Shared\KycDocumentsRelationManager;
use App\Models\Vendor;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class VendorResource extends Resource
{
    use HidesFromWarehouseManagers;

    protected static ?string $model = Vendor::class;

    protected static ?string $navigationLabel = 'Vendors';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $recordTitleAttribute = 'legal_name';

    public static function canViewAny(): bool
    {
        return Gate::allows('manage-vendors');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return Gate::allows('manage-vendors') ? ['legal_name', 'trade_name', 'pan_number', 'phone'] : [];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Business')->columns(2)->schema([
                TextInput::make('legal_name')->required()->maxLength(200),
                TextInput::make('trade_name')->maxLength(200),
                Select::make('business_type')->options(BusinessType::class)->required(),
                TextInput::make('pan_number')->label('PAN')->required()->regex('/^\d{9}$/')->unique(ignoreRecord: true),
                TextInput::make('registration_number')->maxLength(60),
                TextInput::make('registration_authority')->maxLength(150),
                Toggle::make('vat_registered')->label('VAT registered'),
                Toggle::make('sells_food')->label('Supplies food (DFTQC license required)'),
            ]),
            Section::make('Login')->columns(2)->schema([
                TextInput::make('phone')->tel()->required()->unique(ignoreRecord: true),
                TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->dehydrated(fn ($state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->helperText('Leave blank to keep the current password.'),
            ]),
            Section::make('Pickup location (fixed; vendors request changes)')->columns(2)->collapsed()->schema([
                Select::make('city_id')->relationship('city', 'name')->required(),
                TextInput::make('pickup_district'),
                TextInput::make('pickup_municipality'),
                TextInput::make('pickup_ward'),
                TextInput::make('pickup_tole'),
                TextInput::make('pickup_latitude')->numeric(),
                TextInput::make('pickup_longitude')->numeric(),
            ]),
            Section::make('Payout')->columns(2)->collapsed()->schema([
                Select::make('payout_method')->options(PayoutMethod::class),
                Select::make('bank_name')->options(array_combine(config('nepal.banks'), config('nepal.banks')))->searchable(),
                TextInput::make('bank_account_name'),
                TextInput::make('bank_account_number'),
                TextInput::make('wallet_id')->label('Wallet ID'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('city')->withMin('kycDocuments', 'expires_on'))
            ->columns([
                TextColumn::make('legal_name')->searchable()->description(fn (Vendor $v) => $v->trade_name),
                TextColumn::make('pan_number')->label('PAN')->searchable(),
                TextColumn::make('business_type')->badge(),
                TextColumn::make('city.name'),
                TextColumn::make('verification_status')->label('Verification')->badge(),
                IconColumn::make('vat_registered')->label('VAT')->boolean(),
                TextColumn::make('status')->label('Login')->badge()->color(fn ($state) => $state === 'inactive' ? 'danger' : 'gray'),
                TextColumn::make('kyc_documents_min_expires_on')->label('Next doc expiry')->date()->placeholder('—')->sortable(),
                TextColumn::make('submitted_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                SelectFilter::make('city_id')->relationship('city', 'name')->label('City'),
                SelectFilter::make('business_type')->options(BusinessType::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Model $record) => AuditedUpdate::withHiddenFields($data, $record))
                    ->using(fn (Model $record, array $data) => AuditedUpdate::save($record, $data, 'vendor_updated_by_admin')),
            ]);
    }

    public static function getRelations(): array
    {
        return [KycDocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVendors::route('/'),
            'view' => ViewVendor::route('/{record}'),
        ];
    }
}
