<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cod = 'cod';
    case Esewa = 'esewa';
    case Khalti = 'khalti';
    case Fonepay = 'fonepay';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return match ($this) {
            self::Cod => 'Cash on Delivery (COD)',
            self::Esewa => 'eSewa Mobile Wallet',
            self::Khalti => 'Khalti Digital Wallet',
            self::Fonepay => 'Fonepay QR',
        };
    }

    public function isOnline(): bool
    {
        return $this !== self::Cod;
    }
}
