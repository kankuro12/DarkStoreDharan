<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BusinessType: string implements HasLabel
{
    case SoleProprietorship = 'sole_proprietorship';
    case Partnership = 'partnership';
    case PrivateLimited = 'private_limited';
    case PublicLimited = 'public_limited';

    public function registrationDocument(): KycDocumentType
    {
        return match ($this) {
            self::PrivateLimited, self::PublicLimited => KycDocumentType::CompanyRegistration,
            self::SoleProprietorship, self::Partnership => KycDocumentType::FirmRegistration,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::SoleProprietorship => 'Sole Proprietorship',
            self::Partnership => 'Partnership Firm',
            self::PrivateLimited => 'Private Limited Company',
            self::PublicLimited => 'Public Limited Company',
        };
    }
}
