<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'city_id',
        'warehouse_id',
        'address_id',
        'delivery_agent_id',
        'subtotal',
        'discount',
        'delivery_fee',
        'tax',
        'grand_total',
        'payment_method',
        'order_status',
        'payment_status',
        'delivery_status',
        'placed_at',
        'confirmed_at',
        'packed_at',
        'dispatched_at',
        'delivered_at',
        'cancelled_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'tax' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'order_status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'delivery_status' => DeliveryStatus::class,
            'payment_method' => PaymentMethod::class,
            'placed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'packed_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function deliveryAgent(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class)->orderBy('created_at', 'desc');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    /**
     * Transition order status and record in status logs.
     */
    public function transitionOrderStatus(OrderStatus $newStatus, ?string $reason = null, ?int $userId = null): void
    {
        $oldStatus = $this->order_status;
        $this->order_status = $newStatus;

        if ($newStatus === OrderStatus::Confirmed && ! $this->confirmed_at) {
            $this->confirmed_at = now();
        } elseif ($newStatus === OrderStatus::Packed && ! $this->packed_at) {
            $this->packed_at = now();
        } elseif ($newStatus === OrderStatus::Dispatched && ! $this->dispatched_at) {
            $this->dispatched_at = now();
        } elseif ($newStatus === OrderStatus::Delivered && ! $this->delivered_at) {
            $this->delivered_at = now();
        } elseif ($newStatus === OrderStatus::Cancelled && ! $this->cancelled_at) {
            $this->cancelled_at = now();
        }

        $this->save();

        $this->statusLogs()->create([
            'user_id' => $userId,
            'status_type' => 'order',
            'from_status' => $oldStatus?->value,
            'to_status' => $newStatus->value,
            'reason' => $reason,
        ]);
    }
}
