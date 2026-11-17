<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('category_id')->constrained('vendors')->nullOnDelete();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('supply_price', 10, 2)->nullable()->after('price');
        });

        // Settlement snapshot: who supplied the unit and at what agreed cost, frozen at checkout.
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('variant_id')->constrained('vendors')->nullOnDelete();
            $table->decimal('unit_supply_price', 10, 2)->nullable()->after('unit_price');
            $table->index(['vendor_id', 'order_id'], 'order_items_vendor_order_idx');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropIndex('order_items_vendor_order_idx');
            $table->dropColumn(['vendor_id', 'unit_supply_price']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('supply_price');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropColumn('vendor_id');
        });
    }
};
