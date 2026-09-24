<?php

namespace App\Services\Returns;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Customer requests a return (§47).
     */
    public function requestReturn(
        Order $order,
        string $reasonCode,
        ?string $details = null,
        ?int $userId = null
    ): ReturnRequest {
        return DB::transaction(function () use ($order, $reasonCode, $details, $userId) {
            $returnRequest = ReturnRequest::create([
                'order_id' => $order->id,
                'user_id' => $userId ?? $order->user_id,
                'reason_code' => $reasonCode,
                'reason_details' => $details,
                'refund_amount' => $order->grand_total,
                'status' => 'requested',
            ]);

            AuditLog::record(
                'return_requested',
                $returnRequest,
                null,
                $returnRequest->toArray(),
                "Customer requested return for Order #{$order->order_number}. Reason: {$reasonCode}",
                $userId
            );

            return $returnRequest;
        });
    }

    /**
     * Support role approves or rejects return request (§31.2, §47).
     */
    public function reviewReturn(
        ReturnRequest $returnRequest,
        bool $approved,
        ?string $adminNotes = null,
        ?int $userId = null
    ): void {
        DB::transaction(function () use ($returnRequest, $approved, $adminNotes, $userId) {
            if ($approved) {
                $returnRequest->status = 'approved';
                $returnRequest->approved_at = now();
            } else {
                $returnRequest->status = 'rejected';
            }

            $returnRequest->admin_notes = $adminNotes;
            $returnRequest->save();

            AuditLog::record(
                $approved ? 'return_approved' : 'return_rejected',
                $returnRequest,
                null,
                ['status' => $returnRequest->status, 'admin_notes' => $adminNotes],
                $approved ? 'Return approved by support' : 'Return rejected by support',
                $userId
            );
        });
    }

    /**
     * Inspect returned item at warehouse and either restore inventory or write off (§47).
     */
    public function inspectAndRestock(
        ReturnRequest $returnRequest,
        bool $restoreStock,
        ?string $notes = null,
        ?int $userId = null
    ): void {
        DB::transaction(function () use ($returnRequest, $restoreStock, $notes, $userId) {
            $order = $returnRequest->order;
            $returnRequest->status = 'inspected';
            $returnRequest->inspected_at = now();
            $returnRequest->restocked = $restoreStock;
            $returnRequest->admin_notes = $notes;
            $returnRequest->save();

            if ($restoreStock && $order) {
                // Restore quantities to warehouse stock
                foreach ($order->items as $item) {
                    $this->inventoryService->adjustStock(
                        $order->warehouse_id,
                        $item->variant_id,
                        $item->quantity,
                        "Restocked from returned order #{$order->order_number} (Inspection verified sellable condition)",
                        $userId
                    );
                }
            } else {
                AuditLog::record(
                    'stock_written_off',
                    $returnRequest,
                    null,
                    ['order_id' => $order?->id, 'restocked' => false],
                    "Returned items written off as damaged/unsellable for Order #{$order?->order_number}. Reason: {$notes}",
                    $userId
                );
            }

            $order?->transitionOrderStatus(OrderStatus::Returned, 'Return inspected and processed at warehouse', $userId);
        });
    }

    /**
     * Finance completes refund (§31.2, §47).
     */
    public function processRefund(ReturnRequest $returnRequest, ?string $financeNotes = null, ?int $userId = null): void
    {
        DB::transaction(function () use ($returnRequest, $financeNotes, $userId) {
            $order = $returnRequest->order;

            $returnRequest->status = 'completed';
            $returnRequest->refunded_at = now();
            $returnRequest->save();

            if ($order) {
                $order->payment_status = PaymentStatus::Refunded;
                $order->save();

                $order->statusLogs()->create([
                    'user_id' => $userId,
                    'status_type' => 'payment',
                    'from_status' => PaymentStatus::Paid->value,
                    'to_status' => PaymentStatus::Refunded->value,
                    'reason' => "Refund of Rs {$returnRequest->refund_amount} processed. {$financeNotes}",
                ]);
            }

            AuditLog::record(
                'refund_completed',
                $returnRequest,
                null,
                ['amount' => $returnRequest->refund_amount, 'status' => 'completed'],
                "Refund completed for Order #{$order?->order_number}",
                $userId
            );
        });
    }
}
