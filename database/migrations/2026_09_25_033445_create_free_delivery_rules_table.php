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
        Schema::create('free_delivery_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('city_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('min_subtotal', 10, 2)->nullable();
            $table->unsignedInteger('min_quantity')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'city_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('free_delivery_rules');
    }
};
