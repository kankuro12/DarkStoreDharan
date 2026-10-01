<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Inventory;
use App\Models\Order;
use App\Support\WarehouseScope;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DarkStoreStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $warehouseId = WarehouseScope::managedWarehouseId();

        $orders = Order::query()->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId));

        $todayOrdersCount = (clone $orders)->whereDate('placed_at', today())->count();

        $todayRevenue = (clone $orders)->whereDate('placed_at', today())
            ->whereNotIn('order_status', [OrderStatus::Cancelled])
            ->sum('grand_total');

        $outForDeliveryCount = (clone $orders)->where('order_status', OrderStatus::Dispatched)->count();

        $lowStockCount = Inventory::query()
            ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->get()
            ->filter(fn ($inventory) => $inventory->isLowStock())
            ->count();

        return [
            Stat::make("Today's Fast Orders", $todayOrdersCount)
                ->description('Orders placed today across dark stores')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),

            Stat::make("Today's Revenue", 'Rs '.number_format($todayRevenue))
                ->description('Delivered and active orders')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Out for Delivery', $outForDeliveryCount)
                ->description('Riders currently on road')
                ->descriptionIcon('heroicon-m-truck')
                ->color('warning'),

            Stat::make('Low Stock Alerts', $lowStockCount)
                ->description('Variants below warehouse reorder level')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStockCount > 0 ? 'danger' : 'gray'),
        ];
    }
}
