<?php

namespace App\Filament\Resources\VendorPayouts;

use App\Filament\Concerns\HidesFromWarehouseManagers;
use App\Filament\Resources\VendorPayouts\Pages\ManageVendorPayouts;
use App\Models\Vendor;
use App\Models\VendorPayout;
use App\Services\Vendor\VendorSettlementService;
use BackedEnum;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/** Finance records money paid to suppliers; a payout can never exceed what the period's statement owes. */
class VendorPayoutResource extends Resource
{
    use HidesFromWarehouseManagers;

    protected static ?string $model = VendorPayout::class;

    protected static ?string $navigationLabel = 'Vendor payouts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function canViewAny(): bool
    {
        return Gate::allows('manage-vendor-payouts');
    }

    // Payouts are an audit record: corrections are new entries, never edits.
    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('vendor_id')
                ->label('Vendor')
                ->options(fn () => Vendor::orderBy('legal_name')->pluck('legal_name', 'id'))
                ->searchable()
                ->required(),
            Select::make('method')->options(['bank' => 'Bank transfer', 'wallet' => 'Wallet (eSewa/Khalti)', 'cheque' => 'Cheque'])->required(),
            DatePicker::make('period_from')->required(),
            DatePicker::make('period_to')->required()->afterOrEqual('period_from'),
            TextInput::make('amount')
                ->label('Amount (Rs)')
                ->numeric()
                ->minValue(0.01)
                ->required()
                ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get) {
                    $vendor = Vendor::find($get('vendor_id'));
                    if (! $vendor || ! $get('period_from') || ! $get('period_to')) {
                        return;
                    }

                    $balance = app(VendorSettlementService::class)
                        ->statement($vendor, Carbon::parse($get('period_from')), Carbon::parse($get('period_to')))['balance'];

                    if ((float) $value > $balance + 0.001) {
                        $fail('Amount exceeds the balance due for this period (Rs '.number_format($balance, 2).').');
                    }
                }),
            TextInput::make('reference')->label('Bank / wallet reference')->required()->maxLength(100),
            DatePicker::make('paid_at')->label('Paid on')->required()->beforeOrEqual('today'),
            Textarea::make('note')->maxLength(500)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('paid_at', 'desc')
            ->columns([
                TextColumn::make('vendor.legal_name')->label('Vendor')->searchable(),
                TextColumn::make('period_from')->date()->label('From'),
                TextColumn::make('period_to')->date()->label('To'),
                TextColumn::make('amount')->money('NPR'),
                TextColumn::make('method'),
                TextColumn::make('reference')->searchable(),
                TextColumn::make('paid_at')->date(),
                TextColumn::make('recorder.name')->label('Recorded by'),
            ])
            ->filters([
                SelectFilter::make('vendor_id')->relationship('vendor', 'legal_name')->label('Vendor'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVendorPayouts::route('/'),
        ];
    }
}
