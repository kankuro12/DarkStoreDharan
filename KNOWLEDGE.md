# DarkStore Dharan — Project Knowledge

Quick-reference for working on this repo. Full business spec: [work.md](work.md). Production deploy: [deploy/README.md](deploy/README.md), [deploy/REPORT.md](deploy/REPORT.md). Agent rules: [AGENTS.md](AGENTS.md).

## What it is
Quick-commerce ("dark store") grocery app for Nepal. Customer picks a **city** → sees only products stocked/priced for that city → cart → checkout (COD or eSewa/Khalti/Fonepay) → order fulfilled from a city warehouse → rider delivers. Live at https://darkstorenepal.com.

## Stack
| Layer | Tech |
|---|---|
| Backend | PHP 8.4 (`^8.3`), Laravel 13, Socialite (Google) |
| Admin / Rider UI | Filament 5 (+ awcodes/filament-curator for media) |
| Storefront | Blade views + Tailwind 4 via Vite 8 (`resources/js/app.js` is empty) |
| DB | SQLite locally, MariaDB/MySQL in prod |
| Queue / cache / session | `database` driver everywhere (no Redis) |
| Tests | PHPUnit 12, in-memory SQLite |

## Local commands
On this machine use **`php84`** / **`composer84`** (see AGENTS.md).
```bash
composer84 run setup          # install, .env, key, migrate, npm build
php84 artisan migrate:fresh --seed   # demo data (cities, warehouses, products, users)
php84 artisan serve           # http://localhost:8000  (.claude/launch.json "laravel-serve")
npm run dev                   # Vite HMR
php84 artisan test            # all tests
php84 artisan admin:manage    # TUI to create/manage admin users
php84 artisan sitemap:generate
```
Local-only shortcut: `GET /local-admin-login?redirect=/admin` logs in the first super admin (only when `APP_ENV=local`).

Seeded logins (password `password`): `admin@darkstore.np` (super admin), `dharan.manager@darkstore.np` (warehouse manager), `picker@darkstore.np`, customer `customer@darkstore.np`.

## Three auth guards (important)
| Guard | Table / Model | Used by | URL |
|---|---|---|---|
| `web` | `users` / `User` (staff, `role` = `UserRole`) | Admin Filament panel | `/admin` |
| `customer` | `customers` / `Customer` | Storefront | `/login`, `/account/*` |
| `delivery` | `delivery_agents` / `DeliveryAgent` | Rider Filament panel | `/rider` |

`app/Http/Middleware/UseCustomerGuard.php` switches web routes to `customer` unless path is `admin*`/`rider*` or it's a Livewire request. Customers were split out of `users` by migration `2026_09_30_060001_move_customers_to_own_table.php`.

Customer login options: email/password, Google, phone + OTP (`OtpService`, unified sign-in/up), password reset by email or phone OTP.

## Staff roles & scoping
`UserRole`: super_admin, catalog_manager, warehouse_manager, picker_packer, delivery_coordinator, finance, support (+ legacy `customer`).
- `App\Support\WarehouseScope` — a warehouse_manager with `warehouse_id` sees **only** their warehouse. Everyone else is unrestricted (returns null).
- `App\Filament\Concerns\HidesFromWarehouseManagers` — trait hiding global config resources (catalog, cities, coupons, settings…) from warehouse managers.

## Domain model (app/Models)
- **Geography:** `City` (has homepage config) ↔ `Warehouse` (pivot `warehouse_city`), `DeliveryZone`.
- **Catalog:** `Category` (hierarchical), `Brand`, `Product` (gallery via Curator) → `ProductVariant` → `ProductPrice` (per-city pricing).
- **Stock:** `Inventory` (warehouse × variant), `InventoryMovement` (append-only ledger: opening/in/out/transfer_in/transfer_out), `StockReservation` (temporary holds).
- **Orders:** `Customer`, `Address`, `Order`, `OrderItem` (price snapshot), `OrderStatusLog`, `Payment`, `ReturnRequest`.
- **Promo / rules:** `Coupon`, `FreeDeliveryRule`.
- **Misc:** `Setting` (key/value, cached forever as `store_settings` — clear cache after edits outside Filament), `AuditLog`, `OtpCode`.

Enums (`app/Enums`): `OrderStatus` (pending → confirmed → processing → packed → ready_for_dispatch → dispatched → delivered / cancelled / returned), `DeliveryStatus`, `PaymentStatus`, `PaymentMethod` (cod, esewa, khalti, fonepay), `InventoryMovementType`, `UserRole`.

