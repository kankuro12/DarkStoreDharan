<?php

namespace App\Console\Commands;

use App\Enums\KycDocumentStatus;
use App\Enums\VerificationStatus;
use App\Models\AuditLog;
use App\Models\KycDocument;
use App\Services\Sms\SmsService;
use App\Services\Verification\VerificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Daily: remind riders/vendors before a document expires, mark expired documents, and suspend
 * approved parties whose required documents are no longer all valid (they can re-upload themselves).
 */
#[Signature('kyc:check-expiry')]
#[Description('Send KYC expiry reminders, expire documents and suspend non-compliant riders/vendors')]
class CheckKycExpiry extends Command
{
    public const REMINDER_DAYS = [30, 7];

    public function handle(SmsService $sms, VerificationService $verification): int
    {
        $reminded = $this->sendReminders($sms);
        $expiredDocuments = $this->expireDocuments();
        $suspended = $this->suspendNonCompliant($expiredDocuments, $verification);

        $this->info("reminded={$reminded} expired={$expiredDocuments->count()} suspended={$suspended}");

        return self::SUCCESS;
    }

    private function sendReminders(SmsService $sms): int
    {
        $sent = 0;

        foreach (self::REMINDER_DAYS as $days) {
            $documents = KycDocument::with('documentable')
                ->where('status', KycDocumentStatus::Approved)
                ->whereDate('expires_on', today()->addDays($days))
                ->get();

            foreach ($documents as $document) {
                $meta = $document->meta ?? [];
                if (in_array($days, $meta['reminded_days'] ?? [], true) || ! $document->documentable) {
                    continue;
                }

                $sms->send(
                    $document->documentable->phone,
                    "DarkStore: your {$document->type->label()} expires in {$days} days ({$document->expires_on->toDateString()}). Renew it and upload the new copy to stay active."
                );

                $meta['reminded_days'] = [...($meta['reminded_days'] ?? []), $days];
                $document->update(['meta' => $meta]);
                $sent++;
            }
        }

        return $sent;
    }

    /** @return Collection<int, KycDocument> */
    private function expireDocuments()
    {
        $documents = KycDocument::with('documentable')
            ->whereNotNull('expires_on')
            ->whereDate('expires_on', '<', today())
            ->where('status', '!=', KycDocumentStatus::Expired)
            ->get();

        foreach ($documents as $document) {
            $before = $document->auditSnapshot();
            $document->update(['status' => KycDocumentStatus::Expired]);
            AuditLog::record('kyc_document_expired', $document, $before, $document->auditSnapshot());
        }

        return $documents;
    }

    private function suspendNonCompliant($expiredDocuments, VerificationService $verification): int
    {
        $suspended = 0;

        foreach ($expiredDocuments->groupBy(fn (KycDocument $d) => $d->documentable_type.':'.$d->documentable_id) as $documents) {
            $party = $documents->first()->documentable?->fresh();

            if (! $party || $party->verification_status !== VerificationStatus::Approved) {
                continue;
            }

            $missing = collect($party->missingDocumentGroups(approvedOnly: true))->flatten();
            $expiredRequired = $documents->first(fn (KycDocument $d) => $missing->contains($d->type));

            if (! $expiredRequired) {
                continue; // only an optional document expired
            }

            $verification->suspend($party, null, VerificationService::EXPIRY_REASON_PREFIX.' '.$expiredRequired->type->label());
            $suspended++;
        }

        return $suspended;
    }
}
