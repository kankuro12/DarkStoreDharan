<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name');
            $table->text('phone'); // Encrypted at rest (§38.4)
            $table->string('area'); // e.g. "Bhanuchowk", "Pindeshwor", "Main Road"
            $table->string('street');
            $table->string('landmark')->nullable();
            $table->text('latitude')->nullable(); // Encrypted at rest (§38.4)
            $table->text('longitude')->nullable(); // Encrypted at rest (§38.4)
            $table->text('delivery_notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'city_id']);
        });

        Schema::create('delivery_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active'); // active, inactive
            $table->string('availability')->default('available'); // available, assigned, delivering, offline
            $table->decimal('current_latitude', 10, 7)->nullable();
            $table->decimal('current_longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->index(['city_id', 'availability']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('address_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_agent_id')->nullable()->constrained('delivery_agents')->nullOnDelete();

            // Monetary values (recalculated server-side §38.2)
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->decimal('delivery_fee', 10, 2)->default(0.00);
            $table->decimal('tax', 10, 2)->default(0.00);
            $table->decimal('grand_total', 10, 2);

            // Payment and fulfillment details
            $table->string('payment_method')->default('cod'); // cod, esewa, khalti, fonepay
            $table->string('order_status')->default('pending'); // pending, confirmed, processing, packed, ready_for_dispatch, dispatched, delivered, cancelled, returned
            $table->string('payment_status')->default('unpaid'); // unpaid, pending, paid, failed, refunded, partially_refunded
            $table->string('delivery_status')->default('pending'); // pending, assigned, picked_up, out_for_delivery, delivered, failed, returned

            // Timestamps for SLAs and lifecycle tracking
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('packed_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['city_id', 'order_status']);
            $table->index(['warehouse_id', 'order_status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            // Snapshot attributes (§16, §38.2) - never modified after creation
            $table->string('product_name');
            $table->string('sku');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->decimal('tax', 10, 2)->default(0.00);
            $table->decimal('total', 10, 2);
            $table->timestamps();

            $table->index(['order_id', 'variant_id']);
        });

        Schema::create('order_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status_type'); // order, payment, delivery
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'status_type']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_id')->nullable()->unique();
            $table->string('gateway'); // cod, esewa, khalti, fonepay
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending'); // pending, paid, failed, refunded
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->nullable()->index();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status')->default('reserved'); // reserved, confirmed, released, expired
            $table->dateTime('expires_at')->index();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_status_logs');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('delivery_agents');
        Schema::dropIfExists('addresses');
    }
};
