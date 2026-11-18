<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->decimal('rider_base_pay', 8, 2)->default(50);
            $table->decimal('rider_pay_boost', 8, 2)->default(10);
            $table->decimal('rider_pay_max', 8, 2)->default(100);
            $table->unsignedSmallInteger('offer_boost_minutes')->default(2);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('offer_opened_at')->nullable();
            $table->decimal('rider_pay', 8, 2)->nullable();
        });

        // Orders already being packed at deploy time go straight onto the riders' offer board.
        DB::table('orders')
            ->whereNull('delivery_agent_id')
            ->whereIn('order_status', ['processing', 'packed', 'ready_for_dispatch'])
            ->update(['offer_opened_at' => now()]);

        Schema::create('order_offer_declines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_agent_id')->constrained()->cascadeOnDelete();
            $table->decimal('declined_pay', 8, 2);
            $table->timestamps();
            $table->unique(['order_id', 'delivery_agent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_offer_declines');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['offer_opened_at', 'rider_pay']));
        Schema::table('cities', fn (Blueprint $table) => $table->dropColumn(['rider_base_pay', 'rider_pay_boost', 'rider_pay_max', 'offer_boost_minutes']));
    }
};
