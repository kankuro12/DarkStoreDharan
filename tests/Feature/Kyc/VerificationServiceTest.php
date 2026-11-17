<?php

namespace Tests\Feature\Kyc;

use App\Enums\KycDocumentType;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Exceptions\VerificationException;
use App\Models\AuditLog;
use App\Models\DeliveryAgent;
use App\Models\User;
use App\Services\Sms\SmsService;
use App\Services\Verification\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOnboardingFixtures;
use Tests\TestCase;

class VerificationServiceTest extends TestCase
{
    use CreatesOnboardingFixtures, RefreshDatabase;

    private VerificationService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(SmsService::class)->shouldReceive('send')->andReturnTrue();
        $this->service = app(VerificationService::class);
        $this->admin = $this->makeStaff(UserRole::SuperAdmin);
    }

    private function riderWithDocuments(): DeliveryAgent
    {
        $rider = $this->makeRider();
        $rider->upsertKycDocument(KycDocumentType::ProfilePhoto, ['file_front' => 'p.jpg']);
        $rider->upsertKycDocument(KycDocumentType::Citizenship, ['file_front' => 'f.jpg', 'file_back' => 'b.jpg', 'document_number' => '12-01-74-01234']);

        return $rider;
    }

    public function test_submit_fails_when_required_documents_missing(): void
    {
        $this->expectException(VerificationException::class);
        $this->service->submit($this->makeRider(), 'v1', '10.0.0.1');
    }

    public function test_submit_records_agreement_and_moves_to_submitted(): void
    {
        $rider = $this->riderWithDocuments();

        $this->service->submit($rider, 'v1', '10.0.0.1');

        $rider->refresh();
        $this->assertSame(VerificationStatus::Submitted, $rider->verification_status);
        $this->assertSame('10.0.0.1', $rider->agreement_ip);
        $this->assertSame('v1', $rider->agreement_version);
        $this->assertNotNull($rider->agreement_accepted_at);
        $this->assertNotNull($rider->submitted_at);
    }

    public function test_approve_fails_while_any_required_document_unapproved(): void
    {
        $rider = $this->riderWithDocuments();
        $this->service->submit($rider, 'v1', '10.0.0.1');
        $this->service->startReview($rider, $this->admin);

        $this->expectException(VerificationException::class);
        $this->service->approve($rider, $this->admin);
    }

    public function test_full_happy_path_writes_audit_log_and_sms(): void
    {
        $sms = $this->mock(SmsService::class);
        $sms->shouldReceive('send')->withArgs(fn ($to, $msg) => str_contains($msg, 'Approved'))->once()->andReturnTrue();
        $sms->shouldReceive('send')->andReturnTrue();
        $service = app(VerificationService::class);

        $rider = $this->riderWithDocuments();
        $service->submit($rider, 'v1', '10.0.0.1');
        $service->startReview($rider, $this->admin);
        $rider->kycDocuments->each(fn ($doc) => $service->approveDocument($doc, $this->admin));
        $service->approve($rider, $this->admin);

        $rider->refresh();
        $this->assertSame(VerificationStatus::Approved, $rider->verification_status);
        $this->assertSame($this->admin->id, $rider->approved_by);
        $this->assertTrue(AuditLog::where('action', 'verification_approved')->where('user_id', $this->admin->id)->exists());
        $this->assertSame(2, AuditLog::where('action', 'kyc_document_approved')->count());
    }

    public function test_illegal_transition_throws(): void
    {
        $this->expectException(VerificationException::class);
        $this->service->approve($this->makeRider(), $this->admin); // draft → approved
    }

    public function test_request_changes_requires_a_rejected_document(): void
    {
        $rider = $this->riderWithDocuments();
        $this->service->submit($rider, 'v1', '10.0.0.1');
        $this->service->startReview($rider, $this->admin);

        try {
            $this->service->requestChanges($rider, $this->admin, 'fix it');
            $this->fail('Expected VerificationException');
        } catch (VerificationException) {
        }

        $this->service->rejectDocument($rider->kycDocuments()->first(), $this->admin, 'Blurry photo');
        $this->service->requestChanges($rider, $this->admin, 'Please re-upload the blurry photo');

        $this->assertSame(VerificationStatus::NeedsChanges, $rider->fresh()->verification_status);
        $this->assertSame('Please re-upload the blurry photo', $rider->fresh()->rejection_reason);
    }

    public function test_rejected_can_be_reopened_to_needs_changes(): void
    {
        $rider = $this->riderWithDocuments();
        $this->service->submit($rider, 'v1', '10.0.0.1');
        $this->service->startReview($rider, $this->admin);
        $this->service->reject($rider, $this->admin, 'Fake documents');
        $this->assertSame(VerificationStatus::Rejected, $rider->fresh()->verification_status);

        $this->service->reopen($rider, $this->admin, 'Applicant provided originals');

        $this->assertSame(VerificationStatus::NeedsChanges, $rider->fresh()->verification_status);
    }

    public function test_admin_suspension_cannot_be_self_lifted_but_expiry_suspension_can(): void
    {
        $rider = $this->makeRider(['verification_status' => VerificationStatus::Approved]);
        $this->service->suspend($rider, $this->admin, 'Misconduct');

        try {
            $this->service->resubmitAfterExpiry($rider);
            $this->fail('Expected VerificationException');
        } catch (VerificationException) {
        }

        $this->service->reactivate($rider, $this->admin);
        $this->service->suspend($rider, null, 'Document expired: Driving License');
        $this->service->resubmitAfterExpiry($rider);

        $this->assertSame(VerificationStatus::NeedsChanges, $rider->fresh()->verification_status);
    }
}
