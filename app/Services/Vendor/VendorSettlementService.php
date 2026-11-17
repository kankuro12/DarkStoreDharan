<?php

namespace App\Services\Vendor;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Models\Vendor;
use App\Models\VendorPayout;
use Carbon\CarbonInterface;

/**
 * Consignment settlement: a vendor is owed quantity × the supply price snapshotted on each order line
 * of orders delivered in the period. Cancelled / returned orders never reach `delivered`.
 * shortcut: partial returns are not netted per line; add that when ReturnRequest supports per-item returns.
 */
class VendorSettlementService
{
    /**
     * @return array{lines: list<array{sku: string, product_name: string, quantity: int, unit_supply_price: float, amount: float}>, payable: float, paid: float, balance: float}
     */
    public function statement(Vendor $vendor, CarbonInterface $from, CarbonInterface $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $lines = OrderItem::query()
            ->where('order_items.vendor_id', $vendor->id)
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.order_status', OrderStatus::Delivered->value)
            ->whereBetween('orders.delivered_at', [$from, $to])
            ->groupBy('order_items.sku', 'order_items.product_name', 'order_items.unit_supply_price')
            ->orderBy('order_items.product_name')
            // Only catalog and money columns: never order, customer or address data.
            ->selectRaw('order_items.sku, order_items.product_name, order_items.unit_supply_price, SUM(order_items.quantity) as quantity')
            ->get()
            ->map(fn ($row) => [
                'sku' => $row->sku,
                'product_name' => $row->product_name,
                'quantity' => (int) $row->quantity,
                'unit_supply_price' => (float) $row->unit_supply_price,
                'amount' => round((int) $row->quantity * (float) $row->unit_supply_price, 2),
            ])
            ->all();

        $payable = round(array_sum(array_column($lines, 'amount')), 2);
        $paid = (float) VendorPayout::where('vendor_id', $vendor->id)
            ->whereDate('period_from', '<=', $to)
            ->whereDate('period_to', '>=', $from)
            ->sum('amount');

        return [
            'lines' => $lines,
            'payable' => $payable,
            'paid' => round($paid, 2),
            'balance' => round($payable - $paid, 2),
        ];
    }
}
