<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PayoutMethod: string implements HasLabel
{
    case Bank = 'bank';
    case Wallet = 'wallet';

    public function getLabel(): string
    {
        return $this === self::Bank ? 'Bank Account' : 'Digital Wallet (eSewa / Khalti)';
    }
}
