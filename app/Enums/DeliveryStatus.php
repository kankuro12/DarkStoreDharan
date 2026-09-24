<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Assigned = 'assigned';
    case PickedUp = 'picked_up';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Rider Assignment',
            self::Assigned => 'Rider Assigned',
            self::PickedUp => 'Picked Up',
            self::OutForDelivery => 'Out for Delivery',
            self::Delivered => 'Delivered',
            self::Failed => 'Delivery Attempt Failed',
            self::Returned => 'Returned to Warehouse',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Assigned => 'info',
            self::PickedUp => 'warning',
            self::OutForDelivery => 'indigo',
            self::Delivered => 'success',
            self::Failed => 'danger',
            self::Returned => 'rose',
        };
    }
}
