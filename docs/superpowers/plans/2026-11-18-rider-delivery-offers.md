# Rider Delivery Offers Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Riders claim orders from a shared offer board. Each offer shows the rider pay up front, the pay boosts while the order stays unclaimed, and the first rider to accept wins.

**Architecture:**
- An order gets `offer_opened_at` when packing starts and it still has no rider.
- The current pay is computed from the city's pay settings and the minutes elapsed since `offer_opened_at`, so no scheduler is needed.
- `DeliveryOfferService` owns listing, accept (a single conditional UPDATE), decline and manual assignment.
- A new Filament rider page (Offers) and the existing Warehouse Operations page call that service.

**Tech Stack:** Laravel 13, Filament 5, Livewire, PHPUnit 12 (in-memory SQLite). Run tools with `php84`.

**Spec:** `docs/superpowers/specs/2026-11-18-rider-delivery-offers-design.md`

## Global Constraints
- **City pay defaults:** `rider_base_pay` 50, `rider_pay_boost` 10, `rider_pay_max` 100, `offer_boost_minutes` 2.
- **Current pay formula:** `min(max, base + boost × floor(minutes since offer_opened_at / boost_minutes))`, never below `base`.
- **Hoarding limit:** a rider may hold fewer than 2 open deliveries to accept another. An open delivery has `delivery_status` assigned, picked_up or out_for_delivery, on an order that is not cancelled.
- **Privacy:** before acceptance, offers never show the customer's name, phone, street or landmark. They show only `address.area`, the delivery zone name, the warehouse name, item count, COD amount, distance and pay.
- **Audit:** use the existing `AuditLog::record(...)` for accept and manual assign.
- **Known failing test:** the pre-existing `FastDeliveryArchitectureTest::test_customer_selecting_city_locks_catalog_to_that_city` is expected to fail and is not ours to fix.

## Review Focus
1. Two riders accept the same order at once. Only one wins; the other gets `false` and the order keeps the first rider. Owner: Task 2.
2. A rider from another city, or one who is offline or unapproved, calls accept with a crafted id. It is refused. Owner: Task 2.
3. An order is cancelled, or a rider is assigned, after the board loaded and before accept. It is refused. Owner: Task 2.
4. The Offers page HTML must not contain the customer's phone, street or name. Owner: Task 3.
5. A city whose `rider_pay_max` is below `rider_base_pay` gets the base pay, not a lower or negative amount. Owner: Task 1.

---

### Task 1: Schema, settings and pay calculation

**Files:**
- Create: `database/migrations/2026_11_18_000002_add_rider_delivery_offers.php`
- Modify: `app/Models/City.php` (fillable and casts)
- Modify: `app/Models/Order.php` (fillable, casts, `transitionOrderStatus`, new methods)
- Modify: `app/Filament/Resources/Cities/CityResource.php` (four numeric inputs in the form, near `default_delivery_fee`)
- Test: `tests/Feature/Rider/DeliveryOfferPayTest.php`

**Interfaces:**
- Produces:
  - **`cities` columns:** `rider_base_pay`, `rider_pay_boost` and `rider_pay_max` as decimal(8,2), defaulting to 50, 10 and 100. `offer_boost_minutes` as unsigned small int, default 2.
  - **`orders` columns:** `offer_opened_at` (timestamp, nullable) and `rider_pay` (decimal(8,2), nullable).
  - **Table `order_offer_declines`:**
    - columns: id, `order_id` (FK, cascade on delete), `delivery_agent_id` (FK, cascade on delete), `declined_pay` (decimal(8,2)), timestamps
    - unique on (`order_id`, `delivery_agent_id`)
  - **`Order::currentOfferPay(): ?float`:** returns null when `offer_opened_at` is null. Otherwise it applies the formula from Global Constraints, never below base. A `boost_minutes` of 0 or less is treated as 1.
  - **`Order::offerStuck(): bool`:** true when the order has no rider, the offer is open, and the minutes since opening are at least `ceil((max-base)/boost) × boost_minutes + boost_minutes`. In other words, the pay hit the maximum one interval ago. A boost of 0 or less counts as already at max.
  - **`transitionOrderStatus` change:** when moving to `Processing` and `delivery_agent_id` is null and `offer_opened_at` is null, it sets `offer_opened_at = now()` before saving.

