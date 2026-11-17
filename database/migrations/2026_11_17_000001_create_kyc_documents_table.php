<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');
            $table->string('type');
            $table->text('document_number')->nullable(); // encrypted cast
            $table->string('issuing_authority')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('file_front');
            $table->string('file_back')->nullable();
            $table->json('meta')->nullable();
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['documentable_type', 'documentable_id', 'type'], 'kyc_docs_party_type_unique');
            $table->index(['status', 'expires_on'], 'kyc_docs_status_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_documents');
    }
};
