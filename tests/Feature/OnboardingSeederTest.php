<?php

namespace Tests\Feature;

use App\Enums\VerificationStatus;
use App\Models\DeliveryAgent;
use App\Models\Product;
use App\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_has_riders_and_vendors_in_every_review_state(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach ([VerificationStatus::Draft, VerificationStatus::Submitted, VerificationStatus::NeedsChanges, VerificationStatus::Approved] as $status) {
            $this->assertTrue(DeliveryAgent::where('verification_status', $status)->exists(), "rider {$status->value}");
            $this->assertTrue(Vendor::where('verification_status', $status)->exists(), "vendor {$status->value}");
        }

        $this->assertTrue(Product::where('status', 'pending_review')->whereNotNull('vendor_id')->exists());
        $this->assertSame(0, DeliveryAgent::eligibleForDispatch()->where('verification_status', '!=', 'approved')->count());
    }
}
