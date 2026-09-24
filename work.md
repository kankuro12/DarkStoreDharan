# Fast Delivery E-Commerce Platform
## Laravel Architecture, Technology Stack, and Business Flow

## 1. Concept Overview

This platform is designed as a **city-first, local-inventory e-commerce system** focused on fast delivery.

The core customer journey is:

**Choose City → View Products Available in That City → Add to Cart → Checkout → Pay Online or Cash on Delivery → Fulfill from Local Warehouse → Fast Delivery**

This is different from a normal e-commerce platform because product availability, pricing, delivery time, delivery fee, Cash on Delivery (COD), and promotional content can vary by city.

> Design and performance constraints for this system are defined in **Section 38**. Read that section before implementation begins — several database and caching decisions in Sections 5–21 depend on it.

---

## 2. Main Customer Flow

```text
Open Website / Mobile App
        ↓
Choose City
        ↓
Save city_id
        ↓
Homepage / Categories / Search
        ↓
Show Only Products Available in Selected City
        ↓
Product Details
        ↓
Add to Cart
        ↓
Validate Cart Against Selected City
        ↓
Checkout
        ↓
Choose / Add Delivery Address
        ↓
Delivery Option
        ↓
Payment Method
   ┌──────────────┴──────────────┐
   ↓                             ↓
Prepaid                         COD
   ↓                             ↓
Payment Gateway                 Place Order
   ↓                             ↓
Payment Verification            COD Confirmation
   └──────────────┬──────────────┘
                  ↓
           Inventory Reserved
                  ↓
          Warehouse Assigned
                  ↓
           Packing / Dispatch
                  ↓
            Fast Delivery
                  ↓
              Delivered
```

---

## 3. City Selection

The customer should choose a city before browsing products.

Example:

```text
Select your delivery city

[ Biratnagar ]
[ Itahari ]
[ Dharan ]
[ Kathmandu ]
[ Other ]
```

After selection:

```text
Delivering to:
Biratnagar
```

The selected city should be stored in:

- Session for guest users
- Frontend application state / local storage
- User profile for logged-in customers

Example API:

```http
GET /api/products?city_id=3
GET /api/categories/5/products?city_id=3
GET /api/search?q=milk&city_id=3
```

Product filtering must be enforced by Laravel, not only by the frontend.

---

## 4. Recommended Inventory Model

Do not connect products directly to cities.

Recommended model:

```text
City
 ↓
Warehouse / Fulfillment Center
 ↓
Inventory
 ↓
Product Variant
```

Example:

```text
Biratnagar
   ↓
Biratnagar Warehouse
   ├── Product A = 15
   ├── Product B = 0
   └── Product C = 8

Itahari
   ↓
Itahari Warehouse
   ├── Product A = 5
   ├── Product B = 12
   └── Product C = 0
```

If a user selects Biratnagar, the catalog can display Product A and Product C.

Unavailable products can either:

- Be hidden from main listings
- Be shown as unavailable when opened directly

Example:

```text
Currently unavailable in Biratnagar.
Change city to check availability.
```

---

## 5. Core Database Design

### 5.1 `cities`

```text
id
name
slug
province
status
cod_enabled
prepaid_enabled
default_delivery_fee
free_delivery_minimum
estimated_delivery_minutes
```

---

### 5.2 `warehouses`

```text
id
name
code
address
latitude
longitude
status
```

---

### 5.3 `warehouse_city`

Defines which warehouse can fulfill which city.

```text
id
warehouse_id
city_id
priority
delivery_minutes
delivery_fee
status
```

Example:

```text
Warehouse A → Biratnagar → Priority 1
Warehouse B → Biratnagar → Priority 2
```

---

## 6. Products and Variants

### `products`

```text
id
name
slug
brand_id
category_id
description
status
```

### `product_variants`

```text
id
product_id
sku
barcode
name
price
weight
status
```

Example:

```text
Coke
 ├── 250ml
 ├── 500ml
 └── 1.5L
```

Inventory should be maintained at the variant level.

---

## 7. Inventory

### `inventories`

```text
id
warehouse_id
product_variant_id
quantity
reserved_quantity
reorder_level
```

Available stock:

```text
available = quantity - reserved_quantity
```

Example:

```text
Warehouse      Product       Qty     Reserved     Available

Biratnagar     Coke 500ml    50      5            45
Itahari        Coke 500ml    20      2            18
```

---

## 8. City-Specific Pricing

The system should support different prices by city when needed.

### `product_prices`

```text
id
product_variant_id
city_id nullable
warehouse_id nullable
price
sale_price
start_at
end_at
```

Example:

```text
Biratnagar → Rs. 950
Kathmandu → Rs. 990
```

If no city-specific price exists, use the default variant price.

---

## 9. Delivery Zones

For fast delivery, city-level delivery is often too broad.

Recommended structure:

```text
City
 ↓
Delivery Zones
```

Example:

```text
Biratnagar
 ├── Zone A → 30–45 minutes
 ├── Zone B → 45–60 minutes
 └── Zone C → 60–90 minutes
```

### `delivery_zones`

```text
id
city_id
name
postal_code
latitude
longitude
radius
delivery_fee
minimum_order
estimated_minutes
cod_enabled
status
```

Future versions can support geofencing and polygon-based service areas.

---

## 10. COD and Prepaid Configuration

Payment configuration should support multiple levels.

### Global

```text
COD enabled
Prepaid enabled
```

### City

```text
Biratnagar
COD = true
Prepaid = true
```

### Delivery Zone

```text
Zone A
COD = true

Zone C
COD = false
```

### Product

Some products may have:

```text
COD allowed = false
```

This is useful for expensive or high-risk products.

---

## 11. Payment Method Rule Resolution

Recommended decision flow:

```text
Global COD enabled?
        ↓
City COD enabled?
        ↓
Zone COD enabled?
        ↓
Product allows COD?
        ↓
Order amount within COD limit?
        ↓
Customer eligible for COD?
        ↓
Show COD
```

The backend should calculate allowed payment methods.

Example:

```php
PaymentMethodService::availableFor($cart, $customer, $address);
```

Possible response:

```json
{
    "prepaid": true,
    "cod": false
}
```

---

## 12. Backend Payment Settings

Admin settings:

```text
Settings
 └── Payments
      ├── Prepaid
      │    ├── Enabled
      │    └── Gateway Settings
      │
      └── Cash on Delivery
           ├── Enabled
           ├── Maximum COD Amount
           ├── Minimum COD Amount
           ├── COD Charge
           └── Require Phone Verification
```

City settings:

```text
Cities
 └── Biratnagar
      ├── Active
      ├── COD Enabled
      ├── Prepaid Enabled
      ├── Delivery Charge
      ├── Free Delivery Threshold
      └── Fast Delivery SLA
```

---

## 13. Checkout Flow

### Step 1: Validate City

```text
Selected City = Biratnagar
```

### Step 2: Revalidate Stock

Never trust only the stock displayed on the product page.

Laravel should recheck:

- Product
- Variant
- Warehouse
- Available stock
- Current price

### Step 3: Delivery Address

Recommended fields:

```text
Full Name
Phone
City
Area
Street
Landmark
Latitude
Longitude
Delivery Notes
```

If the user changes city during checkout, the cart must be revalidated.

---

## 14. City Change Behavior

Example current cart:

```text
City: Biratnagar

Product A
Product B
Product C
```

Customer changes to Itahari.

Laravel validates the cart again:

```text
Product A ✓
Product B ✕ Unavailable
Product C ✓
```

The system should show:

```text
Your cart has changed because some products are unavailable in Itahari.
```

Do not silently remove unavailable products.

---

## 15. Warehouse Selection

Laravel should choose the best warehouse capable of fulfilling the order.

Example:

```text
Biratnagar City

Warehouse A:
100% items available
3 km away

Warehouse B:
80% items available
1 km away
```

Recommended result:

```text
Choose Warehouse A
```

because it can fulfill the complete order.

For the first version, avoid split shipments.

Future support can include:

```text
Order
 ├── Shipment #1 → Warehouse A
 └── Shipment #2 → Warehouse B
```

---

## 16. Order Database Structure

### `orders`

```text
id
order_number
user_id
city_id
warehouse_id
address_id
subtotal
discount
delivery_fee
tax
grand_total
payment_method
payment_status
fulfillment_status
placed_at
confirmed_at
packed_at
dispatched_at
delivered_at
```

