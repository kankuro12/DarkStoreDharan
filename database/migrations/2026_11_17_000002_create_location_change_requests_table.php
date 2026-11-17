<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_change_requests', function (Blueprint $table) {
            $table->id();
            $table->string('requestable_type');
            $table->unsignedBigInteger('requestable_id');
            $table->json('current');
            $table->json('proposed');
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['requestable_type', 'requestable_id', 'status'], 'loc_change_party_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_change_requests');
    }
};
