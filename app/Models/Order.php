<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Services\Delivery\DeliveryOfferService;
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
        'customer_id',
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
        'offer_opened_at',
        'rider_pay',
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
            'offer_opened_at' => 'datetime',
            'rider_pay' => 'decimal:2',
        ];
    }

    /** Rider pay the offer shows right now: base, plus one boost per elapsed interval, capped at max (never below base). */
    public function currentOfferPay(): ?float
    {
        if (! $this->offer_opened_at) {
            return null;
        }

        [$base, $boost, $max, $interval] = $this->offerPaySettings();
        $steps = intdiv((int) $this->offer_opened_at->diffInMinutes(now()), $interval);

        return max($base, min($max, $base + $boost * $steps));
    }

    /** The pay has sat at its maximum for a full interval and no rider has taken it. */
    public function offerStuck(): bool
    {
        if (! $this->offer_opened_at || $this->delivery_agent_id) {
            return false;
        }

        [$base, $boost, $max, $interval] = $this->offerPaySettings();
        $stepsToMax = $boost > 0 ? (int) ceil(max(0, $max - $base) / $boost) : 0;

        return $this->offer_opened_at->diffInMinutes(now()) >= ($stepsToMax + 1) * $interval;
    }

    /** @return array{float, float, float, int} */
    private function offerPaySettings(): array
    {
        $city = $this->city;

        return [
            (float) ($city->rider_base_pay ?? 50),
            (float) ($city->rider_pay_boost ?? 10),
            (float) ($city->rider_pay_max ?? 100),
            max(1, (int) ($city->offer_boost_minutes ?? 2)),
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
     * Whether a customer can still self-cancel this order: only before the store has
     * started packing it for dispatch. Past that point, cancellation goes through support.
     */
    public function isCancellable(): bool
    {
        return ! in_array($this->order_status, [
            OrderStatus::Packed,
            OrderStatus::ReadyForDispatch,
            OrderStatus::Dispatched,
            OrderStatus::Delivered,
            OrderStatus::Cancelled,
            OrderStatus::Returned,
        ], true);
    }

    /**
     * Transition order status and record in status logs.
     */
    public function transitionOrderStatus(OrderStatus $newStatus, ?string $reason = null, ?int $userId = null): void
    {
        $oldStatus = $this->order_status;
        $this->order_status = $newStatus;

        // Packing has started and nobody is assigned: put the order on the riders' offer board.
        if (in_array($newStatus, DeliveryOfferService::OPEN_ORDER_STATUSES, true) && ! $this->delivery_agent_id && ! $this->offer_opened_at) {
            $this->offer_opened_at = now();
        }

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
