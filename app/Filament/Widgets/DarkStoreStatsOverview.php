<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Inventory;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DarkStoreStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $todayOrdersCount = Order::whereDate('placed_at', today())->count();
        $todayRevenue = Order::whereDate('placed_at', today())
            ->whereNotIn('order_status', [OrderStatus::Cancelled])
            ->sum('grand_total');

        $outForDeliveryCount = Order::where('order_status', OrderStatus::Dispatched)->count();

        $lowStockCount = Inventory::get()->filter(fn ($inv) => $inv->isLowStock())->count();

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
