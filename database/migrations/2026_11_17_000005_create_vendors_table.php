<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();

            // Business identity
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('business_type');
            $table->text('registration_number')->nullable(); // encrypted cast
            $table->string('registration_authority')->nullable();
            $table->string('pan_number', 9)->unique(); // public business data in Nepal; kept plain so it can be unique
            $table->boolean('vat_registered')->default(false);
            $table->boolean('sells_food')->default(false);
            $table->string('authorized_person_name')->nullable();
            $table->string('authorized_person_designation')->nullable();

            // Login (vendor guard)
            $table->string('phone', 20)->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamp('phone_verified_at')->nullable();

            // Fixed pickup location
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->string('pickup_province')->nullable();
            $table->string('pickup_district')->nullable();
            $table->string('pickup_municipality')->nullable();
            $table->string('pickup_ward')->nullable();
            $table->string('pickup_tole')->nullable();
            $table->decimal('pickup_latitude', 10, 7)->nullable();
            $table->decimal('pickup_longitude', 10, 7)->nullable();

            // Payout
            $table->string('payout_method')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->text('bank_account_number')->nullable(); // encrypted cast
            $table->string('wallet_id', 20)->nullable();

            // Verification
            $table->string('status')->default('active'); // admin hard block: inactive
            $table->string('verification_status')->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->string('agreement_version')->nullable();
            $table->timestamp('agreement_accepted_at')->nullable();
            $table->string('agreement_ip', 45)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['verification_status', 'status'], 'vendors_verification_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
