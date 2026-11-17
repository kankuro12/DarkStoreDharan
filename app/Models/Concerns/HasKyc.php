<?php

namespace App\Models\Concerns;

use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use App\Models\AuditLog;
use App\Models\KycDocument;
use App\Models\LocationChangeRequest;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * Shared KYC behaviour for parties that self-onboard (riders, vendors).
 * The model needs the verification columns: verification_status, submitted_at, approved_at,
 * approved_by, rejection_reason, agreement_version, agreement_accepted_at, agreement_ip, status.
 */
trait HasKyc
{
    /**
     * Each inner list is one requirement satisfied by ANY one of its document types.
     *
     * @return array<int, list<KycDocumentType>>
     */
    abstract public function requiredDocumentGroups(): array;

    /**
     * The party's fixed-location columns; changed only through an approved LocationChangeRequest
     * (or directly by an admin).
     *
     * @return list<string>
     */
    abstract public function locationAttributes(): array;

    public function kycDocuments(): MorphMany
    {
        return $this->morphMany(KycDocument::class, 'documentable');
    }

    public function locationChangeRequests(): MorphMany
    {
        return $this->morphMany(LocationChangeRequest::class, 'requestable');
    }

    /**
     * Groups not yet satisfied. Without $approvedOnly, a pending or approved document counts
     * (enough to submit); with it, only approved documents count (enough to approve).
     *
     * @return array<int, list<KycDocumentType>>
     */
    public function missingDocumentGroups(bool $approvedOnly = false): array
    {
        $accepted = $approvedOnly
            ? [KycDocumentStatus::Approved]
            : [KycDocumentStatus::Pending, KycDocumentStatus::Approved];

        $present = $this->kycDocuments()->get()
            ->filter(fn (KycDocument $doc) => in_array($doc->status, $accepted, true))
            ->map(fn (KycDocument $doc) => $doc->type)
            ->all();

        return array_values(array_filter(
            $this->requiredDocumentGroups(),
            fn (array $group) => array_intersect(
                array_map(fn ($t) => $t->value, $group),
                array_map(fn ($t) => $t->value, $present),
            ) === [],
        ));
    }

    /** Approved documents expiring within the renewal window: the party may upload a replacement early. */
    public function renewableDocuments(int $withinDays = 30): Collection
    {
        return $this->kycDocuments
            ->filter(fn (KycDocument $doc) => $doc->status === KycDocumentStatus::Approved
                && $doc->expires_on
                && $doc->expires_on->lte(today()->addDays($withinDays)))
            ->values();
    }

    /** Create or replace the party's document of this type; a replacement goes back to pending review. */
    public function upsertKycDocument(KycDocumentType $type, array $attributes): KycDocument
    {
        $document = $this->kycDocuments()->firstOrNew(['type' => $type]);
        $before = $document->exists ? $document->auditSnapshot() : null;

        $document->fill(array_merge($attributes, [
            'status' => KycDocumentStatus::Pending,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]))->save();

        AuditLog::record('kyc_document_uploaded', $document, $before, $document->auditSnapshot());

        return $document;
    }
}