## Services (business logic lives here, not controllers)
| Service | Job |
|---|---|
| `Cart/CartService` | Session cart, city switch revalidation, coupons, `validateCheckout()` |
| `Catalog/CityCatalogService` | City-scoped product listing/detail |
| `Pricing/PricingService` | Authoritative server-side totals (never trust client) |
| `Delivery/FreeDeliveryMatrixService` | Free-delivery rule evaluation |
| `Payment/PaymentMethodService` | Which methods (COD/prepaid) are allowed: global → city → zone → product |
| `Fulfillment/WarehouseSelector` | Picks a warehouse that can fulfil 100% of the order |
| `Inventory/StockReservationService` | `reserve` (row locks) / `confirm` / `release` / `releaseExpired` |
| `Inventory/InventoryService` + `InventoryLedgerService` | Stock adjust/transfer; every change writes a ledger row |
| `Checkout/CheckoutService` | `placeOrder()` and `confirmPrepaidOrder()` |
| `Returns/ReturnService` | request → review → inspect & restock → refund |
| `Sms/SmsService` | `SMS_DRIVER=log` (default) or `sparrow` (Sparrow SMS, Nepal) |
| `Auth/OtpService` | Issue/verify phone OTPs |

### Checkout flow (`CheckoutService::placeOrder`)
1. Resolve/create address → 2. `WarehouseSelector` → 3. `PricingService` recalculation → 4. payment-method eligibility → 5. in one `DB::transaction`: reserve stock (25 min hold), create order + item snapshots, COD confirms stock immediately, status log, coupon usage, clear cart, audit log → 6. dispatch `SendOrderTrackingSms`.
Prepaid: gateway hits `POST /api/payments/callback` → amount check → `confirmPrepaidOrder`. Unpaid holds are freed by `ReleaseExpiredReservations` (scheduled every 5 min).

## Routes
- `routes/web.php` — storefront: home, `/products/{slug}`, auth, `/account/*`, `/cart/*`, `/checkout`, `/orders/{orderNumber}/tracking|return|cancel`, legal pages.
- `routes/api.php` — JSON API mirroring storefront: cities, categories, products, cart, checkout validate, orders, payment callback, `/api/health`.
- `routes/console.php` — scheduler: reservations release (5 min), sitemap (daily).
- `/up` — Laravel health check.

## Filament
- Admin panel: `app/Providers/Filament/AdminPanelProvider.php`, resources auto-discovered in `app/Filament/Resources/*` (simple "Manage*" single-page resources). Custom pages: `WarehouseOperations`, `AdminManual` (built-in user manual). Widget: `DarkStoreStatsOverview`. CSV import/export: `ProductImporter`, `InventoryImporter`, `InventoryExporter`.
- Rider panel: `RiderPanelProvider.php`, one page `app/Filament/Rider/Pages/MyDeliveries.php`.

## Storefront views
`resources/views/layouts/storefront.blade.php` (main layout) + `resources/views/storefront/*`. `AppServiceProvider` view composer injects `allCities`, `currentCity`, `cartItemCount`, `cartDetailed`, `storeSettings` into every storefront view.

## Tests (`tests/Feature`)
`FastDeliveryArchitectureTest` (core flows), `InventoryLedgerTest`, `CustomerGuardTest`, `WarehouseManagerScopeTest`, `RiderPanelTest`, `CategoryHierarchyTest`, `AdminManualTest`.

## Deploy (summary)
DirectAdmin shared host, **no Node.js** → build assets locally and commit them:
```bash
npm run build
git add -f public/build
git commit -m "chore: build frontend assets"
git push
```
Then on server: `bash ~/domains/darkstorenepal.com/app/deploy/deploy.sh` (git reset → composer → migrate → rsync public → patched index.php → optimize). Cron runs `schedule:run` and `queue:work --stop-when-empty` every minute. Secrets live in gitignored `deploy/CREDENTIALS.local.md`.

## Gotchas
- **MySQL vs SQLite:** migrations pass on SQLite but may fail on MySQL — keep index names ≤ 64 chars, drop FKs before their indexes, resolve real index names via `Schema::getIndexes()`.
- **Payment callback has no gateway signature check** (only amount + order match). Add real eSewa/Khalti/Fonepay verification before trusting it in production.
- Google OAuth config falls back to `mock-client-id` if env is missing.
- Spec section refs like `§21`, `§38.2` in code comments point to [work.md](work.md).
- Root clutter (`browser_*.png`, `capture_screenshots.cjs`, `test_cart.cjs`, `seed_city.php`) are ad-hoc dev scripts/screenshots, not part of the app.
