<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case CatalogManager = 'catalog_manager';
    case WarehouseManager = 'warehouse_manager';
    case PickerPacker = 'picker_packer';
    case DeliveryCoordinator = 'delivery_coordinator';
    case Finance = 'finance';
    case Support = 'support';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::CatalogManager => 'Catalog Manager',
            self::WarehouseManager => 'Warehouse Manager',
            self::PickerPacker => 'Picker / Packer',
            self::DeliveryCoordinator => 'Delivery Coordinator',
            self::Finance => 'Finance',
            self::Support => 'Customer Support',
            self::Customer => 'Customer',
        };
    }
}
