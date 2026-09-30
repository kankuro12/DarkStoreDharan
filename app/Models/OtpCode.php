<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class OtpCode extends Model
{
    protected $fillable = [
        'phone',
        'purpose',
        'code',
        'attempts',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function scopeValid($query)
    {
        return $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function matches(string $rawCode): bool
    {
        return Hash::check($rawCode, $this->code);
    }
}
