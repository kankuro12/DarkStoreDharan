<?php

namespace Tests\Unit;

use App\Enums\VerificationStatus;
use PHPUnit\Framework\TestCase;

class VerificationStatusTest extends TestCase
{
    public function test_allowed_transitions(): void
    {
        $this->assertTrue(VerificationStatus::Draft->canTransitionTo(VerificationStatus::Submitted));
        $this->assertTrue(VerificationStatus::NeedsChanges->canTransitionTo(VerificationStatus::Submitted));
        $this->assertTrue(VerificationStatus::UnderReview->canTransitionTo(VerificationStatus::Approved));
        $this->assertTrue(VerificationStatus::Approved->canTransitionTo(VerificationStatus::Suspended));
        $this->assertTrue(VerificationStatus::Suspended->canTransitionTo(VerificationStatus::Approved));
        $this->assertTrue(VerificationStatus::Rejected->canTransitionTo(VerificationStatus::NeedsChanges));
        $this->assertTrue(VerificationStatus::Suspended->canTransitionTo(VerificationStatus::NeedsChanges));
        $this->assertFalse(VerificationStatus::Draft->canTransitionTo(VerificationStatus::Approved));
        $this->assertFalse(VerificationStatus::Submitted->canTransitionTo(VerificationStatus::Approved));
        $this->assertFalse(VerificationStatus::Approved->canTransitionTo(VerificationStatus::Draft));
    }

    public function test_only_draft_and_needs_changes_are_editable(): void
    {
        $editable = array_values(array_filter(VerificationStatus::cases(), fn ($s) => $s->isEditable()));

        $this->assertSame([VerificationStatus::Draft, VerificationStatus::NeedsChanges], $editable);
    }
}
