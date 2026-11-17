# Rider & Vendor Onboarding, Verification and Management — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let delivery riders and vendors sign up on their own, complete a multi-step, Nepal-specific KYC verification, and be fully controlled by admins. Riders and vendors each get their own login (auth guard), and customers never see vendors.

**Architecture:** Riders and vendors share one KYC framework: a polymorphic `kyc_documents` table, a polymorphic `location_change_requests` table, a `HasKyc` trait and a `VerificationService` state machine. Riders keep the existing `delivery` guard and `/rider` Filament panel. Vendors get a new `vendor` guard, `vendors` table and `/vendor` Filament panel. Admins manage both in the existing `/admin` panel, and Laravel Gates decide which staff roles may do what. Vendors work on **consignment**: stock lives in DarkStore warehouses, `products.vendor_id` links a product to its supplier, and each vendor is paid the supply price snapshotted on every delivered order item.

**Tech Stack:** PHP 8.4, Laravel 13, Filament 5 (Schemas `Wizard`, `FileUpload`, custom `Register` auth page), PHPUnit 12, SQLite (tests) / MariaDB (prod).

**Spec:** The user request of 2026-11-17, expanded in "Requirements & Decisions" below. Read [KNOWLEDGE.md](../../../KNOWLEDGE.md) first for the existing guards, services and panels.

---

## Requirements & Decisions

### From the request
1. Rider (delivery man) signup module.
2. Vendor signup and management.
3. Multiple verification steps with documents and legal details, Nepal-specific and following industry practice.
4. A fixed location for each rider and vendor, which they can ask to change.
5. Customers are never aware that vendors exist.
6. Admins have total control over riders and vendors.
7. Separate auth gates for each party.

### Decisions locked by this plan (confirm before executing)
- **D1 Vendor model = consignment / white-label.** The vendor supplies products, and DarkStore holds the stock in its own warehouses, sets city selling prices (the existing `product_prices`) and delivers. The vendor proposes a `supply_price` per variant, and admin approves it. The vendor's payable for each delivered unit is `quantity × unit_supply_price`, snapshotted on `order_items` at checkout. No commission model. Vendors never see customer data.
- **D2 Separate guards.** Staff use `web` (`users`), customers use `customer` (`customers`), riders use `delivery` (`delivery_agents`) and vendors use `vendor` (a new `vendors` table). Each guard has its own password broker. A session on one guard never authenticates another panel.
- **D3 Separate admin permission gates** (Laravel `Gate`s, defined in `AppServiceProvider::boot`):
  - `manage-riders`: super_admin, delivery_coordinator
  - `manage-vendors`: super_admin, catalog_manager
  - `view-kyc-documents`: rider documents → `manage-riders`; vendor documents → `manage-vendors`
  - `manage-vendor-payouts`: super_admin, finance
  - Warehouse managers get none of these, and the new admin resources use `HidesFromWarehouseManagers`.
- **D4 Verification state machine** (`VerificationStatus`), with every transition audited and the person notified by SMS:
  ```
  draft ──submit──▶ submitted ──startReview──▶ under_review ──approve──▶ approved ──suspend──▶ suspended
    ▲                  ▲                         │    │                                        │
    │                  └──────resubmit───── needs_changes ◀──requestChanges                   reactivate ──▶ approved
    │                                             │
    └─────────────────────────────────────────────┘ (edits allowed only in draft / needs_changes)
  under_review ──reject──▶ rejected (terminal; admin may reopen → needs_changes)
  ```
- **D5 Login vs. operating.** Any rider or vendor may log in to their own panel unless an admin set `status = inactive` (a hard block, enforced by `canAccessPanel`, which Filament checks on every request). Until they are `approved`, middleware confines them to the onboarding wizard and status pages. A rider can only be assigned orders when they are `approved`, `status = active` and `training_completed_at` is set.
- **D6 Fixed location.** Riders have `city_id` + `home_warehouse_id` (their hub) + `base_latitude/longitude`. Vendors have `city_id` + a pickup address + `pickup_latitude/longitude`. Riders and vendors cannot edit these after approval. They file a `LocationChangeRequest`, which admin approves or rejects. Admin can also edit the location directly at any time (audited).
- **D7 KYC documents** are stored on the private `local` disk (`storage/app/private/kyc/{type}/{id}/`) and are never on `public` or Curator. Admins see them only through a gated, audited download route. Document numbers, PAN, citizenship/NID numbers and bank account numbers use Laravel's `encrypted` cast. Consent text version, timestamp and IP are recorded at submission, per Nepal's Individual Privacy Act 2075 (2018).
- **D8 Documents replace, not version.** Re-uploading a document of the same type updates that row and resets it to `pending`. The before/after values go to `AuditLog`. *shortcut: no document history table; add one if regulators ask for historic copies.*
- **D9 Dates in AD.** Nepali documents print BS dates. Forms ask for AD dates and show a hint. *shortcut: no BS↔AD converter; add one (e.g. a BS date picker) if riders and vendors struggle.*
- **D10 Existing riders** (rows already in `delivery_agents`) are migrated to `verification_status = approved` with `training_completed_at = now()`, so live operations keep working.

### Nepal-specific KYC matrix (industry practice: Pathao, Foodmandu, Daraz seller centre)

| Document (`KycDocumentType`) | Party | Required when | Fields captured | Expiry tracked |
|---|---|---|---|---|
| `profile_photo` (live selfie) | rider | always | file | no |
| `citizenship` (Nagarikta) | rider / vendor authorized person | one of citizenship **or** national_id | number, issuing district, issue date, front + back | no |
| `national_id` (NID / Rastriya Parichaya Patra) | rider / vendor authorized person | (same group) | 10-digit NIN, front + back | no |
| `driving_license` | rider | vehicle is scooter/motorcycle | number, category (`A` motorcycle, `K` scooter; scooter accepts A or K, motorcycle needs A), expiry, front + back | yes |
| `vehicle_bluebook` (Billbook) | rider | motorized | registration number (`meta.vehicle_registration_number`), owner page + tax-renewal page, tax renewal expiry | yes |
| `vehicle_insurance` (third-party, mandatory) | rider | motorized | policy number, insurer, expiry | yes |
| `police_clearance` (character certificate) | rider | optional | number, issue date | no |
| `pan_certificate` (IRD) | vendor | always | 9-digit PAN | no |
| `company_registration` (Office of Company Registrar) | vendor | one of company **or** firm registration | registration number, issue date | no |
| `firm_registration` (Dept. of Commerce / Cottage & Small Industries office / municipality) | vendor | (same group) | registration number, authority, issue date | no |
| `vat_certificate` | vendor | `vat_registered = true` (VAT number = PAN in Nepal) | file | no |
| `tax_clearance` | vendor | optional (new businesses have none yet) | fiscal year `meta.fiscal_year` e.g. `2082/83`, expiry = FY end | yes |
| `food_license` (DFTQC) | vendor | `sells_food = true` | license number, expiry | yes |
| `bank_proof` (cancelled cheque or bank letter) | rider* / vendor | vendor always; rider when `payout_method = bank` | file | no |