- [ ] **Step 1: Write failing tests** in `DeliveryOfferPayTest`. Create the City, Warehouse and Order the same way `tests/Feature/RiderPanelTest.php` does, with order_status Confirmed and no rider.
  - `test_starting_packing_opens_offer_for_unassigned_order`: `transitionOrderStatus(Processing)` makes `offer_opened_at` not null.
  - `test_starting_packing_does_not_open_offer_when_rider_already_assigned`
  - `test_pay_starts_at_base_and_boosts_each_interval_up_to_max`, using `$this->travel()`. These must all be exact:

    | Minutes since opening | Expected pay |
    |---|---|
    | 0 | 50.0 |
    | 1 | 50.0 |
    | 2 | 60.0 |
    | 9 | 90.0 |
    | 10 | 100.0 |
    | 30 | 100.0 |
  - `test_pay_never_drops_below_base_when_max_is_lower`: base 80 and max 60 give 80.0. (Review Focus 5.)
  - `test_offer_is_stuck_one_interval_after_reaching_max`: false at 11 minutes, true at 12, false once a rider is set.
  - `test_unopened_offer_has_no_pay`: returns null.

- [ ] **Step 2: Run** `php84 artisan test --filter=DeliveryOfferPayTest`. Expected: FAIL (missing columns or methods).
- [ ] **Step 3: Implement** the migration, model changes and CityResource inputs. The inputs are `numeric()->minValue(0)->prefix('Rs')` with a helper text explaining boosts; `offer_boost_minutes` uses `minValue(1)`.
- [ ] **Step 4: Run** the same filter. Expected: PASS. Then run `php84 artisan test`. Expected: only the known failure.
- [ ] **Step 5: Commit** with the message `feat(delivery): offer pay settings and boost calculation`.

### Task 2: DeliveryOfferService

**Files:**
- Create: `app/Services/Delivery/DeliveryOfferService.php`
- Modify: `app/Models/DeliveryAgent.php`. Add `openDeliveriesCount(): int`, `goOnline(): bool` and `goOffline(): bool`. In `releaseUnstartedDeliveries`, also null `rider_pay`.
- Create: `app/Models/OrderOfferDecline.php`
- Test: `tests/Feature/Rider/DeliveryOfferServiceTest.php`

**Interfaces:**
- Consumes: everything Task 1 produces.
- Produces, in `App\Services\Delivery\DeliveryOfferService`:
  - **`const MAX_OPEN_DELIVERIES = 2`**
  - **`openOffersFor(DeliveryAgent $rider): Collection<Order>`**
    - Returns orders in the rider's `city_id` where `delivery_agent_id` is null, `offer_opened_at` is not null, and `order_status` is in [Processing, Packed, ReadyForDispatch].
    - Eager loads `address.deliveryZone`, `warehouse` and `items`.
    - Excludes orders this rider declined at a `declined_pay` greater than or equal to `currentOfferPay()`.
    - Returns an empty collection unless the rider is `available` (scope `available()`).
    - Ordered oldest first.
  - **`accept(Order $order, DeliveryAgent $rider): bool`**
    - Returns false unless the rider is in `DeliveryAgent::available()`, `$order->city_id === $rider->city_id`, and `$rider->openDeliveriesCount() < MAX_OPEN_DELIVERIES`.
    - Then runs one `Order::whereKey()->whereNull('delivery_agent_id')->whereNotNull('offer_opened_at')->whereIn('order_status', [...open statuses])->update([...])`. The update sets `delivery_agent_id`, `delivery_status = assigned` and `rider_pay = currentOfferPay()`.
    - Returns true only if exactly 1 row was updated, and then writes `AuditLog::record('delivery_offer_accepted', ...)`.
  - **`decline(Order $order, DeliveryAgent $rider): void`:** `updateOrCreate` on the decline row with `declined_pay = currentOfferPay()`.
  - **`assignManually(Order $order, DeliveryAgent $rider, ?int $userId): void`:** sets the rider, `delivery_status = assigned`, and `rider_pay = currentOfferPay() ?? city.rider_base_pay`, then saves and writes `AuditLog::record('delivery_manually_assigned', ...)`.
  - **`static distanceKm(Order $order): ?float`:**
    - The haversine distance from `warehouse` lat/lng to `address` lat/lng, rounded to 1 decimal.
    - Returns null if any coordinate is missing.
    - The address coordinates are encrypted strings, so cast them to float.
- Produces, on `DeliveryAgent`:
  - **`openDeliveriesCount(): int`:** counts orders with `delivery_status` assigned, picked_up or out_for_delivery, where `order_status` is not cancelled.
  - **`goOnline(): bool`:** sets `availability` to `available` and returns true only if the rider passes `eligibleForDispatch`. Otherwise it changes nothing and returns false.
  - **`goOffline(): bool`:** returns false and changes nothing while `openDeliveriesCount() > 0`. Otherwise it sets `availability` to `offline` and returns true.

