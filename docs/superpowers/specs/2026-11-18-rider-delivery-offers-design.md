# Rider delivery offers (DoorDash-style) — design

**Date:** 2026-11-18 · **Size:** medium feature, short design

## Intent
Riders choose their work, the way DoorDash does: each order goes out as an offer with the rider pay shown up front. Riders accept or decline. If nobody accepts, the pay rises step by step until someone does. Today warehouse staff pick a rider (or the first eligible one) at dispatch, and nothing records what the rider earns.

## Goals
- Riders see open offers in their city with the pay, and claim one with a single tap. First accept wins.
- Pay rises automatically for orders that stay unclaimed, up to a per-city maximum.
- The accepted pay is saved on the order, as the rider's earning for that delivery.
- Staff keep a manual override and get an alert when an order reaches maximum pay and still has no rider.

## Non-goals
- Riders bidding prices down (a reverse auction). The user explicitly chose the DoorDash model.
- Sending offers to one rider at a time with timers (approach B, rejected).
- Live GPS tracking, rider payouts or settlements, push notifications or SMS for offers.

## Anti-goals
- Two riders end up holding the same order.
- A rider sees the customer's name, phone or exact address before accepting. The privacy policy says riders get customer data only to complete *their* delivery.
- Deliveries get slower than the current manual dispatch.

## Approaches
- **A. Shared offer board (chosen).** Every available, approved rider in the city sees the offer, and the first to accept gets it. This is the fastest way to get a rider. It works with the rider panel's existing polling and needs no background worker.
- **B. One rider at a time with a 45 s timer.** This is fairer and closest to DoorDash itself. It was rejected because it is slower with a small rider pool, needs an always-on queue worker, and "next rider" can't mean nearest because there is no live location.
- **C. Reverse-auction low bids.** Rejected by the user in favour of the DoorDash model.

## Design
- **Per-city settings:** these are new fields on `cities`, editable in CityResource. The default values are my call.

  | Field | Default |
  |---|---|
  | `rider_base_pay` | Rs 50 |
  | `rider_pay_boost` | Rs 10 |
  | `rider_pay_max` | Rs 100 |
  | `offer_boost_minutes` | 2 |
- **When an offer opens:** when an order with no rider moves to `processing` (packing starts). Staff can still assign a rider directly at any point. *My call:* opening at packing time rather than at "ready" means the rider arrives about when the bag is ready, which protects the 15–30 min promise.
- **What the order stores:**
  - `offer_opened_at`, set when the offer opens.
  - `rider_pay`, saved when a rider accepts or staff assign one.
  - Current pay is computed, not stored: `min(max, base + boost × floor(minutes since opened / boost_minutes))`. Boosts therefore need no scheduler.
  - A manual staff assignment also records `rider_pay`, using the pay the offer had reached at that moment.
- **What a rider sees before accepting:**
  - pickup warehouse
  - drop-off area: delivery zone or tole/ward, never the street address
  - rough distance, when both coordinates exist
  - item count
  - COD amount to collect
  - pay

  The full address and contact details appear only after the rider accepts, in My Deliveries.
- **Accept:** a single conditional update (`where delivery_agent_id is null and order not cancelled`), so only one rider can win. The rider must be approved, `available`, and hold fewer than 2 open deliveries (*my call*, so riders can't hoard orders). On success the order gets the rider, `delivery_status = assigned`, and `rider_pay`. The rider who loses the race sees "Already taken".
- **Decline:** recorded per rider with the pay at that moment (new table `order_offer_declines`). The offer reappears to that rider once the pay rises above what they declined.
- **Going online:** riders get an Online/Offline toggle that sets `availability`. Today only admins can change availability. Riders with an assigned order can't go offline until they have finished it or handed it back.
- **Rider panel:**
  - A new "Offers" page that refreshes every 10 s.
  - My Deliveries shows the earning on each order and today's total earnings.
- **Staff side:**
  - Warehouse Operations shows each open offer's current pay and how long it has been waiting.
  - A red "No rider at max pay — assign manually" badge appears once the pay has hit the maximum and one more boost interval has passed.
  - The existing dispatch assignment still works. `dispatchOrder` no longer auto-picks "first eligible rider" when a rider has already accepted.
- **Cancellation:** a cancelled order disappears from the board. A rider's accepted but not yet picked-up order is cancelled the same way as today.
- **Audit:** AuditLog records offer acceptances and manual assignments.

## Left to the builder
Exact UI layout of the Offers page (follow the My Deliveries styling), migration names, and test structure.