Personal details (rider) and authorized-person details (vendor): legal name as on the citizenship card, date of birth (riders must be **18 or older**), gender, permanent and temporary address split as Nepal's 7 provinces → 77 districts (static lists in `config/nepal.php`) → municipality (free text, *shortcut: 753 local levels not listed*) → ward number (1–35) → tole. Riders also give an emergency contact (name, relation, phone).

Payouts: `payout_method` is `bank` or `wallet` (eSewa / Khalti). Bank payouts need the bank name (from `config/nepal.php` `banks` list), account holder name and account number. Wallet payouts need the wallet ID (phone). Admin checks that the account holder name matches the legal name.

Validation formats: Nepali mobile `^9[678]\d{8}$` after `SmsService::normalize`; PAN `^\d{9}$`; NIN `^\d{10}$` (*confirm with the Department of National ID before go-live*); ward 1–35; document files `jpg,jpeg,png,pdf`, max 5 MB each.

### Out of scope (follow-ups)
Multiple staff logins per vendor, vendor purchase orders and inbound stock workflows, TDS withholding on vendor payouts, a map-picker widget (forms take lat/lng with a "use my current location" button), automated face match on selfies, and live NID API verification.

---

## Global Constraints
- Run commands with `php84` / `composer84` (AGENTS.md). Format with `vendor/bin/pint --dirty`.
- No new Composer or npm dependencies.
- Follow existing conventions: services in `app/Services/*`, enums in `app/Enums` implementing `HasLabel`/`HasColor`, Filament "Manage*"/resource patterns, `AuditLog::record($action, $subject, $before, $after, $reason, $userId)`.
- Filament auth namespaces: `Filament\Auth\Pages\Register`, `Filament\Schemas\Components\Wizard`, `Wizard\Step`, `Filament\Forms\Components\FileUpload`.
- KYC files: `->disk('local')->visibility('private')`. Never the `public` disk.
- Baseline: `FastDeliveryArchitectureTest::test_customer_selecting_city_locks_catalog_to_that_city` already fails before this work. Don't count it as a regression; leave it alone.
- After each task, the full suite must be no worse than the baseline: `php84 artisan test`.
- Every admin state change writes an `AuditLog` row. Every rider/vendor status change sends one SMS via `SmsService::send`.

## Review Focus
1. **Suspending a rider who holds orders.** Orders in `Assigned` delivery status must go back to the dispatch queue (`delivery_agent_id = null`, `DeliveryStatus::Pending`). Orders already `PickedUp`/`OutForDelivery` stay with the rider, and admin sees a warning. → test in Task 8.
2. **Editing after submission.** A rider or vendor in `submitted`/`under_review`/`approved` must not be able to change documents or identity fields. Only `draft`/`needs_changes` are editable, and in `needs_changes` only the rejected documents are. → test in Task 7.
3. **Malicious or oversized uploads.** `.exe`, `.php`, `.svg` and files over 5 MB are rejected. Files land only under `storage/app/private/kyc/...`. → test in Task 7.
4. **Already-expired documents.** Uploading a license, insurance or bluebook with `expires_on` today or in the past is a validation error, not a later auto-suspension. → test in Task 7.
5. **Vendor or customer data leaking.** Storefront HTML, `/api/products*`, `/api/orders/*` and tracking pages never contain the vendor's legal or trade name or `vendor_id`. Vendor panel pages never contain customer names, phones or addresses. → tests in Tasks 13 and 14.

---

## File Map

| Area | Create | Modify |
|---|---|---|
| Enums | `app/Enums/VerificationStatus.php`, `KycDocumentType.php`, `KycDocumentStatus.php`, `VehicleType.php`, `BusinessType.php`, `PayoutMethod.php`, `LocationChangeStatus.php` | — |
| Config | `config/nepal.php` (provinces, districts by province, banks) | `config/auth.php` (vendor guard/provider, `delivery_agents` + `vendors` brokers) |
| KYC core | `app/Models/KycDocument.php`, `app/Models/LocationChangeRequest.php`, `app/Models/Concerns/HasKyc.php`, `app/Services/Verification/VerificationService.php`, `app/Services/Verification/LocationChangeService.php`, `app/Http/Controllers/Admin/KycDocumentFileController.php` | `app/Providers/AppServiceProvider.php` (gates, morph map), `routes/web.php` |
| Shared Filament | `app/Filament/Shared/KycFormSchemas.php` (address, payout, document upload field builders reused by both wizards), `app/Filament/Shared/KycDocumentsInfolist.php` | — |
| Riders | `app/Filament/Rider/Pages/Auth/RiderRegister.php`, `app/Filament/Rider/Pages/RiderOnboarding.php`, `app/Filament/Rider/Pages/RiderAccountStatus.php`, `app/Filament/Rider/Pages/RequestLocationChange.php`, `app/Http/Middleware/EnsureRiderApproved.php` | `app/Models/DeliveryAgent.php`, `app/Providers/Filament/RiderPanelProvider.php`, `app/Filament/Rider/Pages/MyDeliveries.php`, `app/Filament/Resources/DeliveryAgents/*`, `app/Filament/Pages/WarehouseOperations.php:112,165` |
| Vendors | `app/Models/Vendor.php`, `app/Models/VendorPayout.php`, `app/Providers/Filament/VendorPanelProvider.php`, `app/Filament/Vendor/Pages/Auth/VendorRegister.php`, `app/Filament/Vendor/Pages/VendorOnboarding.php`, `VendorAccountStatus.php`, `RequestLocationChange.php`, `VendorSalesReport.php`, `app/Filament/Vendor/Resources/VendorProducts/*`, `app/Http/Middleware/EnsureVendorApproved.php`, `app/Services/Vendor/VendorSettlementService.php` | `bootstrap/providers.php`, `app/Http/Middleware/UseCustomerGuard.php`, `app/Models/Product.php`, `app/Models/ProductVariant.php`, `app/Services/Checkout/CheckoutService.php` |
| Admin | `app/Filament/Resources/Vendors/*`, `app/Filament/Resources/LocationChangeRequests/*`, `app/Filament/Resources/VendorPayouts/*` | `app/Filament/Resources/Products/*` (pending_review filter + approve) |
| Compliance | `app/Console/Commands/CheckKycExpiry.php` | `routes/console.php` |
| Migrations | `create_kyc_documents_table`, `create_location_change_requests_table`, `add_onboarding_columns_to_delivery_agents_table`, `create_vendors_table`, `add_vendor_columns_to_catalog_and_order_items`, `create_vendor_payouts_table` | — |
| Tests | `tests/Unit/VerificationStatusTest.php`, `tests/Feature/Kyc/*Test.php`, `tests/Feature/Rider/*Test.php`, `tests/Feature/Vendor/*Test.php` | `tests/Feature/RiderPanelTest.php` (eligible-rider fixture) |
| Docs | — | `app/Filament/Pages/AdminManual.php` view, `KNOWLEDGE.md`, `.env.example`, `database/seeders/DatabaseSeeder.php` |

---

# Phase 0 — Shared KYC foundation

### Task 1: Enums and Nepal reference data