- [ ] **Step 1: Write failing tests**, each creating its fixtures as in Task 1.
  - `test_available_rider_sees_open_offers_in_their_city_only`: another city's order is not listed, and an offline rider gets an empty list.
  - `test_accept_assigns_rider_and_locks_current_pay`: travel 4 minutes, then the order has the rider, `delivery_status` is assigned, `rider_pay` is 70.00, and an audit row with action `delivery_offer_accepted` exists.
  - `test_second_rider_cannot_take_an_already_accepted_order`: rider A accepts using a fresh model and gets true. Rider B accepts using a **stale** `$order` instance loaded before A accepted and gets false, and the order still has rider A. (Review Focus 1.)
  - `test_accept_refused_for_other_city_offline_or_unapproved_rider`: three riders, each gets false, and the order is still unassigned. (Review Focus 2.)
  - `test_accept_refused_for_cancelled_order`. (Review Focus 3.)
  - `test_rider_with_two_open_deliveries_cannot_accept_more`
  - `test_declined_offer_hides_until_pay_rises`: decline at minute 0 and the offer is hidden. At minute 1 it is still hidden. At minute 2 (pay 60) it is listed again.
  - `test_manual_assignment_records_rider_pay`: at minute 0 that is 50.00, and an audit row exists.
  - `test_rider_cannot_go_offline_with_an_assigned_order`: once the order is delivered, `goOffline()` returns true.
  - `test_released_delivery_returns_to_the_board`: `releaseUnstartedDeliveries()` makes `rider_pay` null, and the order is listed in `openOffersFor` for another rider.
- [ ] **Step 2: Run** `php84 artisan test --filter=DeliveryOfferServiceTest`. Expected: FAIL (class not found).
- [ ] **Step 3: Implement** the service, the decline model and the rider methods.
- [ ] **Step 4: Run** the filter. Expected: PASS. Then run the full suite. Expected: only the known failure.
- [ ] **Step 5: Commit** with the message `feat(delivery): offer board service with atomic accept`.

### Task 3: Rider Offers page, online toggle and earnings

**Files:**
- Create: `app/Filament/Rider/Pages/DeliveryOffers.php`. Navigation label "Offers", icon `Heroicon::OutlinedBolt`, `navigationSort` placing it before My Deliveries, and `canAccess` returning true only when the rider `isApproved()`.
- Create: `resources/views/filament/rider/pages/delivery-offers.blade.php`, using the same styling as `my-deliveries.blade.php`. The wrapper has `wire:poll.10s`.
- Modify: `app/Providers/Filament/RiderPanelProvider.php`. Register the page. `homeUrl` sends approved riders to `DeliveryOffers::getUrl()`.
- Modify: `app/Filament/Rider/Pages/MyDeliveries.php` and its view. Show "Earning Rs X" on each order card that has `rider_pay`. Add a stat `earnedToday`, the sum of `rider_pay` over delivered orders with `delivered_at` today.
- Test: `tests/Feature/Rider/DeliveryOffersPageTest.php`

**Interfaces:**
- Consumes: `DeliveryOfferService::openOffersFor`, `accept`, `decline` and `distanceKm`; `DeliveryAgent::goOnline` and `goOffline`; `Order::currentOfferPay`.
- Produces, on the `DeliveryOffers` Livewire component:
  - **`getOffersProperty(): Collection`**
  - **`acceptOffer(int $orderId): void`:**
    - On success, notifies "Order accepted" and redirects to `MyDeliveries::getUrl()`.
    - On false, shows a warning notification titled "Already taken or not available".
    - Loads the order with `Order::find`; a missing id is treated as false.
  - **`declineOffer(int $orderId): void`**
  - **`toggleOnline(): void`:** calls `goOnline` or `goOffline` depending on the current state. When `goOffline` returns false, notifies "Finish or hand back your current deliveries before going offline".
- **View content:**
  - An Online/Offline toggle button with the label text "You are online" or "You are offline". When offline, show "Go online to see delivery offers".
  - Each offer card shows:
    - the warehouse name
    - "Drop-off: {area}", plus the zone name when present
    - "{n} items"
    - "Collect Rs {grand_total}", only for COD
    - "~{km} km", when not null
    - the large "Rs {currentOfferPay}"
    - Accept and Decline buttons
  - An empty state reading "No offers right now."

