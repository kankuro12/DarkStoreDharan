<?php

namespace App\Services\Verification;

use App\Enums\KycDocumentStatus;
use App\Enums\VerificationStatus;
use App\Exceptions\VerificationException;
use App\Models\AuditLog;
use App\Models\DeliveryAgent;
use App\Models\KycDocument;
use App\Models\User;
use App\Services\Sms\SmsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Onboarding state machine shared by riders and vendors (any model using HasKyc).
 * Every transition is validated, audited and texted to the party.
 */
class VerificationService
{
    public const EXPIRY_REASON_PREFIX = 'Document expired:';

    public function __construct(protected SmsService $sms) {}

    public function submit(Model $party, string $agreementVersion, string $ip): void
    {
        if ($party->missingDocumentGroups() !== []) {
            throw new VerificationException('Upload every required document before submitting.');
        }

        $this->transition($party, VerificationStatus::Submitted, null, null, [
            'submitted_at' => now(),
            'agreement_version' => $agreementVersion,
            'agreement_accepted_at' => now(),
            'agreement_ip' => $ip,
            'rejection_reason' => null,
        ]);
    }

    public function startReview(Model $party, User $actor): void
    {
        $this->transition($party, VerificationStatus::UnderReview, $actor);
    }

    public function approveDocument(KycDocument $document, User $actor): void
    {
        $this->reviewDocument($document, $actor, KycDocumentStatus::Approved, null);
    }

    public function rejectDocument(KycDocument $document, User $actor, string $reason): void
    {
        $this->reviewDocument($document, $actor, KycDocumentStatus::Rejected, $reason);
    }

    public function requestChanges(Model $party, User $actor, string $reason): void
    {
        if (! $party->kycDocuments()->where('status', KycDocumentStatus::Rejected)->exists()) {
            throw new VerificationException('Reject at least one document before requesting changes.');
        }

        $this->transition($party, VerificationStatus::NeedsChanges, $actor, $reason, ['rejection_reason' => $reason]);
    }

    public function approve(Model $party, User $actor): void
    {
        $this->assertCanTransition($party, VerificationStatus::Approved);

        if ($party->missingDocumentGroups(approvedOnly: true) !== []) {
            throw new VerificationException('Every required document must be approved first.');
        }

        $this->transition($party, VerificationStatus::Approved, $actor, null, [
            'approved_at' => now(),
            'approved_by' => $actor->id,
            'rejection_reason' => null,
        ]);
    }

    public function reject(Model $party, User $actor, string $reason): void
    {
        $this->transition($party, VerificationStatus::Rejected, $actor, $reason, ['rejection_reason' => $reason]);
    }

    /** $actor is null when the system suspends (e.g. an expired document). Returns released order count for riders. */
    public function suspend(Model $party, ?User $actor, string $reason): int
    {
        $this->transition($party, VerificationStatus::Suspended, $actor, $reason, ['rejection_reason' => $reason]);

        return $party instanceof DeliveryAgent ? $party->releaseUnstartedDeliveries() : 0;
    }

    public function reactivate(Model $party, User $actor): void
    {
        $this->transition($party, VerificationStatus::Approved, $actor, null, ['rejection_reason' => null]);
    }

    public function reopen(Model $party, User $actor, string $reason): void
    {
        if ($party->verification_status !== VerificationStatus::Rejected) {
            throw new VerificationException('Only rejected applications can be reopened.');
        }

        $this->transition($party, VerificationStatus::NeedsChanges, $actor, $reason, ['rejection_reason' => $reason]);
    }

    /** A party suspended only for an expired document may re-upload it themselves; admin suspensions stay admin-only. */
    public function resubmitAfterExpiry(Model $party): void
    {
        if ($party->verification_status !== VerificationStatus::Suspended
            || ! str_starts_with((string) $party->rejection_reason, self::EXPIRY_REASON_PREFIX)) {
            throw new VerificationException('This suspension can only be lifted by an administrator.');
        }

        $this->transition($party, VerificationStatus::NeedsChanges);
    }

    protected function reviewDocument(KycDocument $document, User $actor, KycDocumentStatus $status, ?string $reason): void
    {
        $before = $document->auditSnapshot();

        $document->update([
            'status' => $status,
            'rejection_reason' => $reason,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        AuditLog::record('kyc_document_'.$status->value, $document, $before, $document->auditSnapshot(), $reason, $actor->id);
    }

    protected function assertCanTransition(Model $party, VerificationStatus $to): void
    {
        if (! $party->verification_status->canTransitionTo($to)) {
            throw new VerificationException("Cannot move from {$party->verification_status->label()} to {$to->label()}.");
        }
    }

    protected function transition(Model $party, VerificationStatus $to, ?User $actor = null, ?string $reason = null, array $extra = []): void
    {
        $this->assertCanTransition($party, $to);
        $from = $party->verification_status;

        DB::transaction(function () use ($party, $to, $actor, $reason, $extra, $from) {
            $party->update(array_merge($extra, ['verification_status' => $to]));

            AuditLog::record(
                'verification_'.$to->value,
                $party,
                ['verification_status' => $from->value],
                ['verification_status' => $to->value],
                $reason,
                $actor?->id,
            );
        });

        $label = $party instanceof DeliveryAgent ? 'rider' : 'supplier';
        $this->sms->send(
            $party->phone,
            "DarkStore: your {$label} account is now {$to->label()}.".($reason ? " Note: {$reason}" : ''),
        );
    }
}
