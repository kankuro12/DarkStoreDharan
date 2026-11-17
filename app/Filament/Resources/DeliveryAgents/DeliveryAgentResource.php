<?php

namespace App\Filament\Resources\DeliveryAgents;

use App\Enums\PayoutMethod;
use App\Enums\VehicleType;
use App\Filament\Concerns\HidesFromWarehouseManagers;
use App\Filament\Resources\DeliveryAgents\Pages\ManageDeliveryAgents;
use App\Filament\Resources\DeliveryAgents\Pages\ViewDeliveryAgent;
use App\Filament\Shared\AuditedUpdate;
use App\Filament\Shared\KycDocumentsRelationManager;
use App\Models\DeliveryAgent;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class DeliveryAgentResource extends Resource
{
    use HidesFromWarehouseManagers;

    protected static ?string $model = DeliveryAgent::class;

    protected static ?string $navigationLabel = 'Riders';

    protected static ?string $modelLabel = 'rider';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    public static function canViewAny(): bool
    {
        return Gate::allows('manage-riders');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')->columns(2)->schema([
                    TextInput::make('name')->required()->maxLength(150),
                    TextInput::make('phone')->tel()->required()->unique(ignoreRecord: true),
                    TextInput::make('email')->email()->unique(ignoreRecord: true)->helperText('Optional. Riders log in at /rider with phone or email.'),
                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->dehydrated(fn ($state): bool => filled($state))
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->helperText('Leave blank to keep the current password.'),
                ]),
                Section::make('Location (fixed; riders request changes)')->columns(2)->schema([
                    Select::make('city_id')->relationship('city', 'name')->required()->live(),
                    Select::make('home_warehouse_id')
                        ->label('Home hub')
                        ->options(fn (Get $get) => Warehouse::whereHas('cities', fn ($q) => $q->whereKey($get('city_id')))->pluck('name', 'id')),
                    TextInput::make('base_latitude')->numeric(),
                    TextInput::make('base_longitude')->numeric(),
                ]),
                Section::make('Vehicle & dispatch')->columns(2)->schema([
                    Select::make('vehicle_type')->options(VehicleType::class),
                    Select::make('availability')
                        ->options(['available' => 'Available', 'assigned' => 'Assigned', 'delivering' => 'Delivering', 'offline' => 'Offline'])
                        ->required()
                        ->default('offline'),
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['city', 'homeWarehouse'])->withMin('kycDocuments', 'expires_on'))
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('city.name'),
                TextColumn::make('homeWarehouse.name')->label('Hub')->placeholder('—'),
                TextColumn::make('vehicle_type')->placeholder('—'),
                TextColumn::make('verification_status')->label('Verification')->badge(),
                IconColumn::make('training_completed_at')->label('Trained')->boolean(),
                TextColumn::make('status')->label('Login')->badge()->color(fn ($state) => $state === 'inactive' ? 'danger' : 'gray'),
                TextColumn::make('kyc_documents_min_expires_on')->label('Next doc expiry')->date()->placeholder('—')->sortable(),
                TextColumn::make('submitted_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                SelectFilter::make('city_id')->relationship('city', 'name')->label('City'),
                SelectFilter::make('vehicle_type')->options(VehicleType::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Model $record) => AuditedUpdate::withHiddenFields($data, $record))
                    ->using(fn (Model $record, array $data) => AuditedUpdate::save($record, $data, 'rider_updated_by_admin')),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [KycDocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDeliveryAgents::route('/'),
            'view' => ViewDeliveryAgent::route('/{record}'),
        ];
    }
}