### `order_items`

```text
order_id
product_id
variant_id
product_name
sku
quantity
unit_price
discount
tax
total
```

Store product and price snapshots inside the order.

Do not depend only on current product data because catalog information may change later.

---

## 17. Separate Status Types

Do not use one generic status for the whole order lifecycle.

### Order Status

```text
pending
confirmed
processing
packed
ready_for_dispatch
dispatched
delivered
cancelled
returned
```

### Payment Status

```text
unpaid
pending
paid
failed
refunded
partially_refunded
```

### Delivery Status

```text
pending
assigned
picked_up
out_for_delivery
delivered
failed
returned
```

---

## 18. Prepaid Payment Flow

```text
Checkout
   ↓
Validate Stock and Pricing
   ↓
Create Pending Order
   ↓
Reserve Inventory
   ↓
Create Payment Transaction
   ↓
Redirect / Open Payment Gateway
   ↓
Gateway Callback
   ↓
Server-Side Verification
   ↓
Payment = PAID
   ↓
Order = CONFIRMED
   ↓
Warehouse Notified
```

Do not mark payment as successful only because the browser returns to a success URL.

Always verify payment server-side with the gateway.

---

## 19. Failed Prepaid Payment

```text
Payment Failed
      ↓
Order Remains Payment Pending
      ↓
Allow Customer to Retry
```

After a configured timeout such as 15 or 30 minutes:

```text
ReleaseInventoryReservation
```

This prevents stock from being locked indefinitely.

---

## 20. COD Flow

```text
Checkout
 ↓
Select COD
 ↓
Validate COD Eligibility
 ↓
Reserve Inventory
 ↓
Create Confirmed Order
 ↓
Warehouse Receives Order
```

Optional:

```text
OTP Verification
```

This is recommended to reduce fake COD orders.

---

## 21. Inventory Reservation

Inventory reservation is essential in a high-speed e-commerce system.

Problem example:

```text
Only 1 item is available.

Customer A → Buy
Customer B → Buy
```

Without proper locking, both orders can succeed.

Use Laravel database transactions with row locking.

Example:

```php
DB::transaction(function () {

    $inventory = Inventory::where(...)
        ->lockForUpdate()
        ->first();

    if ($inventory->available < $quantity) {
        throw new OutOfStockException();
    }

    $inventory->increment('reserved_quantity', $quantity);
});
```

---

## 22. Fast Delivery Operational Flow

```text
Order Placed
     ↓
Auto Select Fulfillment Center
     ↓
Notify Warehouse
     ↓
Picker Receives Order
     ↓
Picking
     ↓
Packing
     ↓
Delivery Rider Assignment
     ↓
Pickup
     ↓
Out for Delivery
     ↓
Delivered
```

Recommended warehouse dashboard:

```text
NEW
3 Orders

PICKING
5 Orders

PACKING
2 Orders

READY
4 Orders

OUT FOR DELIVERY
7 Orders
```

This workflow is much more important for fast commerce than a traditional order table.

---

## 23. Delivery Rider Module

Future delivery staff structure:

### `delivery_agents`

```text
id
name
phone
city_id
status
availability
current_latitude
current_longitude
```

Order can contain:

```text
delivery_agent_id
```

Possible rider statuses:

```text
Available
Assigned
Picking Up
Delivering
Offline
```

---

## 24. Recommended Laravel Stack

Suggested backend stack:

```text
Laravel
PHP 8.4+
MariaDB / MySQL
Redis
Laravel Queue
Laravel Horizon
Laravel Reverb
Nginx
Cloudflare
```

Recommended initial deployment:

```text
Nginx
PHP-FPM
Redis
MySQL / MariaDB
Queue Workers
```

Use Laravel Octane later only if traffic and performance requirements justify it.

---

## 25. Recommended Laravel Application Structure

```text
app/
├── Models/
│   ├── Product.php
│   ├── ProductVariant.php
│   ├── Inventory.php
│   ├── Warehouse.php
│   ├── City.php
│   ├── Order.php
│   └── Payment.php
│
├── Services/
│   ├── Catalog/
│   │   └── CityCatalogService.php
│   │
│   ├── Inventory/
│   │   ├── InventoryService.php
│   │   └── StockReservationService.php
│   │
│   ├── Cart/
│   │   └── CartService.php
│   │
│   ├── Checkout/
│   │   ├── CheckoutService.php
│   │   └── PricingService.php
│   │
│   ├── Payment/
│   │   ├── PaymentService.php
│   │   ├── CODService.php
│   │   └── GatewayService.php
│   │
│   ├── Fulfillment/
│   │   ├── FulfillmentService.php
│   │   └── WarehouseSelector.php
│   │
│   └── Delivery/
│       └── DeliveryService.php
│
├── Actions/
│   ├── PlaceOrder.php
│   ├── ReserveStock.php
│   ├── ConfirmPayment.php
│   ├── CancelOrder.php
│   └── AssignDeliveryAgent.php
│
├── Jobs/
│   ├── ReleaseExpiredReservation.php
│   ├── NotifyWarehouse.php
│   ├── SendOrderNotification.php
│   └── ProcessPaymentCallback.php
│
└── Events/
    ├── OrderPlaced.php
    ├── OrderPacked.php
    ├── OrderDispatched.php
    └── OrderDelivered.php
```

Avoid putting all business logic directly inside controllers.

---

## 26. Queue Usage

Use Laravel queues for:

```text
SMS
Email
Push Notifications
Invoice Generation
Warehouse Notification
Analytics
Payment Receipt
Delivery Assignment
```

Suggested flow:

```text
Order Confirmed
      ↓
Events
      ↓
Redis Queue
      ↓
Workers
 ├── Send SMS
 ├── Send Email
 ├── Send Push
 ├── Generate Invoice
 └── Notify Warehouse
```

This keeps checkout fast.

---

## 27. Redis Usage

Recommended Redis use cases:

```text
City Catalog
Categories
Homepage Sections
Popular Products
Product Availability
Configuration
Delivery Rules
Sessions
Cart
Rate Limiting
Queues
```

Example cache key:

```text
catalog:city:1:category:5
```

When inventory changes, relevant cache entries should be invalidated.

Do not aggressively cache final checkout calculations because stock and pricing must be current.

---

## 28. Real-Time Order Updates

Use Laravel Reverb or another WebSocket solution for live updates.

Example:

```text
Warehouse Changes Order Status → PACKED
```

Backend fires:

```text
OrderStatusChanged
```

Customer immediately sees:

```text
Your order has been packed.
```

The admin dashboard can also update without reloading.

---

## 29. Suggested API Design

```text
GET    /api/cities
GET    /api/cities/{city}/home

GET    /api/categories
GET    /api/products
GET    /api/products/{slug}

POST   /api/cart/items
PATCH  /api/cart/items/{id}
DELETE /api/cart/items/{id}

POST   /api/cart/change-city
POST   /api/checkout/validate

GET    /api/payment-methods

POST   /api/orders
GET    /api/orders/{order}
POST   /api/orders/{order}/cancel

POST   /api/payments/{order}/initiate
POST   /api/payments/callback

GET    /api/orders/{order}/tracking
```

---

## 30. Checkout Validation Endpoint

Recommended endpoint:

```http
POST /api/checkout/validate
```

Example request:

```json
{
    "city_id": 3,
    "address_id": 15
}
```

Example response:

```json
{
    "items": [
        {
            "sku": "ABC123",
            "quantity": 2,
            "available": true,
            "price": 850
        }
    ],
    "subtotal": 1700,
    "discount": 100,
    "delivery_fee": 50,
    "total": 1650,
    "estimated_delivery": "35-50 minutes",
    "payment_methods": {
        "prepaid": true,
        "cod": true
    }
}
```

The frontend can safely render the final checkout from this response.

---

## 31. Admin Panel — Structure, Roles, and Module Detail

### 31.1 Recommended Admin Tech Stack

The admin panel is an **internal tool**, not a customer-facing surface — it does not need to share a codebase with the plain Blade storefront (Section 39) or the mobile app strategy (Section 40). This is a separate decision, and the fastest path is different from the storefront's:

