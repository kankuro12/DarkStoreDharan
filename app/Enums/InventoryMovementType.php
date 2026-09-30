<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InventoryMovementType: string implements HasColor, HasLabel
{
    case Opening = 'opening';
    case In = 'in';
    case Out = 'out';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return $this->color();
    }

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening',
            self::In => 'Stock In',
            self::Out => 'Stock Out',
            self::TransferIn => 'Transfer In',
            self::TransferOut => 'Transfer Out',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Opening => 'gray',
            self::In => 'success',
            self::Out => 'danger',
            self::TransferIn => 'info',
            self::TransferOut => 'warning',
        };
    }
}