**Files:**
- Create: the seven enums listed in the File Map, plus `config/nepal.php`
- Test: `tests/Unit/VerificationStatusTest.php`, `tests/Unit/KycDocumentTypeTest.php`

**Interfaces:**
- Produces:
  - `enum VerificationStatus: string` with cases `Draft, Submitted, UnderReview, NeedsChanges, Approved, Rejected, Suspended` (values snake_case), `canTransitionTo(self $to): bool`, `isEditable(): bool` (Draft, NeedsChanges), `label()`, `getColor()`.
  - `enum KycDocumentType: string` with the cases from the KYC matrix, plus `needsBack(): bool`, `needsNumber(): bool`, `needsExpiry(): bool`, `label(): string`.
  - `enum KycDocumentStatus: string { Pending, Approved, Rejected, Expired }`
  - `enum VehicleType: string { Bicycle, Scooter, Motorcycle }` with `isMotorized(): bool` and `acceptedLicenseCategories(): array` (Scooter `['A','K']`, Motorcycle `['A']`, Bicycle `[]`).
  - `enum BusinessType: string { SoleProprietorship, Partnership, PrivateLimited, PublicLimited }` with `registrationDocument(): KycDocumentType` (limited companies → `CompanyRegistration`, others → `FirmRegistration`).
  - `enum PayoutMethod: string { Bank, Wallet }`, `enum LocationChangeStatus: string { Pending, Approved, Rejected }`
  - `config('nepal.provinces')`: the 7 province names. `config('nepal.districts')`: `array<string province, list<string>>` covering all 77 districts. `config('nepal.banks')`: NRB class-A commercial bank names.

- [ ] **Step 1: Write failing tests**
```php
public function test_allowed_transitions(): void
{
    $this->assertTrue(VerificationStatus::Draft->canTransitionTo(VerificationStatus::Submitted));
    $this->assertTrue(VerificationStatus::NeedsChanges->canTransitionTo(VerificationStatus::Submitted));
    $this->assertTrue(VerificationStatus::UnderReview->canTransitionTo(VerificationStatus::Approved));
    $this->assertTrue(VerificationStatus::Approved->canTransitionTo(VerificationStatus::Suspended));
    $this->assertTrue(VerificationStatus::Suspended->canTransitionTo(VerificationStatus::Approved));
    $this->assertTrue(VerificationStatus::Rejected->canTransitionTo(VerificationStatus::NeedsChanges));
    $this->assertTrue(VerificationStatus::Suspended->canTransitionTo(VerificationStatus::NeedsChanges)); // expired-document recovery, Task 15
    $this->assertFalse(VerificationStatus::Draft->canTransitionTo(VerificationStatus::Approved));
    $this->assertFalse(VerificationStatus::Submitted->canTransitionTo(VerificationStatus::Approved));
    $this->assertFalse(VerificationStatus::Approved->canTransitionTo(VerificationStatus::Draft));
}

public function test_nepal_reference_data_is_complete(): void
{
    $this->assertCount(7, config('nepal.provinces'));
    $this->assertSame(77, collect(config('nepal.districts'))->flatten()->count());
}

public function test_license_categories(): void
{
    $this->assertSame(['A', 'K'], VehicleType::Scooter->acceptedLicenseCategories());
    $this->assertFalse(VehicleType::Bicycle->isMotorized());
    $this->assertTrue(KycDocumentType::DrivingLicense->needsExpiry());
    $this->assertFalse(KycDocumentType::PanCertificate->needsBack());
}
```
- [ ] **Step 2: Run** `php84 artisan test --filter="VerificationStatusTest|KycDocumentTypeTest"`. Expected: FAIL (classes missing).
- [ ] **Step 3: Implement the enums and config.** The transitions table is the D4 diagram. Under_review may also go to NeedsChanges and Rejected.
- [ ] **Step 4: Run the same command.** Expected: PASS.
- [ ] **Step 5: Commit** `feat(kyc): add verification enums and Nepal reference data`

### Task 2: KYC documents, location-change tables and the `HasKyc` trait

**Files:**
- Create: migrations `create_kyc_documents_table`, `create_location_change_requests_table`; `app/Models/KycDocument.php`, `app/Models/LocationChangeRequest.php`, `app/Models/Concerns/HasKyc.php`
- Modify: `app/Providers/AppServiceProvider.php` (`Relation::morphMap(['delivery_agent' => DeliveryAgent::class, 'vendor' => Vendor::class])`, non-enforced so existing morphs keep working. Register the `vendor` alias in Task 10, when the class exists.)
- Test: `tests/Feature/Kyc/HasKycTest.php` (uses `DeliveryAgent` as the documentable)

**Interfaces:**
- Produces:
  - `kyc_documents` columns: `id, documentable_type, documentable_id, type, document_number (text, encrypted), issuing_authority null, issued_on date null, expires_on date null, file_front, file_back null, meta json null, status default 'pending', rejection_reason null, reviewed_by FK users null, reviewed_at null, timestamps` (expiry reminders are tracked in `meta.reminded_days`, Task 15); unique `(documentable_type, documentable_id, type)`; index `(status, expires_on)`.
  - `location_change_requests` columns: `id, requestable_type, requestable_id, current json, proposed json (city_id, warehouse_id?, address fields, latitude, longitude), reason text, status default 'pending', reviewed_by null, reviewed_at null, review_note null, timestamps`.
  - `KycDocument` casts: `type → KycDocumentType`, `status → KycDocumentStatus`, `document_number → encrypted`, `meta → array`, dates. `documentable(): MorphTo`.
  - trait `HasKyc`:
    - `kycDocuments(): MorphMany`
    - `locationChangeRequests(): MorphMany`
    - `abstract requiredDocumentGroups(): array<int, list<KycDocumentType>>` (each group is satisfied by any one of its types)
    - `missingDocumentGroups(bool $approvedOnly = false): array<int, list<KycDocumentType>>`
    - `upsertKycDocument(KycDocumentType $type, array $attributes): KycDocument` (implements D8 and writes `AuditLog` `kyc_document_uploaded`)
  - The model columns the trait expects (added in Tasks 5 and 10): `verification_status`, `submitted_at`, `approved_at`, `approved_by`, `rejection_reason`, `agreement_version`, `agreement_accepted_at`, `agreement_ip`, `status`.

