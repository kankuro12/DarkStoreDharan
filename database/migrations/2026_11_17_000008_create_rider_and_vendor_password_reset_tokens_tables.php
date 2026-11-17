<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each auth guard gets its own reset-token table. Tokens are keyed by email only, and emails are
 * unique per table, so a shared table would let a rider/vendor token reset a customer's password.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['rider_password_reset_tokens', 'vendor_password_reset_tokens'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_password_reset_tokens');
        Schema::dropIfExists('vendor_password_reset_tokens');
    }
};