- [ ] **Step 1: Write failing tests** using `Livewire::actingAs($rider, 'delivery')` and `Filament::setCurrentPanel('rider')`, as `RiderPanelTest` does.
  - `test_offers_page_lists_offer_with_pay_and_area_but_no_customer_details`: the address has full_name 'Sita Sharma', phone '9800000001', street 'Gali 4 House 22' and area 'Putali Line'. Assert see 'Putali Line' and 'Rs 50'. Assert don't see 'Sita Sharma', '9800000001' or 'Gali 4'. (Review Focus 4.)
  - `test_rider_accepts_offer_from_page`: `call('acceptOffer', $id)` gives the order this rider's id and redirects.
  - `test_accepting_a_taken_offer_shows_warning_and_keeps_owner`
  - `test_decline_removes_offer_from_page`
  - `test_toggle_online_and_blocked_offline_with_assigned_order`
  - `test_unapproved_rider_cannot_open_offers_page`: `get(DeliveryOffers::getUrl())` while authenticated on the delivery guard returns 403.
  - `test_my_deliveries_shows_earning`: an assigned order with `rider_pay` 70 shows 'Rs 70'.
- [ ] **Step 2: Run** `php84 artisan test --filter=DeliveryOffersPageTest`. Expected: FAIL.
- [ ] **Step 3: Implement** the page, the view, the provider registration and the MyDeliveries changes.
- [ ] **Step 4: Run** the filter, then `php84 artisan test --filter=RiderPanelTest` to check the homeUrl change, then the full suite. Expected: PASS apart from the known failure.
- [ ] **Step 5: Commit** with the message `feat(rider): offers board page, online toggle and earnings`.

### Task 4: Warehouse Operations, admin visibility and docs

**Files:**
- Modify: `app/Filament/Pages/WarehouseOperations.php`
  - In `dispatchOrder`, when a `$riderId` is given, call `DeliveryOfferService::assignManually` instead of setting fields directly.
  - When no `$riderId` is given and the order already has a rider, keep that rider and don't auto-pick.
  - When no `$riderId` is given and there is no rider, keep the existing auto-pick but route it through `assignManually`.
  - Restrict the manually chosen rider to `eligibleForDispatch` and the same city. Otherwise abort 422.
- Modify: the Warehouse Operations Blade view, found via `$view` in the class. On picking, packing and ready cards with no rider, show "Offer Rs {currentOfferPay} · waiting {n} min". When `offerStuck()` is true, show a red badge reading "No rider at max pay — assign manually". On cards with a rider, show the rider name and "Rs {rider_pay}".
- Modify: `app/Filament/Resources/Orders/OrderResource.php`. Add a `TextEntry::make('rider_pay')->label('Rider Pay')->money('NPR')` next to the delivery fee entry.
- Modify: `resources/views/filament/pages/admin-manual.blade.php`. Add TOC entry "28 Rider delivery offers" and a section covering the city pay settings, how boosts work, the stuck badge, manual override, and that riders see only the area before accepting.
- Modify: `resources/views/storefront/help.blade.php`, the become-rider step 4. Mention that riders go online and accept offers with the pay shown up front.
- Modify: `KNOWLEDGE.md`. Add a short Delivery offers note with the service name and formula.
- Test: `tests/Feature/Rider/WarehouseOfferDispatchTest.php`

**Interfaces:**
- Consumes: `DeliveryOfferService::assignManually`, `Order::currentOfferPay` and `Order::offerStuck`.

- [ ] **Step 1: Write failing tests** acting as a super-admin on the `web` guard, mirroring the setup in `tests/Feature/WarehouseManagerScopeTest.php`.
  - `test_dispatch_keeps_rider_who_accepted_offer`: an order with rider A accepted, `dispatchOrder($id)` with no riderId, then the order still has rider A and order_status is dispatched.
  - `test_manual_dispatch_records_rider_pay_and_audit`
  - `test_manual_dispatch_rejects_rider_from_another_city`: the response has status 422 and the order is unchanged.
  - `test_board_shows_stuck_badge_after_max_pay`: travel 12 minutes, then see 'No rider at max pay'.
- [ ] **Step 2: Run** `php84 artisan test --filter=WarehouseOfferDispatchTest`. Expected: FAIL.
- [ ] **Step 3: Implement** the page, view, resource and docs changes.
- [ ] **Step 4: Run** the filter, then the full suite. Expected: only the known failure. Run `php84 vendor/bin/pint --dirty`.
- [ ] **Step 5:** If any storefront Blade Tailwind classes changed, run `npm run build` and `git add -f public/build`. Then commit with the message `feat(warehouse): show offer pay, stuck alert, manual assign via offer service; docs`.