- [ ] **Step 1: Write failing tests**
```php
public function test_one_of_group_is_satisfied_by_either_type(): void
{
    $rider = $this->bicycleRider(); // groups: [[ProfilePhoto], [Citizenship, NationalId]]
    $rider->upsertKycDocument(KycDocumentType::ProfilePhoto, ['file_front' => 'kyc/x.jpg']);
    $this->assertEquals([[KycDocumentType::Citizenship, KycDocumentType::NationalId]], $rider->missingDocumentGroups());
    $rider->upsertKycDocument(KycDocumentType::NationalId, ['file_front' => 'a', 'file_back' => 'b', 'document_number' => '1234567890']);
    $this->assertSame([], $rider->missingDocumentGroups());
    $this->assertNotSame([], $rider->missingDocumentGroups(approvedOnly: true));
}

public function test_reupload_replaces_row_and_resets_to_pending(): void
{
    $doc = $this->rider->upsertKycDocument(KycDocumentType::ProfilePhoto, ['file_front' => 'old.jpg']);
    $doc->update(['status' => KycDocumentStatus::Rejected, 'rejection_reason' => 'blurry']);
    $this->rider->upsertKycDocument(KycDocumentType::ProfilePhoto, ['file_front' => 'new.jpg']);
    $this->assertSame(1, $this->rider->kycDocuments()->count());
    $this->assertSame(KycDocumentStatus::Pending, $doc->fresh()->status);
    $this->assertNull($doc->fresh()->rejection_reason);
}

public function test_document_number_is_encrypted_at_rest(): void
{
    $doc = $this->rider->upsertKycDocument(KycDocumentType::NationalId, ['file_front' => 'a', 'document_number' => '1234567890']);
    $this->assertStringNotContainsString('1234567890', DB::table('kyc_documents')->value('document_number'));
    $this->assertSame('1234567890', $doc->fresh()->document_number);
}
```
- [ ] **Step 2: Run** `php84 artisan test tests/Feature/Kyc/HasKycTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement the migrations, models and trait.** Tasks 5 and 10 complete `DeliveryAgent` itself. For this task, the test can add a minimal `requiredDocumentGroups()` to `DeliveryAgent` returning the bicycle groups. Task 5 replaces it.
- [ ] **Step 4: Run.** Expected: PASS.
- [ ] **Step 5: Commit** `feat(kyc): add polymorphic KYC documents and location change requests`

### Task 3: `VerificationService` state machine

**Files:**
- Create: `app/Services/Verification/VerificationService.php`, `app/Exceptions/VerificationException.php`
- Test: `tests/Feature/Kyc/VerificationServiceTest.php`

**Interfaces:**
- Consumes: `HasKyc` (Task 2), `VerificationStatus` (Task 1), `AuditLog::record`, `SmsService::send`.
- Produces (every method takes a `Model&HasKyc $party` and throws `VerificationException` on an illegal transition or missing precondition; staff methods take `User $actor`):
  - `submit($party, string $agreementVersion, string $ip): void`. Requires `missingDocumentGroups() === []` and an editable status. Sets `submitted_at` and the agreement fields.
  - `startReview($party, User $actor): void`
  - `approveDocument(KycDocument $doc, User $actor): void` / `rejectDocument(KycDocument $doc, User $actor, string $reason): void`
  - `requestChanges($party, User $actor, string $reason): void`. Requires at least one rejected document.
  - `approve($party, User $actor): void`. Requires `missingDocumentGroups(approvedOnly: true) === []`. Sets `approved_at` and `approved_by`.
  - `reject($party, User $actor, string $reason): void`, `suspend($party, ?User $actor, string $reason): void` (`$actor` is null when the system suspends), `reactivate($party, User $actor): void`, `reopen($party, User $actor, string $reason): void` (Rejected → NeedsChanges)
  - Audit action names: `verification_{to_status}` and `kyc_document_{approved|rejected}`.
  - Hook: after `suspend()` on a `DeliveryAgent`, call `$party->releaseUnstartedDeliveries(): int` (defined in Task 5).

- [ ] **Step 1: Write failing tests**
```php
test_submit_fails_when_required_documents_missing()     // expectException(VerificationException::class)
test_submit_records_agreement_and_moves_to_submitted()  // assertSame(VerificationStatus::Submitted, ...); agreement_ip === '10.0.0.1'
test_approve_fails_while_any_required_document_unapproved()
test_full_happy_path_writes_audit_log_and_sms()         // AuditLog has 'verification_approved'; Log/SMS fake: SmsService mocked ->send called with rider phone
test_request_changes_requires_a_rejected_document()
test_rejected_can_be_reopened_to_needs_changes()
```
Mock `SmsService` with `$this->mock(SmsService::class)->shouldReceive('send')->andReturnTrue()`.
- [ ] **Step 2: Run** `php84 artisan test tests/Feature/Kyc/VerificationServiceTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement.** Wrap each transition in `DB::transaction`. Check with `VerificationStatus::canTransitionTo`. SMS copy: `"DarkStore: your {party} account is now {label}."`, plus the reason when one is given.
- [ ] **Step 4: Run.** Expected: PASS.
- [ ] **Step 5: Commit** `feat(kyc): add verification state machine service`

### Task 4: Admin permission gates and secure document download

**Files:**
- Create: `app/Http/Controllers/Admin/KycDocumentFileController.php`
- Modify: `app/Providers/AppServiceProvider.php` (the gates from D3), `routes/web.php`
- Test: `tests/Feature/Kyc/KycDocumentAccessTest.php`

**Interfaces:**
- Produces:
  - Gates `manage-riders`, `manage-vendors`, `view-kyc-documents` (argument: `KycDocument`), `manage-vendor-payouts`.
  - Route `GET /admin/kyc-documents/{document}/{side}` (`side` in `front|back`), name `admin.kyc.file`, middleware `['web', 'auth:web', 'can:view-kyc-documents,document']`. It streams `Storage::disk('local')->response($path)` and writes `AuditLog` `kyc_document_viewed`.
  - Register the route **before** any `/admin` catch-all. Filament admin paths don't collide with `kyc-documents`.

- [ ] **Step 1: Write failing tests**
```php
test_guest_is_redirected_to_login()
test_customer_rider_and_vendor_sessions_cannot_download()      // actingAs($customer,'customer') etc. → 302/403, never 200
test_warehouse_manager_and_picker_get_403()
test_delivery_coordinator_can_view_rider_doc()     // the vendor-doc half is added in Task 10
test_super_admin_download_streams_file_and_is_audited()        // Storage::fake('local'); assertOk(); AuditLog 'kyc_document_viewed' exists
```
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement the gates and controller.**
- [ ] **Step 4: Run.** Expected: PASS.
- [ ] **Step 5: Commit** `feat(kyc): gate admin abilities and serve KYC files privately`

---

# Phase 1 — Riders

### Task 5: Rider onboarding columns, model rules and eligibility

**Files:**
- Create: migration `add_onboarding_columns_to_delivery_agents_table`
- Modify: `app/Models/DeliveryAgent.php`, `app/Filament/Pages/WarehouseOperations.php:112` and `:165`, `tests/Feature/RiderPanelTest.php` (fixture riders must be eligible)
- Test: `tests/Feature/Rider/RiderEligibilityTest.php`

