<?php

namespace App\Services\Verification;

use App\Enums\LocationChangeStatus;
use App\Exceptions\VerificationException;
use App\Models\AuditLog;
use App\Models\LocationChangeRequest;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Sms\SmsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/** Approved riders / vendors have a fixed location; they ask to change it and an admin decides. */
class LocationChangeService
{
    public function __construct(protected SmsService $sms) {}

    public function request(Model $party, array $proposed, string $reason): LocationChangeRequest
    {
        if (! $party->isApproved()) {
            throw new VerificationException('Only approved accounts can request a location change.');
        }

        if ($party->locationChangeRequests()->where('status', LocationChangeStatus::Pending)->exists()) {
            throw new VerificationException('You already have a pending location change request.');
        }

        $fields = $party->locationAttributes();
        $proposed = Arr::only($proposed, $fields);

        if (array_key_exists('home_warehouse_id', $proposed)) {
            $cityId = $proposed['city_id'] ?? $party->city_id;
            $servesCity = Warehouse::whereKey($proposed['home_warehouse_id'])
                ->whereHas('cities', fn ($q) => $q->whereKey($cityId))
                ->exists();

            if (! $servesCity) {
                throw new VerificationException('The chosen hub does not serve that city.');
            }
        }

        $request = $party->locationChangeRequests()->create([
            'current' => Arr::only($party->attributesToArray(), $fields),
            'proposed' => $proposed,
            'reason' => $reason,
            'status' => LocationChangeStatus::Pending,
        ]);

        AuditLog::record('location_change_requested', $request, null, $proposed, $reason);

        return $request;
    }

    public function approve(LocationChangeRequest $request, User $actor, ?string $note = null): void
    {
        $this->assertPending($request);

        DB::transaction(function () use ($request, $actor, $note) {
            $party = $request->requestable;
            $before = Arr::only($party->attributesToArray(), array_keys($request->proposed));

            $party->update($request->proposed);
            $this->close($request, LocationChangeStatus::Approved, $actor, $note);

            AuditLog::record('location_change_approved', $party, $before, $request->proposed, $note, $actor->id);
        });

        $this->sms->send($request->requestable->phone, 'DarkStore: your location change request was approved.');
    }

    public function reject(LocationChangeRequest $request, User $actor, string $note): void
    {
        $this->assertPending($request);

        $this->close($request, LocationChangeStatus::Rejected, $actor, $note);
        AuditLog::record('location_change_rejected', $request, null, null, $note, $actor->id);

        $this->sms->send($request->requestable->phone, "DarkStore: your location change request was declined. {$note}");
    }

    private function assertPending(LocationChangeRequest $request): void
    {
        if ($request->status !== LocationChangeStatus::Pending) {
            throw new VerificationException('This request has already been decided.');
        }
    }

    private function close(LocationChangeRequest $request, LocationChangeStatus $status, User $actor, ?string $note): void
    {
        $request->update([
            'status' => $status,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);
    }
}
