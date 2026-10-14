<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Copy customer accounts out of `users` into `customers`, preserving IDs
        //    so existing orders/addresses keep pointing at the same person.
        DB::table('users')->where('role', 'customer')->orderBy('id')->chunkById(200, function ($users) {
            $rows = [];

            foreach ($users as $user) {
                $data = (array) $user;

                $rows[] = [
                    'id' => $data['id'],
                    'name' => $data['name'],
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'email_verified_at' => $data['email_verified_at'] ?? null,
                    'phone_verified_at' => $data['phone_verified_at'] ?? null,
                    'password' => $data['password'] ?? null,
                    'provider' => $data['provider'] ?? null,
                    'provider_id' => $data['provider_id'] ?? null,
                    'cod_blocked' => $data['cod_blocked'] ?? false,
                    'remember_token' => $data['remember_token'] ?? null,
                    'created_at' => $data['created_at'] ?? now(),
                    'updated_at' => $data['updated_at'] ?? now(),
                ];
            }

            if (! empty($rows)) {
                DB::table('customers')->insertOrIgnore($rows);
            }
        });

        // 2. Repoint user_id -> customer_id on customer-owned tables. The old FK to
        //    `users` is dropped first — on SQLite that triggers Laravel's table
        //    rebuild, which is what actually frees the column for dropping.
        $this->repointColumn('orders', ['user_id', 'created_at'], ['customer_id', 'created_at']);
        $this->repointColumn('addresses', ['user_id', 'city_id'], ['customer_id', 'city_id']);
        $this->repointColumn('return_requests');

        // 3. Customers no longer live in `users`; it now holds staff accounts only.
        DB::table('users')->where('role', 'customer')->delete();
    }

    /**
     * Drop the users FK + indexes, add `customer_id`, copy the data over and
     * remove `user_id`. Guarded so a partially-applied run can be retried.
     *
     * @param  array<int, string>|null  $oldIndex  composite index to drop/recreate
     * @param  array<int, string>|null  $newIndex  replacement composite index
     */
    private function repointColumn(string $table, ?array $oldIndex = null, ?array $newIndex = null): void
    {
        // Drop the users foreign key first: on MySQL the FK depends on the
        // user_id index, so the index cannot be dropped while the FK exists.
        $hasUsersForeignKey = collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreignKey) => $foreignKey['columns'] === ['user_id']);

        if ($hasUsersForeignKey) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign(['user_id']));
        }

        $this->dropIndexesByColumns($table, array_filter([['user_id'], $oldIndex]));

        if (! Schema::hasColumn($table, 'customer_id')) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger('customer_id')->nullable());
        }

        if (Schema::hasColumn($table, 'user_id')) {
            DB::table($table)->whereNotNull('user_id')->update(['customer_id' => DB::raw('user_id')]);
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('user_id'));
        }

        $index = $newIndex ?? ['customer_id'];

        if (! Schema::hasIndex($table, $index)) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($index));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('customers')->orderBy('id')->chunkById(200, function ($customers) {
            $rows = [];

            foreach ($customers as $customer) {
                $data = (array) $customer;

                $rows[] = [
                    'id' => $data['id'],
                    'name' => $data['name'],
                    'email' => $data['email'] ?? ('customer-'.$data['id'].'@restored.local'),
                    'password' => $data['password'] ?? '',
                    'role' => 'customer',
                    'phone' => $data['phone'] ?? null,
                    'phone_verified_at' => $data['phone_verified_at'] ?? null,
                    'email_verified_at' => $data['email_verified_at'] ?? null,
                    'provider' => $data['provider'] ?? null,
                    'provider_id' => $data['provider_id'] ?? null,
                    'cod_blocked' => $data['cod_blocked'] ?? false,
                    'remember_token' => $data['remember_token'] ?? null,
                    'created_at' => $data['created_at'] ?? now(),
                    'updated_at' => $data['updated_at'] ?? now(),
                ];
            }

            if (! empty($rows)) {
                DB::table('users')->insertOrIgnore($rows);
            }
        });

        $this->restoreColumn('orders', ['customer_id', 'created_at'], ['user_id', 'created_at']);
        $this->restoreColumn('addresses', ['customer_id', 'city_id'], ['user_id', 'city_id']);
        $this->restoreColumn('return_requests');
    }

    /**
     * @param  array<int, string>|null  $oldIndex  composite index to drop/recreate
     * @param  array<int, string>|null  $newIndex  replacement composite index
     */
    private function restoreColumn(string $table, ?array $oldIndex = null, ?array $newIndex = null): void
    {
        $this->dropIndexesByColumns($table, array_filter([['customer_id'], $oldIndex]));

        if (! Schema::hasColumn($table, 'user_id')) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger('user_id')->nullable());
        }

        if (Schema::hasColumn($table, 'customer_id')) {
            DB::table($table)->whereNotNull('customer_id')->update(['user_id' => DB::raw('customer_id')]);
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('customer_id'));
        }

        $index = $newIndex ?? ['user_id'];

        if (! Schema::hasIndex($table, $index)) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($index));
        }

        $hasUsersForeignKey = collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreignKey) => $foreignKey['columns'] === ['user_id']);

        if (! $hasUsersForeignKey) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->foreign('user_id')->references('id')->on('users')->nullOnDelete());
        }
    }

    /**
     * Drop indexes by their column set, resolving the real index name first.
     *
     * MySQL names the implicit index it creates for a foreign key after the
     * constraint (e.g. `return_requests_user_id_foreign`), so guessing the
     * conventional `{table}_{columns}_index` name fails. Drop by actual name.
     *
     * @param  array<int, array<int, string>>  $columnSets
     */
    private function dropIndexesByColumns(string $table, array $columnSets): void
    {
        $indexes = collect(Schema::getIndexes($table));

        foreach ($columnSets as $columns) {
            $match = $indexes->first(
                fn (array $index) => ! $index['primary'] && $index['columns'] === $columns
            );

            if (! $match) {
                continue;
            }

            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($match['name']));
            $indexes = $indexes->reject(fn (array $index) => $index['name'] === $match['name']);
        }
    }
};
