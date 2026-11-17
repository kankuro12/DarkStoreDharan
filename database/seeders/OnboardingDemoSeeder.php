<?php

namespace Database\Seeders;

use App\Enums\BusinessType;
use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use App\Enums\VerificationStatus;
use App\Models\City;
use App\Models\DeliveryAgent;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Demo riders and vendors in each review state so the onboarding screens have data.
 * Logins: rider.{state}@darkstore.np / vendor.{state}@darkstore.np, password "password".
 * Document files are placeholders (not on disk); upload real ones to try the file viewer.
 */
class OnboardingDemoSeeder extends Seeder
{
    private const STATES = [
        VerificationStatus::Draft,
        VerificationStatus::Submitted,
        VerificationStatus::NeedsChanges,
        VerificationStatus::Approved,
    ];

    public function run(): void
    {
        $city = City::first();
        $hub = Warehouse::first();

        if (! $city || ! $hub) {
            return;
        }

        foreach (self::STATES as $i => $state) {
            $this->rider($state, $i, $city, $hub);
            $vendor = $this->vendor($state, $i, $city);

            if ($state === VerificationStatus::Approved) {
                $product = Product::create([
                    'name' => 'Ilam Orthodox Tea 250g',
                    'slug' => 'ilam-orthodox-tea-250g',
                    'status' => 'pending_review',
                    'vendor_id' => $vendor->id,
                    'description' => 'Vendor submission awaiting catalog review.',
                ]);
                $product->variants()->create(['sku' => 'ILAM-TEA-250', 'name' => '250g', 'price' => 180, 'supply_price' => 180, 'status' => 'active']);
            }
        }
    }

    private function rider(VerificationStatus $state, int $i, City $city, Warehouse $hub): void
    {
        $rider = DeliveryAgent::create([
            'name' => 'Demo Rider '.ucfirst($state->value),
            'phone' => '981000000'.$i,
            'email' => "rider.{$state->value}@darkstore.np",
            'password' => 'password',
            'city_id' => $city->id,
            'home_warehouse_id' => $state === VerificationStatus::Draft ? null : $hub->id,
            'vehicle_type' => 'bicycle',
            'payout_method' => 'wallet',
            'wallet_id' => '981000000'.$i,
            'status' => 'active',
            'availability' => 'offline',
            'verification_status' => $state,
            'submitted_at' => $state === VerificationStatus::Draft ? null : now()->subDay(),
            'approved_at' => $state === VerificationStatus::Approved ? now() : null,
            'rejection_reason' => $state === VerificationStatus::NeedsChanges ? 'Please upload a clearer selfie.' : null,
            'phone_verified_at' => now(),
        ]);

        if ($state !== VerificationStatus::Draft) {
            $this->documents($rider, [KycDocumentType::ProfilePhoto, KycDocumentType::NationalId], $state);
        }
    }

    private function vendor(VerificationStatus $state, int $i, City $city): Vendor
    {
        $vendor = Vendor::create([
            'legal_name' => 'Demo Supplier '.ucfirst($state->value).' Pvt. Ltd.',
            'business_type' => BusinessType::PrivateLimited,
            'pan_number' => '60000000'.$i,
            'phone' => '982000000'.$i,
            'email' => "vendor.{$state->value}@darkstore.np",
            'password' => 'password',
            'city_id' => $city->id,
            'status' => 'active',
            'verification_status' => $state,
            'submitted_at' => $state === VerificationStatus::Draft ? null : now()->subDay(),
            'approved_at' => $state === VerificationStatus::Approved ? now() : null,
            'rejection_reason' => $state === VerificationStatus::NeedsChanges ? 'PAN certificate is unreadable.' : null,
            'phone_verified_at' => now(),
        ]);

        if ($state !== VerificationStatus::Draft) {
            $this->documents($vendor, [KycDocumentType::PanCertificate, KycDocumentType::CompanyRegistration, KycDocumentType::NationalId, KycDocumentType::BankProof], $state);
        }

        return $vendor;
    }

    /** @param  list<KycDocumentType>  $types */
    private function documents(DeliveryAgent|Vendor $party, array $types, VerificationStatus $state): void
    {
        foreach ($types as $index => $type) {
            $status = match ($state) {
                VerificationStatus::Approved => KycDocumentStatus::Approved,
                VerificationStatus::NeedsChanges => $index === 0 ? KycDocumentStatus::Rejected : KycDocumentStatus::Approved,
                default => KycDocumentStatus::Pending,
            };

            $party->kycDocuments()->create([
                'type' => $type,
                'document_number' => $type === KycDocumentType::PanCertificate ? $party->pan_number : '1234567890',
                'file_front' => "kyc/demo/{$type->value}.jpg",
                'status' => $status,
                'rejection_reason' => $status === KycDocumentStatus::Rejected ? $party->rejection_reason : null,
            ]);
        }
    }
}