**Interfaces:**
- Produces:
  - New nullable columns: `verification_status` (default `draft`), `submitted_at`, `approved_at`, `approved_by`, `rejection_reason`, `agreement_version`, `agreement_accepted_at`, `agreement_ip`, `phone_verified_at`, `vehicle_type`, `date_of_birth`, `gender`, `perm_province`, `perm_district`, `perm_municipality`, `perm_ward`, `perm_tole`, `temp_*` (same five), `emergency_contact_name`, `emergency_contact_relation`, `emergency_contact_phone`, `home_warehouse_id` (FK warehouses, nullOnDelete), `base_latitude`, `base_longitude`, `payout_method`, `bank_name`, `bank_account_name`, `bank_account_number` (text, encrypted cast), `wallet_id`, `training_completed_at`. Add a unique index on `phone`.
  - Data migration (D10): set existing rows to `verification_status = 'approved'`, `approved_at = now()`, `training_completed_at = now()`.
  - `DeliveryAgent`: `use HasKyc`; casts for the enums, `encrypted` and dates; `homeWarehouse(): BelongsTo`.
  - `requiredDocumentGroups()`: `[[ProfilePhoto],[Citizenship,NationalId]]`, plus `[[DrivingLicense],[VehicleBluebook],[VehicleInsurance]]` if `vehicle_type->isMotorized()`, plus `[[BankProof]]` if `payout_method === Bank`.
  - `scopeEligibleForDispatch(Builder $q)`: `status = active`, `verification_status = approved`, `training_completed_at` not null.
  - `scopeAvailable` becomes `eligibleForDispatch()->where('availability','available')`.
  - `canAccessPanel(Panel $p): bool` is `$p->getId() === 'rider' && $this->status !== 'inactive'` (D5).
  - `isApproved(): bool`
  - `releaseUnstartedDeliveries(): int`: orders with `delivery_status = Assigned` go to `delivery_agent_id = null`, `DeliveryStatus::Pending`. Returns the count.
  - `WarehouseOperations`: the rider dropdown (line 112) uses `->eligibleForDispatch()`. The fallback `DeliveryAgent::where('status','active')->first()` (line 165) becomes `->eligibleForDispatch()->first()`.

- [ ] **Step 1: Write failing tests**
```php
test_existing_riders_are_migrated_as_approved()         // insert a row before migrate? → use the migration's up() on a pre-seeded row via DB::table in the test, then assert approved
test_unapproved_or_untrained_rider_is_not_dispatchable() // draft, approved-but-no-training, inactive → none in eligibleForDispatch()
test_bicycle_rider_needs_no_license_documents()
test_scooter_rider_with_bank_payout_needs_license_bluebook_insurance_and_bank_proof()
test_inactive_rider_cannot_access_panel_but_draft_rider_can()
test_release_unstarted_deliveries_only_touches_assigned_orders() // Assigned → Pending & null agent; PickedUp untouched; returns 1
```
- [ ] **Step 2: Run** `php84 artisan test tests/Feature/Rider/RiderEligibilityTest.php tests/Feature/RiderPanelTest.php`. Expected: new tests FAIL.
- [ ] **Step 3: Implement** the migration, model changes and the `WarehouseOperations` swap. Update the `RiderPanelTest` fixtures with `verification_status => 'approved', training_completed_at => now()`.
- [ ] **Step 4: Run.** Expected: PASS. The full suite matches the baseline.
- [ ] **Step 5: Commit** `feat(rider): add onboarding fields and dispatch eligibility`

### Task 6: Rider self-registration with phone OTP and password reset

**Files:**
- Create: `app/Filament/Rider/Pages/Auth/RiderRegister.php`
- Modify: `app/Providers/Filament/RiderPanelProvider.php` (`->registration(RiderRegister::class)->passwordReset()->authPasswordBroker('delivery_agents')`), `config/auth.php` (broker `delivery_agents` mirroring `customers`)
- Test: `tests/Feature/Rider/RiderRegistrationTest.php`

**Interfaces:**
- Consumes: `OtpService::issue(string $phone, string $purpose): array`, `OtpService::verify(string $phone, string $purpose, string $code): bool`, `SmsService::normalize()`.
- Produces: `RiderRegister extends Filament\Auth\Pages\Register`. Form fields: `name`, `phone`, `otp_code` (with a suffix action `sendOtp` that calls `issue($phone, 'rider_signup')`), `email` (nullable, unique:delivery_agents), `password` + confirmation (min 8), `city_id` (active cities), `vehicle_type`. `handleRegistration(array $data): DeliveryAgent` verifies the OTP, then creates the agent with `status = active` (login allowed), `verification_status = draft`, `phone_verified_at = now()`, `availability = offline`. Login uses phone or email: override `getCredentialsFromFormData` on the panel login page so it accepts either.

- [ ] **Step 1: Write failing tests**
```php
test_rider_can_register_with_valid_otp()
// Filament::setCurrentPanel('rider'); mock OtpService verify → true
// Livewire::test(RiderRegister::class)->fillForm([...])->call('register')->assertHasNoFormErrors();
// assertDatabaseHas('delivery_agents', ['phone' => '9812345678', 'verification_status' => 'draft']);
// assertTrue(auth('delivery')->check()); assertFalse(auth('web')->check()); assertFalse(auth('customer')->check());
test_wrong_otp_blocks_registration()            // verify → false → assertHasFormErrors(['otp_code'])
test_duplicate_rider_phone_is_rejected()
test_customer_with_same_phone_can_still_become_rider() // Customer exists with phone; rider registration succeeds (separate tables, D2)
test_invalid_nepali_mobile_is_rejected()        // '1234567890' → error on phone
```
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Expected: PASS.
- [ ] **Step 5: Commit** `feat(rider): self-registration with phone OTP`

### Task 7: Rider onboarding wizard, status page and approval gate

**Files:**
- Create: `app/Filament/Shared/KycFormSchemas.php`, `app/Filament/Rider/Pages/RiderOnboarding.php`, `app/Filament/Rider/Pages/RiderAccountStatus.php`, `app/Http/Middleware/EnsureRiderApproved.php`, `config/kyc.php` (`agreement_version`, rider/vendor agreement text keys)
- Modify: `RiderPanelProvider` (register the pages; add `EnsureRiderApproved` to `->authMiddleware`), `MyDeliveries::canAccess()` → `auth('delivery')->user()?->isApproved()`
- Test: `tests/Feature/Rider/RiderOnboardingTest.php`

