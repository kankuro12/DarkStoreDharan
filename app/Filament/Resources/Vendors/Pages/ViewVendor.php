<?php

namespace App\Filament\Resources\Vendors\Pages;

use App\Filament\Resources\Vendors\VendorResource;
use App\Filament\Shared\AccountActions;
use App\Filament\Shared\VerificationActions;
use App\Models\Vendor;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewVendor extends ViewRecord
{
    protected static string $resource = VendorResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Verification')->columns(4)->schema([
                TextEntry::make('verification_status')->badge(),
                TextEntry::make('status')->label('Login')->badge()->color(fn ($state) => $state === 'inactive' ? 'danger' : 'success'),
                TextEntry::make('submitted_at')->dateTime()->placeholder('—'),
                TextEntry::make('approved_at')->dateTime()->placeholder('—'),
                TextEntry::make('rejection_reason')->label('Last note')->placeholder('—')->columnSpan(2),
                TextEntry::make('agreement_version')->placeholder('—'),
                TextEntry::make('agreement_ip')->label('Agreement IP')->placeholder('—'),
            ]),
            Section::make('Business')->columns(4)->schema([
                TextEntry::make('legal_name'),
                TextEntry::make('trade_name')->placeholder('—'),
                TextEntry::make('business_type')->badge(),
                TextEntry::make('pan_number')->label('PAN'),
                TextEntry::make('registration_number')->placeholder('—'),
                TextEntry::make('registration_authority')->placeholder('—'),
                IconEntry::make('vat_registered')->label('VAT registered')->boolean(),
                IconEntry::make('sells_food')->label('Supplies food')->boolean(),
                TextEntry::make('authorized_person_name')->label('Authorized person')
                    ->formatStateUsing(fn ($state, Vendor $v) => "{$state} ({$v->authorized_person_designation})")->placeholder('—'),
                TextEntry::make('phone'),
                TextEntry::make('email'),
            ]),
            Section::make('Pickup & payout')->columns(4)->schema([
                TextEntry::make('city.name'),
                TextEntry::make('pickup_address')->state(fn (Vendor $v) => collect([$v->pickup_tole, $v->pickup_municipality.' - '.$v->pickup_ward, $v->pickup_district, $v->pickup_province])->filter()->implode(', '))->columnSpan(2),
                TextEntry::make('pickup_latitude')->label('Coordinates')
                    ->formatStateUsing(fn ($state, Vendor $v) => "{$v->pickup_latitude}, {$v->pickup_longitude}")->placeholder('—'),
                TextEntry::make('payout_method')->placeholder('—'),
                TextEntry::make('bank_name')->placeholder('—'),
                TextEntry::make('bank_account_name')->placeholder('—'),
                TextEntry::make('wallet_id')->placeholder('—'),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            ...VerificationActions::make(fn () => $this->record),
            AccountActions::make(fn () => $this->record, 'vendor', 'vendors', 'vendor'),
        ];
    }
}
