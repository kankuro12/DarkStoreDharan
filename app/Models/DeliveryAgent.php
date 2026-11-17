<?php

namespace App\Models;

use App\Enums\KycDocumentType;
use App\Enums\VerificationStatus;
use App\Models\Concerns\HasKyc;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'current_latitude' => 'decimal:7',
            'current_longitude' => 'decimal:7',
            'verification_status' => VerificationStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'agreement_accepted_at' => 'datetime',
            'training_completed_at' => 'datetime',
        ];
    }

    public function requiredDocumentGroups(): array
    {
        return [
            [KycDocumentType::ProfilePhoto],
            [KycDocumentType::Citizenship, KycDocumentType::NationalId],
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'active')->where('availability', 'available');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'rider' && $this->status === 'active';
    }
}
