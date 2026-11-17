<?php

namespace App\Models;

use App\Enums\BusinessType;
use App\Enums\KycDocumentType;
use App\Enums\PayoutMethod;
use App\Enums\VerificationStatus;
use App\Models\Concerns\HasKyc;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A consignment supplier. Customers never see vendors: products are sold as DarkStore's own,
 * and vendors are paid the supply price snapshotted on delivered order items.
 */
class Vendor extends Authenticatable implements FilamentUser, HasName
{
    use HasKyc, Notifiable, SoftDeletes;

    protected $fillable = [
        'legal_name', 'trade_name', 'business_type', 'registration_number', 'registration_authority',
        'pan_number', 'vat_registered', 'sells_food', 'authorized_person_name', 'authorized_person_designation',
        'phone', 'email', 'password', 'phone_verified_at',
        'city_id', 'pickup_province', 'pickup_district', 'pickup_municipality', 'pickup_ward', 'pickup_tole',
        'pickup_latitude', 'pickup_longitude',
        'payout_method', 'bank_name', 'bank_account_name', 'bank_account_number', 'wallet_id',
        'status', 'verification_status', 'submitted_at', 'approved_at', 'approved_by', 'rejection_reason',
        'agreement_version', 'agreement_accepted_at', 'agreement_ip',
    ];

    protected $hidden = ['password', 'remember_token', 'bank_account_number', 'registration_number'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'business_type' => BusinessType::class,
            'payout_method' => PayoutMethod::class,
            'verification_status' => VerificationStatus::class,
            'registration_number' => 'encrypted',
            'bank_account_number' => 'encrypted',
            'vat_registered' => 'boolean',
            'sells_food' => 'boolean',
            'pickup_latitude' => 'decimal:7',
            'pickup_longitude' => 'decimal:7',
            'phone_verified_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'agreement_accepted_at' => 'datetime',
        ];
    }

    public function requiredDocumentGroups(): array
    {
        $groups = [
            [KycDocumentType::PanCertificate],
            [KycDocumentType::CompanyRegistration, KycDocumentType::FirmRegistration],
            [KycDocumentType::Citizenship, KycDocumentType::NationalId], // authorized person
            [KycDocumentType::BankProof],
        ];

        if ($this->vat_registered) {
            $groups[] = [KycDocumentType::VatCertificate];
        }

        if ($this->sells_food) {
            $groups[] = [KycDocumentType::FoodLicense];
        }

        return $groups;
    }

    public function locationAttributes(): array
    {
        return [
            'city_id', 'pickup_province', 'pickup_district', 'pickup_municipality', 'pickup_ward', 'pickup_tole',
            'pickup_latitude', 'pickup_longitude',
        ];
    }

    /** Used by shared admin screens (e.g. the location-change list) alongside riders' `name`. */
    public function getNameAttribute(): string
    {
        return $this->trade_name ?: $this->legal_name;
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function isApproved(): bool
    {
        return $this->verification_status === VerificationStatus::Approved;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'vendor' && $this->status !== 'inactive';
    }
}
