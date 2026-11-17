<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VerificationStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case NeedsChanges = 'needs_changes';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, match ($this) {
            self::Draft => [self::Submitted],
            self::Submitted => [self::UnderReview],
            self::UnderReview => [self::Approved, self::NeedsChanges, self::Rejected],
            self::NeedsChanges => [self::Submitted],
            self::Approved => [self::Suspended],
            self::Rejected => [self::NeedsChanges],
            // NeedsChanges only for expired-document recovery; VerificationService enforces that.
            self::Suspended => [self::Approved, self::NeedsChanges],
        }, true);
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::NeedsChanges], true);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted, self::UnderReview => 'info',
            self::NeedsChanges => 'warning',
            self::Approved => 'success',
            self::Rejected, self::Suspended => 'danger',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under Review',
            self::NeedsChanges => 'Needs Changes',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Suspended => 'Suspended',
        };
    }
}
