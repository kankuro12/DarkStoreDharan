<?php

namespace Tests\Feature\Kyc;

use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesOnboardingFixtures;
use Tests\TestCase;

class HasKycTest extends TestCase
{
    use CreatesOnboardingFixtures, RefreshDatabase;

    public function test_one_of_group_is_satisfied_by_either_type(): void
    {
        $rider = $this->makeRider(); // bicycle groups: [[ProfilePhoto], [Citizenship, NationalId]]

        $rider->upsertKycDocument(KycDocumentType::ProfilePhoto, ['file_front' => 'kyc/x.jpg']);
        $this->assertEquals([[KycDocumentType::Citizenship, KycDocumentType::NationalId]], $rider->missingDocumentGroups());

        $rider->upsertKycDocument(KycDocumentType::NationalId, ['file_front' => 'a', 'file_back' => 'b', 'document_number' => '1234567890']);
        $this->assertSame([], $rider->missingDocumentGroups());
        $this->assertNotSame([], $rider->missingDocumentGroups(approvedOnly: true));
    }

    public function test_rejected_document_does_not_satisfy_its_group(): void
    {
        $rider = $this->makeRider();
        $rider->upsertKycDocument(KycDocumentType::ProfilePhoto, ['file_front' => 'a.jpg'])
            ->update(['status' => KycDocumentStatus::Rejected]);

        $this->assertContains([KycDocumentType::ProfilePhoto], $rider->missingDocumentGroups());
    }

    public function test_reupload_replaces_row_and_resets_to_pending(): void
    {
        $rider = $this->makeRider();
        $doc = $rider->upsertKycDocument(KycDocumentType::ProfilePhoto, ['file_front' => 'old.jpg']);
        $doc->update(['status' => KycDocumentStatus::Rejected, 'rejection_reason' => 'blurry']);

        $rider->upsertKycDocument(KycDocumentType::ProfilePhoto, ['file_front' => 'new.jpg']);

        $this->assertSame(1, $rider->kycDocuments()->count());
        $this->assertSame(KycDocumentStatus::Pending, $doc->fresh()->status);
        $this->assertNull($doc->fresh()->rejection_reason);
        $this->assertSame('new.jpg', $doc->fresh()->file_front);
        $this->assertSame(2, AuditLog::where('action', 'kyc_document_uploaded')->count());
    }

    public function test_document_number_is_encrypted_at_rest_and_kept_out_of_audit_log(): void
    {
        $rider = $this->makeRider();
        $doc = $rider->upsertKycDocument(KycDocumentType::NationalId, ['file_front' => 'a', 'document_number' => '1234567890']);

        $this->assertStringNotContainsString('1234567890', DB::table('kyc_documents')->value('document_number'));
        $this->assertSame('1234567890', $doc->fresh()->document_number);
        $this->assertStringNotContainsString('1234567890', (string) DB::table('audit_logs')->value('after'));
    }
}
