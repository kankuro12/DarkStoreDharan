<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ADDRESS = ['province', 'district', 'municipality', 'ward', 'tole'];

    public function up(): void
    {
        Schema::table('delivery_agents', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();

            foreach (['perm', 'temp'] as $prefix) {
                foreach (self::ADDRESS as $field) {
                    $table->string("{$prefix}_{$field}")->nullable();
                }
            }

            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_relation')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();

            $table->foreignId('home_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->decimal('base_latitude', 10, 7)->nullable();
            $table->decimal('base_longitude', 10, 7)->nullable();

            $table->string('payout_method')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->text('bank_account_number')->nullable(); // encrypted cast
            $table->string('wallet_id', 20)->nullable();

            $table->unique('phone', 'delivery_agents_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_agents', function (Blueprint $table) {
            $table->dropUnique('delivery_agents_phone_unique');
            $table->dropForeign(['home_warehouse_id']);

            $columns = [
                'phone_verified_at', 'vehicle_type', 'date_of_birth', 'gender',
                'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
                'home_warehouse_id', 'base_latitude', 'base_longitude',
                'payout_method', 'bank_name', 'bank_account_name', 'bank_account_number', 'wallet_id',
            ];
            foreach (['perm', 'temp'] as $prefix) {
                foreach (self::ADDRESS as $field) {
                    $columns[] = "{$prefix}_{$field}";
                }
            }

            $table->dropColumn($columns);
        });
    }
};
