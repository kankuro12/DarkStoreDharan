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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('customer')->after('password');
            // Roles: super_admin, catalog_manager, warehouse_manager, picker_packer, delivery_coordinator, finance, support, customer (§31.2)
            $table->string('phone')->nullable()->after('role');
            $table->foreignId('warehouse_id')->nullable()->after('phone')->constrained()->nullOnDelete();
            $table->boolean('cod_blocked')->default(false)->after('warehouse_id'); // §11, §31.3
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn(['role', 'phone', 'warehouse_id', 'cod_blocked']);
        });
    }
};
