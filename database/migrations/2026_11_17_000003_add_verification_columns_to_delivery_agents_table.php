<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_agents', function (Blueprint $table) {
            $table->string('verification_status')->default('draft')->after('status');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->string('agreement_version')->nullable();
            $table->timestamp('agreement_accepted_at')->nullable();
            $table->string('agreement_ip', 45)->nullable();
            $table->timestamp('training_completed_at')->nullable();
        });

        // Riders that already exist were onboarded manually; keep them dispatchable.
        DB::table('delivery_agents')->update([
            'verification_status' => 'approved',
            'approved_at' => now(),
            'training_completed_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('delivery_agents', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'verification_status', 'submitted_at', 'approved_at', 'approved_by', 'rejection_reason',
                'agreement_version', 'agreement_accepted_at', 'agreement_ip', 'training_completed_at',
            ]);
        });
    }
};
