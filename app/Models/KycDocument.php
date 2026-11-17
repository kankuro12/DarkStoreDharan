<?php

namespace App\Models;

use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class KycDocument extends Model
{
    protected $fillable = [
        'type',
        'document_number',
        'issuing_authority',
        'issued_on',
        'expires_on',
        'file_front',
        'file_back',
        'meta',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $hidden = ['document_number'];

    protected function casts(): array
    {
        return [
            'type' => KycDocumentType::class,
            'status' => KycDocumentStatus::class,
            'document_number' => 'encrypted',
            'meta' => 'array',
            'issued_on' => 'date',
            'expires_on' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Audit-safe snapshot: never includes the (encrypted) document number. */
    public function auditSnapshot(): array
    {
        return [
            'type' => $this->type?->value,
            'status' => $this->status?->value,
            'file_front' => $this->file_front,
            'file_back' => $this->file_back,
            'expires_on' => $this->expires_on?->toDateString(),
        ];
    }
}
