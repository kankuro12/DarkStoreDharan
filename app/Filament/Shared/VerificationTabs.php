<?php

namespace App\Filament\Shared;

use App\Enums\VerificationStatus;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/** List tabs by verification status, shared by the rider and vendor admin lists. */
class VerificationTabs
{
    public static function make(): array
    {
        $where = fn (VerificationStatus ...$statuses) => fn (Builder $query) => $query->whereIn(
            'verification_status',
            array_map(fn ($s) => $s->value, $statuses),
        );

        return [
            'pending_review' => Tab::make('Pending review')->modifyQueryUsing($where(VerificationStatus::Submitted, VerificationStatus::UnderReview)),
            'needs_changes' => Tab::make('Needs changes')->modifyQueryUsing($where(VerificationStatus::NeedsChanges)),
            'approved' => Tab::make('Approved')->modifyQueryUsing($where(VerificationStatus::Approved)),
            'suspended' => Tab::make('Suspended')->modifyQueryUsing($where(VerificationStatus::Suspended)),
            'rejected' => Tab::make('Rejected')->modifyQueryUsing($where(VerificationStatus::Rejected)),
            'draft' => Tab::make('Draft')->modifyQueryUsing($where(VerificationStatus::Draft)),
            'all' => Tab::make('All'),
        ];
    }
}
