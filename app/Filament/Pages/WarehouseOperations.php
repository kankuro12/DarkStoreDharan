<?php

namespace App\Filament\Pages;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\DeliveryAgent;
use App\Models\Order;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class WarehouseOperations extends Page
{
    protected string $view = 'filament.pages.warehouse-operations';

    protected static ?string $navigationLabel = 'Warehouse Floor Operations';

    protected static ?string $title = 'Warehouse Floor Operations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    public ?int $selectedWarehouseId = null;

    public string $scannedSku = '';

    public ?string $scanResult = null;

    public function mount(): void
    {
        $user = auth()->user();
        if ($user && $user->warehouse_id) {
            $this->selectedWarehouseId = $user->warehouse_id;
        } else {
            $firstWarehouse = Warehouse::active()->first();
            $this->selectedWarehouseId = $firstWarehouse?->id;
        }
    }

    public function getWarehousesProperty()
    {
        return Warehouse::active()->get();
    }

    public function getOrdersProperty(): array
    {
        if (! $this->selectedWarehouseId) {
            return [
                'new' => collect(),
                'picking' => collect(),
                'packing' => collect(),
                'ready' => collect(),
                'dispatched' => collect(),
            ];
        }

        $baseQuery = Order::with(['items', 'address', 'deliveryAgent'])
            ->where('warehouse_id', $this->selectedWarehouseId)
            ->latest('placed_at');

        return [
            'new' => (clone $baseQuery)->where('order_status', OrderStatus::Confirmed)->get(),
            'picking' => (clone $baseQuery)->where('order_status', OrderStatus::Processing)->get(),
            'packing' => (clone $baseQuery)->where('order_status', OrderStatus::Packed)->get(),
            'ready' => (clone $baseQuery)->where('order_status', OrderStatus::ReadyForDispatch)->get(),
            'dispatched' => (clone $baseQuery)->where('order_status', OrderStatus::Dispatched)->get(),
        ];
    }

    public function getAvailableRidersProperty()
    {
        if (! $this->selectedWarehouseId) {
            return collect();
        }

        $warehouse = Warehouse::find($this->selectedWarehouseId);
        $cityId = $warehouse?->cities()->first()?->id;

        return DeliveryAgent::where('city_id', $cityId)
            ->where('status', 'active')
            ->get();
    }

    public function startPicking(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $order->transitionOrderStatus(OrderStatus::Processing, 'Picker started item collection', auth()->id());

        AuditLog::record('warehouse_picking_started', $order, null, ['status' => 'processing'], 'Picker began picking order items');

        Notification::make()
            ->title("Order #{$order->order_number} moved to PICKING")
            ->success()
            ->send();
    }

    public function finishPacking(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $order->transitionOrderStatus(OrderStatus::Packed, 'Items picked and verified. Bag sealed.', auth()->id());

        AuditLog::record('warehouse_packing_finished', $order, null, ['status' => 'packed'], 'Items packed into bag');

        Notification::make()
            ->title("Order #{$order->order_number} moved to PACKED")
            ->success()
            ->send();
    }

    public function markReady(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $order->transitionOrderStatus(OrderStatus::ReadyForDispatch, 'Order staged at dispatch bay', auth()->id());

        AuditLog::record('warehouse_order_ready', $order, null, ['status' => 'ready_for_dispatch'], 'Order waiting for rider pickup');

        Notification::make()
            ->title("Order #{$order->order_number} is READY FOR DISPATCH")
            ->info()
            ->send();
    }

    public function dispatchOrder(int $orderId, ?int $riderId = null): void
    {
        $order = Order::findOrFail($orderId);

        if ($riderId) {
            $order->delivery_agent_id = $riderId;
            $order->delivery_status = DeliveryStatus::Assigned;
        } else {
            // Pick first available rider for the city
            $rider = DeliveryAgent::where('status', 'active')->first();
            if ($rider) {
                $order->delivery_agent_id = $rider->id;
                $order->delivery_status = DeliveryStatus::Assigned;
            }
        }

        $order->transitionOrderStatus(OrderStatus::Dispatched, 'Handed over to rider for fast delivery', auth()->id());

        AuditLog::record('warehouse_order_dispatched', $order, null, ['status' => 'dispatched'], 'Handed over to rider');

        Notification::make()
            ->title("Order #{$order->order_number} DISPATCHED!")
            ->success()
            ->send();
    }

    public function scanBarcode(): void
    {
        $sku = strtoupper(trim($this->scannedSku));
        if (empty($sku)) {
            return;
        }

        $orders = $this->orders['picking']->merge($this->orders['new']);
        $matched = false;

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if (strtoupper($item->sku) === $sku) {
                    $this->scanResult = "MATCH! Found SKU [{$sku}] in Order #{$order->order_number} ({$item->product_name} x {$item->quantity})";
                    $matched = true;
                    break 2;
                }
            }
        }

        if (! $matched) {
            $this->scanResult = "NO MATCH: SKU [{$sku}] not needed in currently pending orders.";
        }

        $this->scannedSku = '';
    }
}
