<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Enums\KycDocumentType;
use App\Enums\OrderStatus;
use App\Enums\PayoutMethod;
use App\Enums\VehicleType;
use App\Enums\VerificationStatus;
use App\Models\Concerns\HasKyc;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class DeliveryAgent extends Authenticatable implements FilamentUser
{
    use HasFactory, HasKyc, Notifiable;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'city_id',
        'status',
        'availability',
        'current_latitude',
        'current_longitude',
        'verification_status',
        'submitted_at',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'agreement_version',
        'agreement_accepted_at',
        'agreement_ip',
        'training_completed_at',
        'phone_verified_at',
        'vehicle_type',
        'date_of_birth',
        'gender',
        'perm_province', 'perm_district', 'perm_municipality', 'perm_ward', 'perm_tole',
        'temp_province', 'temp_district', 'temp_municipality', 'temp_ward', 'temp_tole',
        'emergency_contact_name',
        'emergency_contact_relation',
        'emergency_contact_phone',
        'home_warehouse_id',
        'base_latitude',
        'base_longitude',
        'payout_method',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'wallet_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'bank_account_number',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'current_latitude' => 'decimal:7',
            'current_longitude' => 'decimal:7',
            'base_latitude' => 'decimal:7',
            'base_longitude' => 'decimal:7',
            'verification_status' => VerificationStatus::class,
            'vehicle_type' => VehicleType::class,
            'payout_method' => PayoutMethod::class,
            'bank_account_number' => 'encrypted',
            'date_of_birth' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'agreement_accepted_at' => 'datetime',
            'training_completed_at' => 'datetime',
            'phone_verified_at' => 'datetime',
        ];
    }

    public function requiredDocumentGroups(): array
    {
        $groups = [
            [KycDocumentType::ProfilePhoto],
            [KycDocumentType::Citizenship, KycDocumentType::NationalId],
        ];

        if ($this->vehicle_type?->isMotorized()) {
            array_push($groups, [KycDocumentType::DrivingLicense], [KycDocumentType::VehicleBluebook], [KycDocumentType::VehicleInsurance]);
        }

        if ($this->payout_method === PayoutMethod::Bank) {
            $groups[] = [KycDocumentType::BankProof];
        }

        return $groups;
    }

    public function locationAttributes(): array
    {
        return ['city_id', 'home_warehouse_id', 'base_latitude', 'base_longitude'];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function homeWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'home_warehouse_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isApproved(): bool
    {
        return $this->verification_status === VerificationStatus::Approved;
    }

    /** Only verified, trained, active riders may be handed orders. */
    public function scopeEligibleForDispatch(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where('verification_status', VerificationStatus::Approved->value)
            ->whereNotNull('training_completed_at');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->eligibleForDispatch()->where('availability', 'available');
    }

    public const IN_FLIGHT_STATUSES = [DeliveryStatus::PickedUp, DeliveryStatus::OutForDelivery];

    /** Orders already in the rider's hands: they must be able to finish these even if suspended. */
    public function hasInFlightDeliveries(): bool
    {
        return $this->orders()->whereIn('delivery_status', array_map(fn ($s) => $s->value, self::IN_FLIGHT_STATUSES))->exists();
    }

    /** Hand back orders the rider has not picked up yet; picked-up orders stay with them. */
    public function releaseUnstartedDeliveries(): int
    {
        return $this->orders()
            ->where('delivery_status', DeliveryStatus::Assigned->value)
            ->update(['delivery_agent_id' => null, 'delivery_status' => DeliveryStatus::Pending->value, 'rider_pay' => null]);
    }

    /** Assigned or on-the-road deliveries the rider still has to finish. */
    public function openDeliveriesCount(): int
    {
        return $this->orders()
            ->whereIn('delivery_status', [DeliveryStatus::Assigned->value, DeliveryStatus::PickedUp->value, DeliveryStatus::OutForDelivery->value])
            ->where('order_status', '!=', OrderStatus::Cancelled->value)
            ->count();
    }

    public function goOnline(): bool
    {
        if (! static::eligibleForDispatch()->whereKey($this->id)->exists()) {
            return false;
        }

        return $this->update(['availability' => 'available']);
    }

    public function goOffline(): bool
    {
        if ($this->openDeliveriesCount() > 0) {
            return false;
        }

        return $this->update(['availability' => 'offline']);
    }

    /** Login is allowed until an admin hard-blocks it; unapproved riders are confined by EnsureRiderApproved. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'rider' && $this->status !== 'inactive';
    }
}
