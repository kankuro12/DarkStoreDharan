<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Packed = 'packed';
    case ReadyForDispatch = 'ready_for_dispatch';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Processing => 'Processing',
            self::Packed => 'Packed',
            self::ReadyForDispatch => 'Ready for Dispatch',
            self::Dispatched => 'Dispatched',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
            self::Returned => 'Returned',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Confirmed => 'info',
            self::Processing => 'warning',
            self::Packed => 'primary',
            self::ReadyForDispatch => 'indigo',
            self::Dispatched => 'warning',
            self::Delivered => 'success',
            self::Cancelled => 'danger',
            self::Returned => 'rose',
        };
    }

    /**
     * Precomposed Tailwind classes for storefront status badges.
     * `color()` returns Filament's semantic palette (info/warning/success/...), which
     * isn't a real Tailwind color scale, so storefront Blade views use this instead.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-gray-100 text-gray-800 border-gray-200',
            self::Confirmed => 'bg-blue-100 text-blue-800 border-blue-200',
            self::Processing => 'bg-amber-100 text-amber-800 border-amber-200',
            self::Packed => 'bg-indigo-100 text-indigo-800 border-indigo-200',
            self::ReadyForDispatch => 'bg-indigo-100 text-indigo-800 border-indigo-200',
            self::Dispatched => 'bg-amber-100 text-amber-800 border-amber-200',
            self::Delivered => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::Cancelled => 'bg-rose-100 text-rose-800 border-rose-200',
            self::Returned => 'bg-rose-100 text-rose-800 border-rose-200',
        };
    }
}