**Interfaces:**
- Consumes: `HasKyc::upsertKycDocument`, `VerificationService::submit`, `VehicleType::acceptedLicenseCategories`.
- Produces:
  - `KycFormSchemas::addressFields(string $prefix): array` (province select → dependent district select from `config('nepal.districts')`, municipality, ward 1–35, tole)
  - `KycFormSchemas::payoutFields(): array`
  - `KycFormSchemas::documentFields(KycDocumentType $type, string $directory): array` (FileUpload front/back on disk `local`, private, `acceptedFileTypes(['image/jpeg','image/png','application/pdf'])`, `maxSize(5120)`; number / issued_on / expires_on fields per `needs*()`; `expires_on` rule `after:today`)
  - `RiderOnboarding` wizard steps: **1 Personal** (legal name, DOB `before_or_equal:-18 years`, gender, permanent + temporary address, emergency contact) → **2 Identity** (profile photo, citizenship OR NID) → **3 Vehicle** (license with category limited to `acceptedLicenseCategories()`, bluebook + `meta.vehicle_registration_number`, insurance; step hidden for bicycles) → **4 Hub & payout** (home warehouse limited to warehouses serving the rider's city, base lat/lng, payout fields, bank proof) → **5 Review & agree** (summary, agreement checkbox with text from `config('kyc')`) → submit calls `VerificationService::submit($rider, config('kyc.agreement_version'), request()->ip())`.
  - Editing rules: the form is disabled unless `verification_status->isEditable()`. In `NeedsChanges`, only the upload fields for documents whose status is `Rejected` are enabled.
  - `RiderAccountStatus` shows the status badge, the rejection or needs-changes reasons per document, and the expiry dates of approved documents.
  - `EnsureRiderApproved`: if the rider isn't approved and the route isn't onboarding, status, logout or location-change, redirect to `RiderOnboarding` (for editable statuses) or `RiderAccountStatus` (for all others).

- [ ] **Step 1: Write failing tests**
```php
test_draft_rider_is_redirected_from_deliveries_to_onboarding()
test_approved_rider_reaches_my_deliveries()
test_wizard_stores_files_privately_and_submits()   // Storage::fake('local'); UploadedFile::fake()->image('front.jpg'); after submit: verification_status submitted; Storage::disk('local')->assertExists("kyc/delivery_agent/{$id}/...")
test_bicycle_rider_skips_vehicle_step()
test_scooter_rider_cannot_pick_license_category_B()
test_under_18_is_rejected()
test_rejects_disallowed_or_oversized_files()       // fake()->create('x.exe', 10), fake()->create('x.svg'), fake()->image('big.jpg')->size(6000) → form errors   [Review Focus 3]
test_rejects_already_expired_license()             // expires_on = today → error                                                       [Review Focus 4]
test_submitted_rider_cannot_edit_documents()       // set Submitted, call save → no change, doc row unchanged                          [Review Focus 2]
test_needs_changes_rider_can_replace_only_rejected_documents()
```
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Expected: PASS.
- [ ] **Step 5: Commit** `feat(rider): multi-step KYC onboarding wizard and approval gate`

### Task 8: Admin control of riders

**Files:**
- Create: `app/Filament/Shared/KycDocumentsInfolist.php`, `app/Filament/Resources/DeliveryAgents/Pages/ViewDeliveryAgent.php`
- Modify: `app/Filament/Resources/DeliveryAgents/DeliveryAgentResource.php` and its pages
- Test: `tests/Feature/Rider/AdminRiderManagementTest.php`

**Interfaces:**
- Consumes: every `VerificationService` method; gates `manage-riders` and `view-kyc-documents`; route `admin.kyc.file`.
- Produces:
  - Resource `canAccess()` / `canViewAny()` → `Gate::allows('manage-riders')`. Uses `HidesFromWarehouseManagers`.
  - Table: tabs `Pending review` (submitted + under_review), `Needs changes`, `Approved`, `Suspended`, `Rejected`, `All`. Columns: name, phone, city, hub, vehicle, verification badge, nearest document expiry. Filters: city, vehicle_type.
  - View page: personal details, `KycDocumentsInfolist::make()` (document type, masked number with reveal action, expiry, status, preview links to `admin.kyc.file`), and the audit trail for this rider.
  - Actions, each confirming with a reason where noted: `startReview`, per-document `approve` / `reject(reason)`, `requestChanges(reason)`, `approve`, `reject(reason)`, `suspend(reason)` (shows how many orders were released and lists picked-up orders still with the rider), `reactivate`, `reopen(reason)`, `markTrainingComplete` (sets `training_completed_at`, audited), `deactivateLogin` / `activateLogin` (toggles `status`), `resetPassword` (sends a broker link), and edit (all fields, including location, audited via the model's `updated` diff in `AuditLog`).

- [ ] **Step 1: Write failing tests**
```php
test_delivery_coordinator_can_list_riders_warehouse_manager_cannot()
test_admin_approves_documents_then_rider()          // Livewire table/page actions; final verification_status approved; SMS mocked
test_admin_cannot_approve_rider_with_pending_document() // action shows danger notification, status unchanged
test_suspend_releases_assigned_orders_keeps_picked_up()  [Review Focus 1]
test_deactivated_rider_is_logged_out_of_panel()     // status inactive → GET /rider → 403
test_mark_training_complete_makes_rider_dispatchable()
test_admin_location_edit_is_audited()
```
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Expected: PASS.
- [ ] **Step 5: Commit** `feat(admin): full rider review and control`

---

# Phase 2 — Location change requests (shared)

### Task 9: Request and approve location changes

**Files:**
- Create: `app/Services/Verification/LocationChangeService.php`, `app/Filament/Rider/Pages/RequestLocationChange.php`, `app/Filament/Resources/LocationChangeRequests/LocationChangeRequestResource.php` + `Pages/ManageLocationChangeRequests.php`
- Test: `tests/Feature/Kyc/LocationChangeTest.php`

**Interfaces:**
- Produces:
  - `LocationChangeService::request(Model&HasKyc $party, array $proposed, string $reason): LocationChangeRequest`. Throws `VerificationException` if a pending request already exists. Snapshots `current` from the party's location fields.
  - `approve(LocationChangeRequest $r, User $actor, ?string $note): void` copies `proposed` onto the party in one transaction.
  - `reject(LocationChangeRequest $r, User $actor, string $note): void`
  - Add `abstract public function locationAttributes(): array` (a `list<string>`) to the `HasKyc` trait and implement it on each model. Rider: `city_id, home_warehouse_id, base_latitude, base_longitude`. Vendor (Task 10): `city_id, pickup_province, pickup_district, pickup_municipality, pickup_ward, pickup_tole, pickup_latitude, pickup_longitude`.
  - The rider page is available only to approved riders. Changing city clears the hub, so a new hub from the new city is required.
  - Admin resource: Pending/All tabs, a side-by-side current vs proposed view, approve/reject actions. Access is gated by `manage-riders` or `manage-vendors` per requestable type. The vendor page reuses the same service in Task 11.

- [ ] **Step 1: Write failing tests**
```php
test_approved_rider_can_request_change_and_second_pending_request_is_refused()
test_unapproved_rider_cannot_request_change()
test_admin_approval_applies_location_and_audits()
test_rejection_leaves_location_untouched()
test_new_hub_must_serve_new_city()
```
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Expected: PASS.
- [ ] **Step 5: Commit** `feat(kyc): location change requests with admin approval`

---

# Phase 3 — Vendors

### Task 10: Vendor account, `vendor` guard and `/vendor` panel

**Files:**
- Create: migration `create_vendors_table`, `app/Models/Vendor.php`, `app/Providers/Filament/VendorPanelProvider.php`
- Modify: `config/auth.php` (guard `vendor`, provider `vendors`, broker `vendors`), `bootstrap/providers.php`, `app/Http/Middleware/UseCustomerGuard.php` (also skip `vendor*`), the morph map (`'vendor' => Vendor::class`)
- Test: `tests/Feature/Vendor/VendorGuardSeparationTest.php`, and add `test_catalog_manager_can_view_vendor_doc_but_not_rider_doc` to `KycDocumentAccessTest`

**Interfaces:**
- Produces:
  - `vendors` columns: `id, legal_name, trade_name null, business_type, registration_number (encrypted), registration_authority null, pan_number (string, unique; stored plain so it can be unique and searched; PAN is public business data in Nepal), vat_registered bool, sells_food bool, authorized_person_name, authorized_person_designation, phone unique, email unique, password, remember_token, phone_verified_at, city_id FK, pickup_province, pickup_district, pickup_municipality, pickup_ward, pickup_tole, pickup_latitude, pickup_longitude, payout_method, bank_name, bank_account_name, bank_account_number (encrypted), wallet_id, status default 'active', ` plus the verification columns listed in Task 2, `timestamps, softDeletes`.
  - `Vendor extends Authenticatable implements FilamentUser`, `use HasKyc, SoftDeletes`.
  - `requiredDocumentGroups()`: `[[PanCertificate],[CompanyRegistration, FirmRegistration],[Citizenship, NationalId],[BankProof]]`, plus `[[VatCertificate]]` if VAT registered, plus `[[FoodLicense]]` if `sells_food`.
  - `canAccessPanel(Panel $p)` is `$p->getId() === 'vendor' && $this->status !== 'inactive'`.
  - `products(): HasMany`, `payouts(): HasMany`, `isApproved(): bool`
  - `VendorPanelProvider`: id `vendor`, path `vendor`, `->authGuard('vendor')`, `->login()`, colour Indigo, brand "DarkStore Supplier Portal". Pages and resources are discovered in `app/Filament/Vendor`.

- [ ] **Step 1: Write failing tests**
```php
test_vendor_session_does_not_open_admin_rider_or_customer_account()  // actingAs($vendor,'vendor'): /admin → login redirect, /rider → login redirect, /account/orders → login redirect
test_staff_customer_and_rider_sessions_do_not_open_vendor_panel()
test_storefront_requests_still_use_customer_guard_but_vendor_paths_do_not()  // UseCustomerGuard
test_inactive_vendor_gets_403()
test_vat_and_food_flags_add_required_documents()
```
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Expected: PASS.
- [ ] **Step 5: Commit** `feat(vendor): vendor model, dedicated auth guard and supplier panel`

### Task 11: Vendor registration, onboarding wizard and approval gate

**Files:**
- Create: `app/Filament/Vendor/Pages/Auth/VendorRegister.php`, `VendorOnboarding.php`, `VendorAccountStatus.php`, `RequestLocationChange.php`, `app/Http/Middleware/EnsureVendorApproved.php`
- Modify: `VendorPanelProvider` (`->registration(VendorRegister::class)->passwordReset()->authPasswordBroker('vendors')`, plus the middleware)
- Test: `tests/Feature/Vendor/VendorOnboardingTest.php`

**Interfaces:**
- Consumes: `OtpService` (purpose `vendor_signup`), `KycFormSchemas::*` (Task 7), `VerificationService::submit`, `LocationChangeService::request` (Task 9).
- Produces:
  - `VendorRegister` fields: legal_name, phone + OTP, email, password, city_id, business_type → creates the vendor with `verification_status = draft`.
  - `VendorOnboarding` steps: **1 Business** (trade name, registration number + authority, PAN `^\d{9}$` unique, VAT registered, sells food) → **2 Authorized person** (name, designation, citizenship OR NID) → **3 Documents** (PAN certificate, the document from `business_type->registrationDocument()`, VAT certificate if flagged, food license if flagged, optional tax clearance with `meta.fiscal_year` matching `^\d{4}/\d{2}$`) → **4 Pickup location** (`addressFields('pickup')`, lat/lng) → **5 Payout** (bank proof required) → **6 Review & agree** → submit.
  - Same editing rules as Task 7. `EnsureVendorApproved` mirrors `EnsureRiderApproved`. The vendor `RequestLocationChange` page reuses `LocationChangeService`.

- [ ] **Step 1: Write failing tests**
```php
test_vendor_registers_with_otp_and_lands_on_onboarding()
test_private_limited_requires_company_registration_sole_prop_requires_firm_registration()
test_duplicate_or_malformed_pan_is_rejected()        // '12345678' and existing PAN
test_tax_clearance_fiscal_year_format_validated()
test_unapproved_vendor_cannot_open_products_resource()
test_submitted_vendor_cannot_edit()
test_approved_vendor_can_request_pickup_location_change()
```
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Expected: PASS.
- [ ] **Step 5: Commit** `feat(vendor): self-registration and KYC onboarding wizard`

### Task 12: Admin control of vendors

**Files:**
- Create: `app/Filament/Resources/Vendors/VendorResource.php`, `Pages/ListVendors.php`, `Pages/ViewVendor.php`, `Pages/EditVendor.php`
- Test: `tests/Feature/Vendor/AdminVendorManagementTest.php`

**Interfaces:**
- Consumes: `VerificationService`, `KycDocumentsInfolist` (Task 8), gate `manage-vendors`.
- Produces: the same tab, view and action set as Task 8 (minus training, plus `vat_registered` / `sells_food` toggles, which re-run `missingDocumentGroups`). Admins can also create a vendor directly (e.g. a walk-in supplier) as `draft`, and upload documents on the vendor's behalf (audited with the admin's `user_id`). Suspending a vendor sets all their `products.status = 'inactive'` and records the product ids in the audit `after` payload. Reactivating restores exactly those products.

- [ ] **Step 1: Write failing tests**
```php
test_catalog_manager_manages_vendors_delivery_coordinator_cannot()
test_admin_approval_flow_for_vendor()
test_suspending_vendor_hides_their_products_and_reactivation_restores_only_those()
test_admin_created_vendor_documents_are_attributed_to_admin()
```
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Expected: PASS.
- [ ] **Step 5: Commit** `feat(admin): vendor review and full control`

### Task 13: Vendor catalog submissions, supply prices and customer invisibility

**Files:**
- Create: migration `add_vendor_columns_to_catalog_and_order_items`, `app/Filament/Vendor/Resources/VendorProducts/VendorProductResource.php` + pages
- Modify: `app/Models/Product.php`, `app/Models/ProductVariant.php`, `app/Models/OrderItem.php`, `app/Services/Checkout/CheckoutService.php` (item snapshot), `app/Filament/Resources/Products/ProductResource.php` (filter + approve action + vendor column)
- Test: `tests/Feature/Vendor/VendorCatalogTest.php`

**Interfaces:**
- Produces:
  - Columns: `products.vendor_id` (FK vendors, nullOnDelete, null = house product), `product_variants.supply_price decimal(10,2) null`, `order_items.vendor_id null`, `order_items.unit_supply_price decimal(10,2) null`.
  - New product status value `pending_review`. The storefront already shows only `active` products. Verify that `CityCatalogService` filters on `status = 'active'`.
  - `Product::$hidden` adds `vendor_id`, and `ProductVariant::$hidden` adds `supply_price`.
  - `VendorProductResource`: query scoped to `vendor_id = auth('vendor')->id()`. Vendors may create and edit products only while `draft` / `pending_review`, with fields name, category, brand, description, images (Curator), and variants (name, sku, barcode, weight, supply_price). Saving sets `pending_review`. Vendors cannot touch city prices, inventory or status.
  - Admin `ProductResource`: a `Pending review` filter, an `approve` action (sets `active` after the admin sets `product_prices` for at least one city), a `reject(reason)` action back to `draft` with an SMS to the vendor, and a vendor column + filter for admins only.
  - `CheckoutService` order-item snapshot adds `'vendor_id' => $variant->product->vendor_id, 'unit_supply_price' => $variant->supply_price`.

- [ ] **Step 1: Write failing tests**
```php
test_vendor_sees_and_edits_only_own_products()
test_vendor_submission_is_pending_and_hidden_from_storefront_until_approved()
test_vendor_cannot_change_status_or_city_prices()
test_checkout_snapshots_vendor_and_supply_price()
test_customers_never_see_vendor()   [Review Focus 5]
// approved vendor product in Dharan: GET /?city_id=..., GET /products/{slug}, GET /api/products?city_id=..., GET /api/products/{slug},
// place order then GET /orders/{n}/tracking and /api/orders/{n}:
// assertDontSee($vendor->legal_name); assertDontSee($vendor->trade_name); assertJsonMissingPath('vendor_id') / assertStringNotContainsString('supply_price', ...)
```
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Expected: PASS.
- [ ] **Step 5: Commit** `feat(vendor): catalog submissions with admin approval, hidden from customers`

### Task 14: Vendor stock and sales view, settlements and payouts

**Files:**
- Create: migration `create_vendor_payouts_table`, `app/Models/VendorPayout.php`, `app/Services/Vendor/VendorSettlementService.php`, `app/Filament/Vendor/Pages/VendorSalesReport.php`, `app/Filament/Vendor/Widgets/VendorStockOverview.php`, `app/Filament/Resources/VendorPayouts/VendorPayoutResource.php` + `Pages/ManageVendorPayouts.php`
- Test: `tests/Feature/Vendor/VendorSettlementTest.php`

**Interfaces:**
- Produces:
  - `vendor_payouts` columns: `id, vendor_id, period_from date, period_to date, amount decimal(12,2), method, reference, paid_at, recorded_by FK users, note null, timestamps`.
  - `VendorSettlementService::statement(Vendor $v, CarbonInterface $from, CarbonInterface $to): array{lines: list<array{sku:string, product_name:string, quantity:int, unit_supply_price:float, amount:float}>, payable: float, paid: float, balance: float}`. It counts `order_items` with `vendor_id = $v->id` on orders where `order_status = delivered` and `delivered_at` is within `[from, to]`. Cancelled and returned orders are excluded because their status isn't `delivered`. `paid` sums payouts whose period overlaps the range. *shortcut: partial returns aren't netted line by line; add that when `ReturnRequest` supports per-item returns.*
  - `VendorStockOverview` shows the vendor's own variants with total available stock summed across warehouses (no warehouse names), plus low-stock flags.
  - `VendorSalesReport` has a date range, the statement table and CSV export. It never selects order, customer or address columns.
  - `VendorPayoutResource` is gated by `manage-vendor-payouts`. Recording a payout requires `amount <= statement balance` for that period, and is audited.

- [ ] **Step 1: Write failing tests**
```php
test_statement_counts_only_delivered_orders_in_range_using_snapshot_price() // change supply_price after order → statement uses old snapshot
test_vendor_report_contains_no_customer_data()   [Review Focus 5] // Livewire::test(VendorSalesReport) → assertDontSee(customer name/phone/address)
test_vendor_sees_only_own_stock_and_sales()
test_finance_records_payout_and_balance_drops_catalog_manager_cannot()
test_payout_above_balance_is_refused()
```
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Expected: PASS.
- [ ] **Step 5: Commit** `feat(vendor): stock view, settlement statements and payouts`

---

# Phase 4 — Compliance operations and docs

### Task 15: KYC expiry monitoring

**Files:**
- Create: `app/Console/Commands/CheckKycExpiry.php`
- Modify: `routes/console.php` (`Schedule::command('kyc:check-expiry')->dailyAt('06:00')`)
- Test: `tests/Feature/Kyc/KycExpiryTest.php`

**Interfaces:**
- Produces: command `kyc:check-expiry`.
  1. Approved documents expiring in exactly 30 or 7 days get one SMS to the owner, unless that milestone is already in `meta.reminded_days`. Append the milestone after sending, so the same reminder is never sent twice.
  2. Documents with `expires_on < today` are set to `Expired`.
  3. Any approved party whose `missingDocumentGroups(approvedOnly: true)` is now non-empty gets `VerificationService::suspend($party, null, 'Document expired: {label}')`. Riders therefore also have their assigned orders released.
  4. The command outputs counts as `reminded=X expired=Y suspended=Z`.
  - Riders and vendors can re-upload an expired document from their status page. The status becomes NeedsChanges → resubmit → review → approve. `VerificationService` allows the `Suspended → NeedsChanges` transition (defined in Task 1) only when `rejection_reason` starts with `Document expired:`. An admin suspension stays admin-only to lift.

- [ ] **Step 1: Write failing tests**
```php
test_reminders_sent_once_at_30_and_7_days()          // Carbon::setTestNow; run twice → SMS mock ->once()
test_expired_license_suspends_rider_and_releases_assigned_orders()
test_expired_optional_document_does_not_suspend()    // police_clearance / tax_clearance
test_suspended_for_expiry_rider_can_reupload_and_resubmit()
```
- [ ] **Step 2: Run** `php84 artisan test tests/Feature/Kyc/KycExpiryTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Expected: PASS.
- [ ] **Step 5: Commit** `feat(kyc): daily document expiry reminders and auto-suspension`

### Task 16: Seed data, admin manual and knowledge file

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php` (one rider and one vendor in each of: draft, submitted, needs_changes, approved; one vendor product in pending_review), `resources/views/filament/pages/admin-manual.blade.php` (sections: Rider onboarding, Vendor onboarding, KYC document checklist from the matrix, Location change requests, Vendor payouts, Expiry suspensions), `KNOWLEDGE.md` (guards table + vendor guard, new services, new commands, consignment model), `.env.example` (no new keys unless the implementation added any)
- Test: existing `tests/Feature/AdminManualTest.php` extended with `assertSee('Vendor onboarding')`

- [ ] **Step 1: Extend `AdminManualTest`** with assertions for the new section headings.
- [ ] **Step 2: Run** `php84 artisan test tests/Feature/AdminManualTest.php`. Expected: FAIL.
- [ ] **Step 3: Update the seeder, manual and knowledge file.** Then `php84 artisan migrate:fresh --seed` must succeed on SQLite.
- [ ] **Step 4: Run the full suite** `php84 artisan test`. Expected: PASS except the known baseline failure.
- [ ] **Step 5: Commit** `docs: onboarding seed data, admin manual and knowledge update`

---

## Deployment notes (production MariaDB)
- Keep index names ≤ 64 chars and give explicit names to composite and morph indexes (e.g. `kyc_docs_party_type_unique`). See KNOWLEDGE.md → Gotchas.
- `storage/app/private/kyc` must not be web-reachable. It sits outside `public_html` on the server, so this already holds.
- Frontend assets: rebuild and commit `public/build` only if the Tailwind classes used in custom Blade views change.
- After deploy, a super admin should open `/admin/delivery-agents` and confirm that existing riders show as Approved (D10).
