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
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('province')->nullable();
            $table->string('status')->default('active'); // active, inactive
            $table->boolean('cod_enabled')->default(true);
            $table->boolean('prepaid_enabled')->default(true);
            $table->decimal('default_delivery_fee', 8, 2)->default(50.00);
            $table->decimal('free_delivery_minimum', 8, 2)->nullable();
            $table->unsignedInteger('estimated_delivery_minutes')->default(45);
            $table->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status')->default('active'); // active, inactive
            $table->timestamps();
        });

        Schema::create('warehouse_city', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('priority')->default(1);
            $table->unsignedInteger('delivery_minutes')->default(45);
            $table->decimal('delivery_fee', 8, 2)->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['warehouse_id', 'city_id']);
            $table->index(['city_id', 'status', 'priority']);
        });

        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('postal_code')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('radius_km', 6, 2)->default(5.00);
            $table->decimal('delivery_fee', 8, 2)->default(50.00);
            $table->decimal('minimum_order', 8, 2)->default(0.00);
            $table->unsignedInteger('estimated_minutes')->default(45);
            $table->boolean('cod_enabled')->default(true);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['city_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('warehouse_city');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('cities');
    }
};