**Recommended: [Filament](https://filamentphp.com/)** (Laravel-native, built on Livewire).

```text
Why Filament over an Angular admin app:
- No second frontend build, API layer, or auth system to maintain —
  it runs directly against your Eloquent models.
- CRUD-heavy screens (Products, Warehouses, Coupons, Settings) are
  generated from a resource class in minutes, not built as separate
  Angular components + API endpoints.
- Ships table filters/sorting, form builders, relation managers, and
  role-based visibility out of the box (Section 31.2).
- If Lunar (Section 39's earlier package comparison) is adopted later
  for any sub-module, its admin hub is already Filament-based, so the
  two integrate directly instead of needing a bridge.
```

Only build the admin UI in Angular instead if the admin team explicitly needs the same design system/branding as the customer app, or if admin staff need an offline-capable mobile app (Section 31.4 covers that narrower case separately, since it has different requirements than the back-office panel).

### 31.2 Roles & Permissions (RBAC)

Do not ship the admin panel with a single "admin" role. At minimum:

```text
Role                  Can access
──────────────────────────────────────────────────────────────
Super Admin           Everything, including Settings and user management
Catalog Manager       Catalog, Marketing (not Orders, Payments, Settings)
Warehouse Manager     Inventory + Orders for their assigned warehouse(s) only
Picker / Packer       Warehouse Operations view only (Section 31.4) — no
                      access to the full admin panel at all
Delivery Coordinator  Delivery module (riders, assignments, live map)
Finance               Payments, Refunds, COD reconciliation — read-only
                      on everything else
Support               Customers, Orders (read + limited actions: cancel,
                      resend notification) — no pricing/inventory access
```

- Scope Warehouse Manager and Picker/Packer access to their **assigned warehouse(s)** at the query level (`WHERE warehouse_id IN (...)`), not just hidden in the UI — an unscoped API/Filament policy is a data-leak risk across warehouses.
- Every admin action that mutates money, stock, or order status must be attributed to a user and timestamped (Section 31.7, audit log) — "who cancelled this order" must always be answerable.
- Require 2FA for Super Admin and Finance roles at minimum, given they can issue refunds and change payment settings (Section 12).

### 31.3 Module Detail

```text
Dashboard
├── Today's orders (by status, from Section 17)
├── Revenue today / this week (with city breakdown)
├── Low-stock alerts (from Section 7's reorder_level)
├── Failed payments needing attention
└── Active riders / orders currently out for delivery

Catalog
├── Products         — CRUD, bulk image upload, bulk CSV import/export
├── Categories        — tree/nested structure, drag-to-reorder
├── Brands
├── Variants          — per-product variant matrix (Section 6)
├── Pricing           — default price + city-specific overrides (Section 8),
│                       with a clear "3 cities have a custom price" indicator
│                       so it's never silently overridden
└── Reviews            — moderation queue if customer reviews are enabled

Inventory
├── Warehouses         — CRUD, map pin (lat/long), operating hours
├── Warehouse↔City map — priority ordering per Section 5.3, so it's visible
│                       which warehouse serves which city and in what order
├── Current Stock      — filterable by warehouse + low-stock flag
├── Stock Transfer     — move stock between warehouses (Phase 2, Section 35)
├── Stock Adjustment   — manual +/- with a mandatory reason field (shrinkage,
│                       damage, recount) — never a bare quantity edit
└── Low Stock           — below reorder_level, grouped by warehouse

Locations
├── Cities              — active/inactive, COD/prepaid toggles (Section 12),
│                       default delivery fee, free-delivery threshold
├── Delivery Zones      — per-city zone list with ETA + fee (Section 9)
└── Service Areas       — map view of zone boundaries once geofencing lands
                          (Phase 3, Section 35)

Orders
├── New / Confirmed / Picking / Packing / Ready / Dispatched / Delivered /
│   Cancelled / Returned  — Kanban or filterable table by status (Section 17)
├── Order detail         — full item/price snapshot (Section 16), payment
│                          status, delivery status, and a timeline of every
│                          status change with the acting user (Section 31.7)
├── Manual status override — with a required reason, since this bypasses the
│                          normal fulfillment flow (Section 22)
└── Cancel / Refund action — triggers the reservation-release + refund flow,
                             never a direct DB edit

Delivery
├── Riders              — CRUD, current status (Section 23), assigned city
├── Assignments         — manual override of auto-assignment when needed
└── Live Deliveries     — map view, updates via Reverb (Section 28)

Payments
├── Online Payments     — gateway transaction log, reconciliation status
├── COD                 — COD orders, collection status, cash reconciliation
│                          per rider/day
├── Failed Payments     — retry / abandon, with the gateway's failure reason
│                          surfaced (never just "failed")
└── Refunds             — refund queue with approval step for Finance role

Customers
├── Customers           — profile, order history, lifetime value
├── Addresses
├── Order History
└── COD Restrictions     — flag customers who abuse COD (repeated refusals),
                          feeding the eligibility check in Section 11

Marketing
├── Coupons             — usage limits, per-city scoping, min-order rules
├── Offers
├── Banners             — schedulable, per-city (Section 32)
└── City Campaigns

Reports & Analytics       (new — not in the original structure)
├── Sales by city / warehouse / category
├── Delivery SLA performance (promised vs. actual, from Section 34's ETA)
├── COD vs. prepaid mix
└── Inventory turnover / dead stock

Settings
├── Payment              — gateway config (Section 12)
├── COD                  — global limits, charges (Section 12)
├── Delivery              — global defaults, SLA
├── Order
├── Notifications         — SMS/email/push templates (Section 26)
└── Users & Roles          — manage the RBAC roles from Section 31.2
```

### 31.4 Warehouse Operations View (separate from the back-office admin)

Pickers and packers should **not** get the full Filament admin panel — it's built for desk-based staff managing catalog/pricing/settings, not for someone moving through a warehouse aisle. Give warehouse floor staff a narrower, purpose-built view instead:

```text
Requirements, distinct from Section 31.3's admin panel:
- Large tap targets, minimal text — used on a shared tablet or a rugged
  handheld scanner, often with gloves on.
- Shows only: orders assigned to this warehouse, grouped by status
  (New → Picking → Packing → Ready), matching the dashboard in Section 22.
- Barcode/SKU scan-to-pick flow (Section 6's SKU field), not manual search.
- No access to pricing, customer PII beyond name/delivery area, or
  cross-warehouse data.
```

This can be a simple Filament panel with a restricted resource set for Phase 1, or a lightweight dedicated view later if a barcode scanner integration needs native camera access — in which case it becomes a natural candidate for Section 40's Option A (a Capacitor wrapper), since it's then effectively a small, separate mobile app.

### 31.5 Notifications Inside the Admin Panel

- Real-time badge/toast when a new order arrives for a warehouse manager's warehouse (via Reverb, Section 28) — order intake should never depend on someone manually refreshing the Orders table.
- Low-stock and failed-payment alerts surfaced on the Dashboard (31.3), not buried in a report only checked periodically.

### 31.6 Admin API & Security Constraints

- The admin panel authenticates through a separate guard from the customer API (Section 29) — an admin session token must never be valid against customer-facing endpoints and vice versa.
- Rate-limit and log every admin login attempt; lock the account after repeated failures rather than only relying on the password.
- If Filament is exposed on a subdomain (e.g. `admin.example.com`), restrict it further with IP allowlisting or a VPN requirement once the team and warehouse count grow beyond a handful of trusted staff — this is a config decision, not a code change, so it can be deferred past Phase 1.

### 31.7 Audit Log

Every mutation listed with a ⚠ requirement above (stock adjustment reasons, manual order status overrides, refund approvals, role changes) must be written to an append-only audit log:

```text
id, user_id, action, subject_type, subject_id, before, after, created_at
```

This is a small addition in Phase 1 (e.g. `spatie/laravel-activitylog`, which integrates directly with Filament) and is expensive to retrofit later once "who changed this and when" becomes a support or dispute question.

---

## 32. City-Specific Homepage

Each city can have different content.

Example:

```text
Delivered in 45 minutes

Popular in Biratnagar

Today's Deals

Groceries

Dairy

Snacks

Drinks

Household
```

The following can be city-specific:

- Banners
- Featured products
- Coupons
- Offers
- Campaigns
- Delivery promises
- Minimum order
- Delivery fees

---

## 33. Recommended Customer UI

Header:

```text
┌────────────────────────────────────────────────────┐
│ LOGO   Delivering to Biratnagar ▼      Search  Cart│
└────────────────────────────────────────────────────┘
```

Product card:

```text
┌───────────────────┐
│      IMAGE        │
│                   │
│ Product Name      │
│ Rs. 850           │
│                   │
│ 30-45 min         │
│                   │
│     [+ ADD]       │
└───────────────────┘
```

Important information on a product card:

- Availability
- Delivery time
- Price
- Add to cart button

---

## 34. Delivery Time Logic

Do not promise delivery speed based only on city.

Recommended ETA factors:

```text
Warehouse
+
Stock Availability
+
Delivery Zone
+
Operating Hours
+
Order Volume
+
Cutoff Time
```

Future ETA formula:

```text
ETA =
Picking Time
+ Packing Time
+ Rider Assignment Time
+ Travel Time
```

This allows realistic messages such as:

```text
Delivery in 35–50 minutes
```

---

## 35. MVP Recommendation

### Phase 1

Build:

```text
City Selection
City-Based Catalog
Products and Variants
Warehouse Inventory
Cart
Address
COD
Online Payment
Orders
Basic Warehouse Processing
Delivery Status
Admin Configuration
Coupons
Notifications
```

### Phase 2

Add:

```text
Rider Management
GPS Tracking
Delivery Zones
Live ETA
Automatic Rider Assignment
Multiple Warehouses per City
Stock Transfer
Returns and Refunds
Customer Wallet
Loyalty Points
Push Notifications
```

### Phase 3

Add:

```text
Route Optimization
Demand Forecasting
Dynamic ETA
Automated Procurement
Multi-Vendor Support
Dark Stores
Fraud Detection
Personalized Catalog
```

---

## 36. Recommended Architecture

```text
                  ┌─────────────────────────┐
                  │   Web Storefront         │
                  │  Blade + Tailwind CSS     │
                  │ (plain server-rendered,   │
                  │  no JS framework)         │
                  └──────────┬───────────────┘
                             │
                    Direct method calls
                    (no HTTP hop — Controllers
                     call Services in-process,
                     Section 25)
                             │
                  ┌──────────▼──────────┐
                  │       Laravel       │
                  │                     │
                  │ Auth                │
                  │ Catalog             │
                  │ Cart                │
                  │ Checkout            │
                  │ Inventory           │
                  │ Orders              │
                  │ Payments            │
                  │ Delivery            │
                  └───────┬───────┬─────┘
                          │       │
                    ┌─────▼──┐ ┌──▼─────┐
                    │ MySQL  │ │ Redis  │
                    └────────┘ └──┬─────┘
                                  │
                              Queue Jobs
                                  │
                ┌─────────────────┼────────────────┐
                ↓                 ↓                ↓
             Payment          Notification      Delivery
             Gateway             Service        Service

                             ▲
                             │  REST API (Section 29) — separate
                             │  entry point, used only by:
                  ┌──────────┴──────────┐
                  │   Mobile App         │
                  │ (Section 40)         │
                  └──────────────────────┘
```

For the first release, use a **modular monolith** rather than microservices.

This is easier to build, maintain, and deploy while still providing clean domain separation.

Dropping the Angular SPA changes this diagram in one important way: the **web storefront no longer talks to Laravel over HTTP at all**. A standard Laravel controller runs inside the same PHP request as everything else — it calls `CityCatalogService`, `CartService`, etc. (Section 25) directly, the way a controller normally would. The REST API in Section 29 still exists, but it now has exactly one consumer — the mobile app — instead of being the only way in for both web and mobile. This also removes the separate Node.js SSR runtime entirely (no Angular Universal, no SSR resource/scaling question) since Blade is server-rendered by PHP itself, with no additional process to run or scale, and there is no client-side JS framework runtime to hydrate either.

---

## 37. Core Business Model

The core system should follow:

```text
Customer
   ↓
SELECT CITY
   ↓
City
   ↓
Service Area
   ↓
Fulfillment Warehouse
   ↓
Available Inventory
   ↓
Available Products
   ↓
Cart
   ↓
Stock + Price + Delivery Revalidation
   ↓
Checkout
   ↓
COD / Prepaid
   ↓
Inventory Reservation
   ↓
Order
   ↓
Warehouse Fulfillment
   ↓
Delivery
```

This structure provides a strong foundation for a fast-delivery e-commerce system combining traditional e-commerce shopping with local quick-commerce fulfillment.

---

## 38. Design Constraints

These are binding constraints, not suggestions. Every module in Sections 3–37 must be validated against them before it is considered done.

### 38.1 Performance Constraints

```text
Constraint                              Target
─────────────────────────────────────────────────────
Product listing API (cached)            < 150 ms p95
Product listing API (cache miss)        < 500 ms p95
Checkout validation endpoint            < 400 ms p95
Payment gateway callback processing     < 2 s
Add-to-cart response                    < 200 ms p95
Search response                         < 300 ms p95
Concurrent orders per warehouse         100+ /minute at peak
```

- No checkout-path request may perform a synchronous external HTTP call (SMS, email, push) — these must be dispatched to a queue (see Section 26).
- No checkout-path request may perform an uncached full-table scan; every filtered query (city, warehouse, category) needs a covering index.
- Pages/endpoints on the customer-facing catalog must degrade gracefully to stale cache data rather than fail if Redis is briefly unavailable.

### 38.2 Data Consistency Constraints

- Inventory `quantity` and `reserved_quantity` must only be mutated inside a `DB::transaction()` with `lockForUpdate()` (Section 21). No inventory write is allowed outside this pattern, including admin manual stock edits.
- An order's `product_name`, `sku`, `unit_price` must be **immutable snapshots** at the time of order creation (Section 16). They must never be recalculated from live catalog data after the order is placed.
- A cart must be revalidated against city, stock, and price at three points, non-negotiably: entering checkout, changing city, and immediately before payment/order creation. A cart is never trusted from a prior page load.
- Payment status must only ever be written by server-to-server gateway verification (Section 18) — never by a client redirect/return URL alone.

### 38.3 Scalability & Multi-Tenancy Constraints

- The schema must support N cities and M warehouses without any code change — city and warehouse counts are runtime data, not configuration constants.
- A single warehouse must be assignable to multiple cities and a single city must be assignable to multiple warehouses (Section 5.3) from day one, even if Phase 1 UI only exposes a 1:1 mapping.
- All city-specific and warehouse-specific business rules (pricing, COD, delivery fee, SLA) must resolve through a single rule-resolution service (Section 11), never through scattered `if ($city_id == X)` conditionals in controllers or views.

### 38.4 Security & Compliance Constraints

- All monetary calculations (subtotal, discount, delivery fee, tax, total) must be recalculated and verified server-side at order creation; client-submitted totals are never trusted.
- COD orders above the configured `Maximum COD Amount` (Section 12) must be blocked at the API layer, not only hidden in the UI.
- Customer PII (phone, address, geolocation) must be encrypted at rest at the column level for `addresses.phone`, `addresses.latitude/longitude`.
- Rate limiting is mandatory on: OTP request endpoints, login, checkout submission, and coupon application (Section 27).

### 38.5 UI/UX Design Constraints (extends Section 33)

- **City lock:** a city must be selected before any product price or availability is rendered; there is no "default city" fallback that silently guesses location.
- **Persistent context:** the selected city is always visible in the header (Section 33) on every screen, including checkout — never only on the homepage.
- **No silent cart mutation:** if a city change removes items from the cart, the UI must show which items and why (Section 14) — never a silent count change.
- **Delivery-time-first cards:** every product card must show estimated delivery time with equal visual weight to price (Section 33) — delivery speed is the product's core value proposition, not a footnote.
- **Mobile-first:** design and build for a single-column, thumb-reachable mobile layout first; desktop is a progressive enhancement, not the primary target, since fast-delivery shopping is predominantly a mobile behavior.
- **Perceived speed:** use optimistic UI updates for add-to-cart and quantity changes (update the UI immediately, reconcile with the server response silently) so the interface *feels* as fast as the delivery promise.
- **Accessibility floor:** WCAG 2.1 AA as a minimum — sufficient color contrast for price/strikethrough-price pairs, tap targets ≥ 44px, and non-color-only indicators for "unavailable in your city."

### 38.6 Operational Constraints

- Every warehouse status change (Section 22) must be visible on the warehouse dashboard within 2 seconds of the event, via Reverb (Section 28) — polling is not an acceptable substitute for Phase 1.
- Inventory reservation locks must auto-expire (Section 19) — there is no unbounded reservation state in the system.
- All queued jobs (Section 26) must be idempotent, since Redis-backed queues can redeliver a job more than once.

---

## 39. Recommended UI Approach for Fast Bootstrap (Plain Blade + Tailwind — no JS framework)

The storefront is plain Laravel Blade views with standard controllers, standard routes, and standard HTML forms — no Livewire, no Alpine, no client-side reactivity framework at all. Interactivity comes from regular page navigation plus a small number of hand-written, targeted vanilla-JS scripts for the handful of things that genuinely need it (a mobile nav toggle, a live cart-count update). This is the simplest possible stack, and it removes an entire category of framework-version and reactivity-model decisions the earlier TALL-stack version of this document carried.

### 39.1 Comparison

| Option | What it gives you | Fit for this storefront |
|---|---|---|
| **Tailwind CSS + hand-built Blade components, no kit** | Full control, zero dependencies beyond Tailwind itself, nothing to fight when customizing (Section 38.5's "not templated" bar). | The most literal fit for "just Blade." Slowest to bootstrap since every card/drawer/badge is built from utility classes by hand, but there is nothing to learn beyond Tailwind and Blade components, which most Laravel developers already know. |
| **[DaisyUI](https://daisyui.com/)** (Tailwind plugin, pure CSS, no JS) | Semantic classes (`btn`, `card`, `badge`, `drawer`) on top of Tailwind utilities — genuinely zero JavaScript, since it's a CSS-only plugin. | **Best default choice for this stack.** It's the only option in this table that adds real speed without adding any JS runtime at all, which matches "just Blade" most directly. Re-theme its default palette immediately (39.2) so it doesn't read as a stock template. |
| **[Flowbite](https://flowbite.com/) or [Preline UI](https://preline.co/)** (Tailwind components + their own small vanilla-JS bundle) | Pre-built Blade-compatible markup for things that genuinely need JS behavior (modals, dropdowns, carousels, off-canvas drawers) — their JS is plain vanilla, not tied to Alpine or any component framework. | Use these **only** for the specific components that need real interactivity beyond what native HTML (39.2) can do — not as a base layer for the whole UI, to keep the JS footprint as small as the "just Blade" goal implies. |

### 39.2 Recommendation

**Tailwind + DaisyUI** for styling, **native HTML elements** for the interactivity that doesn't strictly need JavaScript, and a small number of **hand-written vanilla-JS files** (no framework, no build-time component compiler) for the few things that do:

```text
1. npm install -D tailwindcss daisyui
   → gives you: utility classes + a base set of styled components,
     zero additional JS runtime.

2. Prefer native HTML over JavaScript wherever the platform already
   does the job (Section 33 + 38.5):
   - Dropdowns/accordions → <details>/<summary> — no JS needed at all.
   - Modals (city switcher, cart preview) → the native <dialog> element
     (`showModal()` is one line of vanilla JS to open it; closing and
     backdrop click are native browser behavior).
   - Forms (login, address, checkout steps) → standard HTML <form>
     POSTs to Laravel controllers, with validation errors and old()
     input redisplayed server-side exactly as vanilla Laravel does it —
     no client-side form-state library needed.

3. Build the few things native HTML genuinely can't do as small,
   dedicated vanilla-JS files (no framework), loaded only on the pages
   that need them:
   - CitySwitcher      → <dialog> for the picker UI, a ~15-line JS file
                          that POSTs the selection to a CityController
                          endpoint and reloads (or updates the page via
                          a small fetch() call, Section 39.3)
   - ProductCard        → pure Blade component, no JS — the delivery-
                          time badge (Section 38.5) is just a styled
                          <span>, server-rendered with the right value
   - ProductGrid        → server-side filtering: filter controls are a
                          <form> that submits query parameters and
                          reloads the page with filtered results — the
                          simplest possible implementation, at the cost
                          of a full page reload per filter change
   - CartDrawer         → <dialog> triggered by the header cart icon,
                          contents rendered server-side on page load;
                          the item count badge updates via a small
                          fetch()-based script (39.3) after add-to-cart,
                          without needing a full page reload for that
                          one number
   - CheckoutStepper    → a traditional multi-step server-side wizard:
                          each step is its own route/controller/view,
                          current step stored in the session, "Next"
                          is a normal form POST that validates
                          (Section 30) and redirects to the next step
                          — exactly how checkout wizards worked before
                          JS frameworks existed, and still work fine
   - Toasts/errors      → flash messages (Laravel's built-in session
                          flash data) rendered server-side after a
                          redirect (e.g. "cart changed," Section 14) —
                          no client-side toast library needed

4. Re-theme DaisyUI's default palette/typography immediately with the
   tokens from Section 41 — same reasoning as before: a stock
   component-kit look doesn't meet Section 38.5's "not templated" bar.
```

### 39.3 Where a Little JavaScript Is Still the Right Call

Going fully framework-free doesn't mean zero JavaScript anywhere — a handful of specific interactions genuinely need it, and the honest approach is a small, dedicated script per concern rather than reaching for a framework to solve all of them uniformly:

```text
- Cart badge count after add-to-cart: a single fetch() POST to
  CartController, updating one DOM element's text content on success
  — a few lines of vanilla JS, not a reason to add a framework.
- Search autocomplete (Section 42): a debounced fetch() call to a
  lightweight search endpoint, rendering suggestions into a plain
  <ul> — again a small dedicated script, not a reactive framework.
- Mobile nav toggle: a one-line classList.toggle() on a hamburger
  button click.
```

Each of these is a self-contained file with a clear, narrow job — the discipline to keep is not adding a shared state/reactivity layer that starts creeping toward reinventing Livewire in raw JS. If more than a handful of these small scripts start needing to coordinate with each other, that's the signal to revisit this decision, not to keep stacking vanilla JS files.

### 39.4 The Honest Trade-off

- **Page transitions are ordinary full page loads.** Without Livewire's `wire:navigate`, there's no SPA-like transition by default. The modern, zero-dependency way to soften this is the browser-native **View Transitions API** (`document.startViewTransition`, supported in Chromium-based browsers) applied to standard MPA navigation — a few lines of CSS/JS, not a framework, and it degrades gracefully (a plain page load) in browsers that don't support it yet.
- **Every interaction that changes server state is a full round trip by default** (add to cart, apply coupon, change quantity), reloading the page unless specifically implemented otherwise via the small `fetch()` scripts in 39.3. This is slower to feel "instant" than Livewire's optimistic updates (Section 38.5's original ask), but it is also the simplest possible mental model to build and debug, with the fewest moving parts of any option this document has considered.
- This also **removes the Angular SSR resource question entirely, and removes the Livewire/Alpine dependency surface too**: Blade is rendered by PHP-FPM in the same process that already serves the rest of the app — there is no separate Node.js runtime, no Livewire component lifecycle, and no Alpine directive syntax to learn, version, or debug.

---

## 40. Mobile Strategy: Flutter Wrapper + Native Bridge

The mobile app is a Flutter app, not a Capacitor wrapper. Flutter provides both the WebView-based wrapper around the Blade storefront and the native bridge layer (push notifications, local storage) as first-class Dart/native code, rather than a JS-to-native bridge layered on top of a web runtime.

### 40.1 Option A — Flutter WebView wrapper around the live site (fastest)

A Flutter app whose primary screen is a WebView (`webview_flutter` or `flutter_inappwebview`) pointed at the **live Blade site's URL**, not a bundled local build. This is the direct Flutter equivalent of "the website in an app icon":

```dart
// Simplified shape
WebViewWidget(
  controller: WebViewController()
    ..loadRequest(Uri.parse('https://yourapp.example.com'))
    ..addJavaScriptChannel('NativeBridge', onMessageReceived: (msg) {
      // handle messages sent up from the page — Section 40.4
    }),
)
```

- The app is effectively "your website in an app icon," using the exact same Blade + Tailwind UI already built for Section 39 — zero duplicate UI work.
- The browser-native View Transitions API (Section 39.4) already softens page-to-page navigation inside the WebView, which is most of what this approach is trying to buy you.
- **Trade-off:** the app requires a live network connection at all times — there is no offline shell, since there's no local bundle to fall back to. For a fast-delivery app where the customer is choosing a city and browsing live stock and pricing anyway, this is a reasonable trade-off — the experience is already fundamentally online-only (Sections 3–4).
- Native capabilities (push notifications, geolocation, local storage) are implemented in Dart/native code and exposed to the WebView through a JavaScript channel (Section 40.4), rather than through a pre-built plugin bridge — this is more setup than a framework with ready-made web↔native plugins, but keeps everything in one Flutter codebase and one set of native dependencies.
- Real-time order updates (Section 28) still need a native push fallback for backgrounded apps — Reverb's WebSocket only stays open while the WebView is foregrounded, exactly as noted for any wrapped-WebView approach regardless of the wrapping technology.

### 40.2 Option B — A fully native Flutter app consuming the REST API

Build the mobile UI as genuine Flutter widgets (no WebView at all) talking directly to the REST API in Section 29, the same API the web storefront no longer uses (Section 36).

- More upfront work — this is a second UI entirely, with its own screens, its own release cadence, and no visual code sharing with the Blade storefront (though the Dart bridge code from Section 40.4 carries over directly, since it doesn't depend on the WebView).
- Justified once the mobile app needs to feel fully native (smooth gesture-driven navigation, offline browsing of a cached catalog, deep OS integration) rather than "the website in an app shell."
- The REST API (Section 29) and its checkout-validation endpoint (Section 30) were already designed API-first, so this path doesn't require new backend work — only new Flutter screens.

### 40.3 Recommendation

Start with **Option A (Flutter WebView wrapper)** for Phase 1 (Section 35) — it gets a listable app-store presence with genuinely zero duplicate UI work, which matters more early on than a fully native feel. Revisit **Option B** in Phase 2/3 once order volume and user feedback justify the investment in fully native screens — at that point the REST API this document already specifies is ready to support it without backend changes, and Option A's native bridge (push, SQLite) migrates over largely unchanged since it was never tied to the WebView in the first place.

### 40.4 Native Bridge Layer: Push Notifications + Local SQLite

The Flutter app wraps the Blade site (Option A) but still owns real native capabilities directly, communicating with the WebView through a JavaScript channel rather than loading a separate bridge script only in "app mode" — since the entire app *is* the native shell here, not a feature-detected variant of the website.

#### 40.4.1 Native-handled push notifications

- Flutter's own push integration (`firebase_messaging` for FCM on Android + APNs on iOS via the same package) registers the device and owns receiving/showing the system notification — this closes the same gap noted earlier: Reverb's WebSocket (Section 28) only stays open while the WebView is foregrounded, but native push wakes the app or shows a notification even when it's suspended.
- On tap, the native Dart code calls into the WebView (`controller.loadRequest(Uri.parse(...))`) to navigate straight to that specific order's tracking page URL — so tapping "your order is out for delivery" lands the user exactly there, not just wherever the WebView last was. Since the storefront is plain server-rendered Blade (Section 39) with no client-side router to address, this is a normal URL navigation, not a framework-specific event.
- Because this is a Flutter-native capability rather than a JS bridge loaded conditionally into shared web code, there's no feature-detection needed on the Blade side at all — the ordinary website served in a browser simply never receives these events, since only the Flutter app's WebView has the native side wired up to send them.

#### 40.4.2 Local SQLite for on-device storage

- Use `sqflite` (Flutter's standard SQLite plugin) for structured, on-device storage owned entirely by the Flutter app — a real native database file, not browser storage, so it's less likely to be evicted under OS storage pressure and supports proper structured queries.
- What belongs there: cart draft state, the last-loaded city + catalog snapshot (the same offline goal as Section 40.1's connectivity note), recently viewed products, an in-progress delivery address — data worth surviving an app restart.
- What must never go there: payment credentials, card numbers, or anything that should only ever live server-side or inside the payment gateway's own SDK — Section 38.4's PII/security rules apply identically on-device, not just on the server.
- Encrypt the local database (`sqflite_sqlcipher` or platform keystore-backed encryption) since it may hold delivery addresses and draft orders — this is the on-device equivalent of Section 38.4's "encrypted at rest" requirement, just applied to the device instead of the MySQL column.
- Expose the data the WebView needs (e.g. "do we have a cached catalog for this city?") back to the page through the same JavaScript channel used for push events, so the small vanilla-JS scripts already in the Blade views (Section 39.3) can read from native SQLite without needing their own separate offline-storage logic duplicated in JS.

#### 40.4.3 This is a client-side cache, not a second source of truth

To be explicit about scope: local SQLite here sits **alongside** the app's storage layer, not in place of the server database. The Laravel + MySQL/MariaDB (InnoDB) stack from Section 24 remains the only authoritative store for inventory, pricing, and orders — nothing about this bridge changes that.

- Any cart or draft data written to local SQLite must be revalidated against the live server through the same checkout-validation endpoint (Section 30) before it's ever submitted — it is never trusted as-is, exactly like Section 38.2's existing rule that "a cart is never trusted from a prior page load" now also covers "a cart is never trusted from local SQLite," for the identical reason: stock and pricing may have moved while the device was offline.
- If the local snapshot is stale when connectivity returns, re-run full cart validation immediately and surface the same "your cart changed" messaging from Section 14 if anything in it is no longer available — the local database exists to make the *offline experience* better, not to let anything skip the revalidation this document has required everywhere else.

---

## 41. Visual Design System (Mobile + Desktop)

This section defines the concrete visual system referenced loosely in Section 33 and constrained in Section 38.5, synthesized from two reference directions: a warm, editorial, card-driven **mobile** direction (ASOS-style fashion app) and a dense, utilitarian, location-aware **desktop** direction (a general marketplace like emox). The mobile app doesn't try to look like a dense desktop marketplace, and the desktop site doesn't try to look like a sparse editorial app — each platform gets the density that suits it, unified by one color and type system underneath.

### 41.1 Brand Personality

Fast, clean, trustworthy — premium enough to feel considered, dense enough on desktop to feel like a real marketplace with real selection. The mobile app leads with delivery speed and product photography; the desktop site leads with breadth (categories, brand stores, deals) the way a browsing-on-a-big-screen customer expects.

### 41.2 Color Palette

```text
Token                  Hex        Usage
─────────────────────────────────────────────────────────────
--color-bg-warm         #F5EDE4    Mobile hero/editorial sections only
--color-bg-surface      #FFFFFF    Cards, product tiles, content surfaces
--color-bg-muted        #F7F8FA    Desktop page background (denser, cooler than mobile)
--color-ink             #111111    Primary text, primary buttons (solid dark CTAs)
--color-ink-muted       #6B7280    Secondary text, meta info (ratings, review counts)
--color-accent          #C97B5E    Terracotta accent — promo highlights, editorial tags
--color-brand-success   #1E8E5A    In-stock / delivered / "fast" badges
--color-brand-warning   #D97706    Low-stock badges
--color-brand-error     #DC2626    Out-of-stock, errors, destructive actions
--color-border           #E5E7EB    Desktop card borders (flat style, Section 41.4)
```

- The warm beige (`--color-bg-warm`) is used sparingly on mobile — hero banners and promotional sections only, per the reference — not as the default page background, so it stays a highlight rather than fatiguing the eye across every screen.
- `--color-brand-success` doubles as the delivery-time badge color (Section 38.5's "delivery time gets equal visual weight to price") — a green pill reads as "fast/available" at a glance, consistent with how it's already used for stock status.

### 41.3 Typography

```text
Font stack: Inter, -apple-system, "Segoe UI", Roboto, sans-serif

Role                   Mobile         Desktop        Weight
──────────────────────────────────────────────────────────────
Wordmark/logo          20px           18px           700, lowercase, tight tracking
Hero headline          28–32px        20–22px        700
Section heading        18–20px        16px           700
Product name           14–15px        13px           500
Price                  16–18px        14–15px        700
Meta (ratings/reviews) 12px           11px           400, --color-ink-muted
Badge/tag text          11px          10px           600, uppercase, tight tracking
```

Desktop type runs noticeably smaller than mobile at every level — this is deliberate, matching the denser marketplace reference, where more products need to fit above the fold rather than one or two per screen.

### 41.4 Layout & Density

```text
                     Mobile                    Desktop
─────────────────────────────────────────────────────────────────
Grid                 Single column             12-column, max-width 1280px
Page margin          16px                      24px (with centered max-width)
Card gutter          12px                      16px
Card corner radius   16–20px (soft, editorial) 8–10px (flat, utilitarian)
Card elevation       Soft shadow               1px border, no shadow
                     (0 4px 12px rgba(0,0,0,.06))  (--color-border)
Product grid         2 columns                 5–6 columns
Navigation           Floating bottom pill nav  Top nav + category sub-bar
```

The corner-radius and elevation difference is the single biggest lever for matching each reference's feel: soft rounded shadowed cards read as "app," flat bordered cards read as "marketplace" — using the wrong one on the wrong platform is the fastest way to make either surface feel off-brand.

### 41.5 Key Components

```text
City / Location Selector  — a persistent pill in the header on both platforms
                             (mobile: below the logo; desktop: top-right next
                             to search, mirroring the "AE ▾ Update Location"
                             pattern from the reference) — this is not a nice-
                             to-have here, it's Section 38.5's city-lock
                             requirement given visual form.

Product Card (mobile)     — 4:5 image, badge top-left (delivery time takes
                             priority over "New in"/"Best Seller" per Section
                             38.5), heart icon top-right in a white circle,
                             name + price below, soft shadow, 16–20px radius.

Product Card (desktop)    — smaller 1:1 or 4:3 image, star rating + review
                             count row (small, muted), price with currency
                             code, small wishlist icon overlay, flat 1px
                             border, 8–10px radius, 5–6 per row.

Category chips             — horizontally scrollable circular icon chips on
                             mobile; a denser icon-plus-label row on desktop
                             (mirrors both references' "Explore Popular
                             Categories" pattern).

Primary CTA button          — solid --color-ink, white text, pill-shaped on
                             mobile ("Shop Now", "Add to Bag"), slightly less
                             rounded on desktop ("Shop Now" banner buttons).

Bottom nav (mobile only)   — floating dark pill bar, 4–5 icons (Home,
                             Wishlist, Cart with badge count, Account) —
                             no desktop equivalent; desktop uses the top nav
                             instead, so this component never renders above
                             the tablet breakpoint (Section 41.6).

Promo banner               — full-bleed rounded tile on mobile (carousel,
                             dot indicators); a denser grid of smaller promo
                             tiles on desktop (2–3 across, matching the
                             reference's "Sale up to 50% Off" / Ramadan-style
                             banner tiles) rather than one large carousel.
```

### 41.6 Responsive Breakpoints

```text
< 768px    Mobile   — single column, floating bottom nav, soft/shadowed cards
768–1024px Tablet   — 2–3 column grid, top nav appears, bottom nav still shown
> 1024px   Desktop  — full top nav + category sub-bar, dense grid, flat
                       bordered cards, no bottom nav
```

### 41.7 Implementation Notes (ties to Section 39)

- Encode the tokens in 41.2–41.3 directly into `tailwind.config.js` (`theme.extend.colors`, `theme.extend.borderRadius`) so every Blade component pulls from the same palette rather than hardcoded hex values scattered across views.
- This is the concrete work behind Section 39.2's instruction to "re-theme DaisyUI's default palette immediately" — without it, the storefront ships looking like a generic component-library demo instead of the system defined here.
- Because corner radius and elevation differ meaningfully by breakpoint (41.4), define them as responsive Tailwind utility variants (e.g. `rounded-2xl md:rounded-lg`, `shadow-md md:shadow-none md:border`) on shared components rather than building genuinely separate mobile and desktop card components — one Blade component, breakpoint-aware classes, consistent with keeping Section 36's architecture a single Blade codebase.

---

## 42. Search & Discovery

Product search is a separate concern from the catalog browsing already covered in Sections 5–8, and it needs its own plan rather than falling back on a slow `LIKE '%query%'` MySQL query once the catalog grows past a few thousand SKUs.

### 42.1 Recommended Approach

- **[Laravel Scout](https://laravel.com/docs/scout) + [Meilisearch](https://www.meilisearch.com/) or [Typesense](https://typesense.org/)** — both are open-source, self-hostable, fast (sub-50ms typo-tolerant search), and have first-class Scout drivers, so indexing is a matter of adding a `toSearchableArray()` method to the `Product` model rather than building a search pipeline from scratch.
- Index per-city availability as a searchable/filterable attribute (`available_city_ids`), not just product name/description — a search for "milk" in a city with no dairy warehouse coverage should filter it out at the search layer, not return it and then fail at cart-add time.
- Re-index on the same events that already invalidate the pricing/inventory cache (Section 27) — a stock-out or price change should be reflected in search results within the same event-driven flow, not on a separate cron-based re-index.

### 42.2 What the Search Experience Needs

```text
Autocomplete       — as-you-type suggestions (product names, categories,
                      brands), debounced client-side via the small vanilla-
                      JS search script (Section 39.3) calling a lightweight
                      search endpoint — not a full page reload per keystroke.
Typo tolerance      — "mlik" should still surface "milk" — this is Meilisearch/
                      Typesense's default behavior, not something to build.
Filters              — category, brand, price range, in-stock-in-my-city —
                      matching the filter chips already in Section 33's UI.
Zero-results state   — never a blank page; suggest categories or popular
                      searches instead, per Section 38.5's general "never
                      leave the customer at a dead end" spirit.
Search analytics     — log zero-result queries (Section 44) — this is the
                      cheapest source of "what are customers looking for
                      that we don't stock" data the business will get.
```

---

## 43. SEO Strategy

Switching to Blade (Section 36) was already a major SEO unlock over the earlier Angular SPA plan — pages are server-rendered HTML by default, no client-side rendering gap for crawlers to work around. This section defines what to actually do with that advantage.

### 43.1 Per-Page Requirements

- **Meta tags**: unique `<title>` and `<meta name="description">` per product and category page, generated from product/category data, not a single static template repeated everywhere.
- **Structured data (schema.org)**: emit `Product`, `Offer` (with `price`, `priceCurrency`, `availability`), and `AggregateRating` JSON-LD on every product page — this is what enables rich results (price, stock, star rating) directly in search listings.
- **Canonical URLs**: since the catalog is city-specific (Section 3), decide one canonical URL policy up front — either a single canonical product URL indexed regardless of city (recommended, to avoid duplicate-content penalties across every city variant of the same product), with city-specific price/availability handled as a query parameter or session state that doesn't fragment the canonical URL.
- **Sitemap**: an XML sitemap generated from the product/category tables (a scheduled job regenerating it, not hand-maintained), submitted to Search Console — exclude cart/checkout/account routes.

### 43.2 Performance as an SEO Factor

- Core Web Vitals (LCP, CLS, INP) directly affect ranking. Section 38.1's performance targets already cover most of this; the SEO-specific addition is making sure the **largest contentful paint** on a product page is the product image, and that it's served at an appropriately sized/compressed format (WebP/AVIF with a JPEG fallback) rather than a full-resolution upload.
- Avoid layout shift from late-loading badges/price (Section 38.5's delivery-time badge) — reserve the space in the initial server-rendered HTML rather than injecting it after page load via JavaScript.

### 43.3 City Pages as an SEO Opportunity

- Section 32's city-specific homepage is also a genuine SEO asset: a dedicated, indexable page per city ("Grocery delivery in [City]") targets local search intent directly, the same way food-delivery and quick-commerce competitors typically rank for city-specific queries. Make sure each city homepage has unique content (not just the same template with a city name swapped in) — a few sentences of genuinely city-specific copy (popular categories in that city, delivery zones covered) is enough to avoid reading as thin/duplicate content to a search engine.

---

## 44. Observability & Monitoring

Section 38.1 defines performance *targets*; this section defines how you'd actually know if you're missing them in production.

### 44.1 Error Tracking

- **[Sentry](https://sentry.io/) (self-hosted or cloud)** or an equivalent — every unhandled exception, failed queue job, and failed payment callback (Section 18) should land here with enough context (user id, order id, request payload minus sensitive fields) to debug without reproducing locally.
- Failed queue jobs specifically (Section 26) need alerting, not just logging — a silently failing notification job means a customer never finds out their order shipped, which is a support ticket waiting to happen.

### 44.2 Application Performance Monitoring (APM)

- Track the actual p95/p99 latency of the endpoints Section 38.1 sets targets for (product listing, checkout validation, add-to-cart) in production, not just in local testing — tools like Laravel Telescope (dev/staging) and a production APM (e.g. New Relic, or Sentry's performance monitoring) close the loop between "we set a 150ms target" and "we know whether we're hitting it."
- Alert on the specific failure modes already called out elsewhere in this document: inventory lock contention (Section 21) spiking, Redis cache hit rate dropping (Section 27), and Reverb connection count/error rate (Section 28).

### 44.3 Business-Level Dashboards

Beyond technical monitoring, track the metrics that actually matter to the business day to day:

```text
- Orders per hour, by city and warehouse
- Cart abandonment rate, and at which checkout step (Section 13) it happens
- COD vs. prepaid split, and COD refusal rate (feeds Section 31.3's
  "COD Restrictions" flag on repeat offenders)
- Average time from order confirmation to delivery, vs. the promised
  ETA from Section 34 — this is the number the whole product is built
  around, so it needs to be visible on the admin Dashboard (Section 31.3),
  not buried in a report.
```

### 44.4 Logging

- Structured (JSON) logs for anything that touches money, stock, or order status — the same events already required to hit the audit log (Section 31.7) should also be loggable/searchable for debugging, even though the audit log and the debug log serve different audiences (compliance/support vs. engineering).
- Centralize logs (even a simple ELK/Loki stack, or a hosted log service) once running more than a single server — grepping individual server log files doesn't scale past Phase 1.

---

## 45. Testing Strategy

Given how much of this document depends on correctness under concurrency (Section 21's locking) and financial accuracy (Section 38.2's server-recalculated totals), test coverage isn't optional for the checkout-adjacent code paths, even in an MVP.

### 45.1 What Needs Real Test Coverage (not just manual QA)

```text
Priority   Area                              Why
─────────────────────────────────────────────────────────────────────
Critical   Inventory locking (Section 21)     A race condition here means
                                               overselling — real money and
                                               real customer trust lost.
Critical   Price/total calculation             A bug here is a direct
           (Section 38.2)                      financial loss or a customer
                                               dispute.
Critical   Cart revalidation (Section 14)      Getting this wrong means
                                               customers pay for items that
                                               are actually out of stock.
High       City-based catalog filtering        Wrong-city products showing
           (Section 3)                         up breaks the core premise
                                               of the whole app.
High       Checkout validation endpoint         This is the last line of
           (Section 30)                        defense before an order is
                                               created — needs explicit
                                               negative-path tests (stale
                                               price, out-of-stock item,
                                               invalid coupon), not just
                                               the happy path.
Medium     Admin RBAC (Section 31.2)           A permissions bug here is a
                                               data-leak risk across
                                               warehouses/cities.
```

### 45.2 Tooling

- **[Pest](https://pestphp.com/)** (or plain PHPUnit) for unit and feature tests — Pest's syntax is lighter-weight, which matters for actually getting tests written rather than skipped under deadline pressure.
- **Feature tests that hit real concurrency**: for Section 21's locking logic specifically, write a test that fires concurrent requests at the same inventory row and asserts the reservation count never exceeds available stock — a single-threaded happy-path test won't catch a race condition that only shows up under real concurrent load.
- **Browser/E2E tests** (Laravel Dusk, or Pest's browser testing) for the full checkout flow end-to-end, run against a seeded multi-city, multi-warehouse dataset — this is the closest thing to a regression safety net for the city/warehouse logic that's genuinely custom to this project (Section 39's rewritten section notes there's no off-the-shelf package covering this).

### 45.3 CI Gate

- Run the test suite on every pull request, not just before a release — this is what actually catches a regression before it ships, and is a prerequisite for the CI/CD pipeline in Section 46.
- Treat a failing test on the "Critical" rows in 45.1 as a hard merge blocker, not a warning.

---

## 46. Deployment & CI/CD Pipeline

### 46.1 Pipeline Stages

```text
1. Push / PR opened
2. CI: composer install, npm install, run test suite (Section 45),
   run static analysis (Larastan/PHPStan), run Pint (code style)
3. On merge to main: build assets (Vite, Section 39), run database
   migrations against staging, deploy to staging
4. Manual promotion (or automatic after a smoke test) from staging
   to production
5. Post-deploy: run a smoke test hitting the checkout validation
   endpoint (Section 30) and a health-check endpoint, roll back
   automatically if either fails
```

### 46.2 Zero-Downtime Deploys

- Use Laravel's `php artisan down --render` (or a proper zero-downtime deploy tool like Envoyer, Deployer, or a blue-green setup) rather than a bare `git pull` on a live server — an in-progress checkout should never hit a 502 because a deploy happened mid-request.
- Run queue worker restarts (`php artisan queue:restart`) as an explicit deploy step so in-flight jobs finish on the old code before workers pick up the new code — otherwise a job can start on old code and finish assumptions against a schema the migration just changed.

### 46.3 Environment Parity

- Keep staging's city/warehouse/inventory seed data structurally representative of production (multiple cities, multiple warehouses, overlapping warehouse-city coverage per Section 5.3) — a staging environment with only one city can hide bugs in exactly the multi-city logic that's the whole point of this project.
- Secrets (payment gateway keys, DB credentials) via environment variables injected at deploy time, never committed — this is standard practice but worth stating explicitly given how many integration points (Section 18's payment gateway, Section 26's SMS/push providers) need credentials.

---

## 47. Returns & Refunds Workflow

Section 17 already includes a `Returned` order status and Section 31.3 lists a Refunds queue, but the actual workflow between those two points hasn't been specified yet.

### 47.1 Flow

```text
Customer requests return
        │
        ▼
Support (Section 31.2's Support role) reviews request
  — reason code required (wrong item, damaged, changed mind,
    quality issue) — this feeds the reporting in Section 44.3
        │
        ▼
Return approved? ──No──▶ Rejection reason sent to customer,
        │                 order stays in its current status
       Yes
        │
        ▼
Pickup/drop-off scheduled (reverse logistics — Phase 2/3,
  Section 35, since Phase 1 may require in-person drop-off
  at a warehouse instead of a rider pickup)
        │
        ▼
Item received & inspected at warehouse
        │
        ▼
Inventory restored? ──Yes──▶ Section 19's reservation/stock logic
        │                     runs in reverse — quantity added back
       No (damaged/unsellable)
        │
        ▼
Written off (never silently added back to sellable stock —
  this needs its own stock-adjustment reason code, Section 31.3)
        │
        ▼
Refund issued via original payment method (Section 18) for
  prepaid orders, or marked for manual reconciliation for COD
  orders where money was collected in cash
```

### 47.2 Constraints

- A refund must always be tied to the original order's payment transaction (Section 16's immutable order snapshot) — never processed as a fresh, disconnected transaction, both for audit purposes (Section 31.7) and because most gateways require refunding against the original transaction ID anyway.
- Define a return window (e.g. "within 24 hours for perishables, 7 days for non-perishables") as a per-category setting, not a single global rule — a quick-delivery grocery app has fundamentally different return norms for fresh produce versus electronics or household goods, if the catalog spans both (Section 37).
- COD refunds are operationally different from prepaid refunds — there's no gateway to call, so this needs a manual reconciliation step (Section 31.3's Finance role) rather than an automated flow, at least in Phase 1.

---

## 48. Legal & Compliance

### 48.1 Required Policies

- **Privacy Policy** — must disclose what's collected (Section 38.4's PII fields: phone, address, geolocation) and how it's used, especially given location data is core to the app's function.
- **Terms of Service** — including COD terms (Section 12's max COD amount, refusal consequences), return/refund policy (Section 47), and delivery SLA disclaimers (Section 34's ETA is an estimate, not a guarantee, unless the business explicitly wants to commit to a guarantee with compensation, which is a business decision, not a technical one).
- **Cookie/tracking consent** — if analytics (Section 44.3) or marketing pixels are added, a consent banner may be legally required depending on the target market's regulations (e.g. GDPR-style requirements apply broadly even outside the EU for many businesses with EU customers).

### 48.2 Data Retention

- Define how long order history, cancelled-cart data, and inactive-customer PII are retained — "keep everything forever" is the easiest engineering default but is often not compliant with data-protection regulations, and it also means Section 38.4's encrypted-PII surface area only grows over time. A concrete retention/deletion policy (e.g. anonymize PII on accounts inactive for N years) is worth deciding early, since retrofitting deletion logic onto years of accumulated data is much harder than building it in from the start.
- Support a "delete my account/data" request path — increasingly a baseline legal expectation, not just a nice-to-have, regardless of which specific regulation applies in the target market.

### 48.3 Payment Compliance

- If handling card data directly at any point (even transiently), PCI-DSS scope applies — the strong recommendation is to never let raw card numbers touch your own servers at all, using the payment gateway's hosted fields/SDK (Section 18) so PCI scope stays minimal (SAQ A, the lightest tier) rather than something the team has to actively maintain compliance for.
