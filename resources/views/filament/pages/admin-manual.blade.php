<x-filament-panels::page>
    <style>
        .manual { color: #1f2937; line-height: 1.65; font-size: 0.95rem; max-width: 72rem; }
        .dark .manual { color: #e5e7eb; }
        .manual h2 { font-size: 1.25rem; font-weight: 700; margin: 2rem 0 0.75rem; padding-bottom: 0.4rem; border-bottom: 2px solid #f59e0b; }
        .manual h3 { font-size: 1rem; font-weight: 600; margin: 1.25rem 0 0.4rem; }
        .manual p, .manual li { margin: 0.35rem 0; }
        .manual ul, .manual ol { padding-left: 1.4rem; }
        .manual ul { list-style: disc; }
        .manual ol { list-style: decimal; }
        .manual code { background: rgba(120, 113, 108, 0.15); padding: 0.1rem 0.35rem; border-radius: 0.25rem; font-size: 0.85rem; }
        .manual table { width: 100%; border-collapse: collapse; margin: 0.75rem 0; font-size: 0.875rem; }
        .manual th, .manual td { border: 1px solid rgba(120, 113, 108, 0.25); padding: 0.45rem 0.6rem; text-align: left; vertical-align: top; }
        .manual th { background: rgba(245, 158, 11, 0.12); font-weight: 600; }
        .manual .toc { display: grid; grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr)); gap: 0.4rem 1rem; background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 0.5rem; padding: 0.9rem 1.1rem; margin: 1rem 0; }
        .manual .toc a { color: #b45309; text-decoration: none; font-weight: 500; }
        .dark .manual .toc a { color: #fbbf24; }
        .manual .toc a:hover { text-decoration: underline; }
        .manual .flow { display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem; margin: 0.6rem 0; }
        .manual .flow span { background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.4); border-radius: 9999px; padding: 0.15rem 0.7rem; font-size: 0.8rem; font-weight: 600; white-space: nowrap; }
        .manual .note { background: rgba(59, 130, 246, 0.08); border-left: 4px solid #3b82f6; padding: 0.6rem 0.9rem; border-radius: 0.25rem; margin: 0.75rem 0; }
        .manual .warn { background: rgba(239, 68, 68, 0.08); border-left: 4px solid #ef4444; padding: 0.6rem 0.9rem; border-radius: 0.25rem; margin: 0.75rem 0; }
    </style>

    <div class="manual">
        <p>
            This manual explains every screen in the <strong>DarkStore Dharan Operations Hub</strong> — the staff
            panel used to manage the storefront's orders, catalog, stock, delivery and settings. Everything below is
            also reachable from the left sidebar.
        </p>

        <div class="toc">
            <a href="#signing-in">1. Signing In</a>
            <a href="#dashboard">2. Dashboard</a>
            <a href="#orders">3. Orders</a>
            <a href="#floor-ops">4. Warehouse Floor Operations</a>
            <a href="#products">5. Products &amp; Variants</a>
            <a href="#import">6. Importing Products (CSV)</a>
            <a href="#categories">7. Categories &amp; Brands</a>
            <a href="#inventory">8. Inventory</a>
            <a href="#cities">9. Cities &amp; Warehouses</a>
            <a href="#delivery">10. Delivery Agents</a>
            <a href="#free-delivery">11. Free Delivery Matrix</a>
            <a href="#coupons">12. Coupons</a>
            <a href="#returns">13. Return Requests</a>
            <a href="#settings">14. Settings</a>
            <a href="#audit">15. Audit Logs</a>
            <a href="#routine">16. Daily Routine (Quick List)</a>
        </div>

        <h2 id="signing-in">1. Signing In</h2>
        <ul>
            <li>Open <code>/admin</code> on the site and log in with your staff email and password.</li>
            <li>Only staff accounts can enter. Customer accounts are refused at the door; customer registration
                happens on the storefront, not here.</li>
            <li>If you are signed out mid-task, log in again — unsaved form input is lost.</li>
        </ul>

        <h2 id="dashboard">2. Dashboard</h2>
        <p>First screen after login. Four live counters at the top:</p>
        <ul>
            <li><strong>Today's Fast Orders</strong> — orders placed today.</li>
            <li><strong>Today's Revenue</strong> — value of today's orders (cancelled excluded).</li>
            <li><strong>Out for Delivery</strong> — orders currently with riders.</li>
            <li><strong>Low Stock Alerts</strong> — variants at or below their warehouse reorder level. Red means act now.</li>
        </ul>

        <h2 id="orders">3. Orders</h2>
        <p>
            The <strong>Orders</strong> screen lists every order, newest first. Use the search box for an order
            number or customer name, and the filters for <em>Status</em>, <em>Payment Status</em>, <em>City</em>,
            <em>Warehouse</em> and the <em>Placed At</em> date range.
        </p>
        <h3>Row actions (per order)</h3>
        <ul>
            <li><strong>View</strong> — full detail: items, customer, delivery address, assigned rider and payment summary.</li>
            <li><strong>Update Status</strong> — the normal way to move an order forward. Pick the new status and type
                a short reason (required — it is recorded in the order history and audit log).</li>
            <li><strong>Cancel</strong> — only shown while the order is still cancellable (before the store has packed it).
                A reason is required. Cancelling automatically releases the reserved stock back to the warehouse.</li>
        </ul>
        <h3>Order lifecycle</h3>
        <div class="flow">
            <span>Pending</span> → <span>Confirmed</span> → <span>Processing</span> → <span>Packed</span> →
            <span>Ready for Dispatch</span> → <span>Dispatched</span> → <span>Delivered</span>
        </div>
        <p>From almost any state an order can also become <strong>Cancelled</strong> (before packing) or
            <strong>Returned</strong> (after delivery).</p>
        <div class="note">Always use <strong>Update Status</strong> or <strong>Cancel</strong> to change an order's
            state — never edit status by hand. The action records the transition, the timestamp and who did it.</div>

        <h2 id="floor-ops">4. Warehouse Floor Operations</h2>
        <p>
            The picker/packer workbench. Choose a warehouse at the top (staff with an assigned warehouse get it
            pre-selected) and you get a kanban board of that warehouse's active orders:
        </p>
        <table>
            <thead>
                <tr><th>Column</th><th>Meaning</th><th>Button moves it to</th></tr>
            </thead>
            <tbody>
                <tr><td>New</td><td>Confirmed, not started</td><td>Start Picking → Picking</td></tr>
                <tr><td>Picking</td><td>Items being collected</td><td>Finish Packing → Packing</td></tr>
                <tr><td>Packing</td><td>Items packed, bag being sealed</td><td>Mark Ready → Ready for Dispatch</td></tr>
                <tr><td>Ready</td><td>Staged at the dispatch bay</td><td>Dispatch → Dispatched (assigns a rider)</td></tr>
                <tr><td>Dispatched</td><td>Handed to a rider</td><td>—</td></tr>
            </tbody>
        </table>
        <ul>
            <li><strong>Dispatch</strong> assigns an active rider for that city automatically (and marks the delivery
                as assigned). Use it only when the bag is physically leaving.</li>
            <li><strong>Barcode / SKU scan</strong> — type or scan a SKU and submit. The board answers whether that SKU
                is needed by any order still in New or Picking, and in which order. Handy for sorting a pile of items.</li>
        </ul>

        <h2 id="products">5. Products &amp; Variants</h2>
        <p>
            A <strong>Product</strong> is the shelf concept (name, brand, category, photo). A <strong>variant</strong>
            is a buyable pack size (500ml bottle, 1kg bag…) with its own SKU, barcode and price.
        </p>
        <h3>Create / edit a product</h3>
        <ol>
            <li>Products → <strong>New product</strong>.</li>
            <li>Type the <strong>name</strong> — the slug (used in the storefront URL) is filled automatically for new
                products; you can adjust it.</li>
            <li>Choose brand and category, set the status: <code>active</code> (visible), <code>inactive</code>
                (hidden) or <code>draft</code>.</li>
            <li>Pick a <strong>primary image</strong> and any <strong>gallery images</strong> from the media library
                (upload new ones right in the picker).</li>
            <li>Add a description, then <strong>Save</strong>.</li>
        </ol>
        <h3>Variants tab</h3>
        <p>Open a product (or right after creating it) and use the <strong>Variants (Pack Sizes)</strong> section:</p>
        <ul>
            <li><strong>Variant name</strong> — e.g. “500ml Pouch”. The SKU is suggested automatically on create if the SKU field is empty.</li>
            <li><strong>SKU</strong> — must be unique; this is what the storefront and the floor scanner match on.</li>
            <li><strong>Barcode</strong>, <strong>Base Price (Rs)</strong>, <strong>Weight (kg)</strong>.</li>
            <li><strong>Cash on Delivery</strong> — allow or block COD for this variant.</li>
            <li><strong>Total Stock</strong> column shows the summed stock across warehouses (green = available, red = zero).</li>
        </ul>

        <h2 id="import">6. Importing Products (CSV)</h2>
        <p>
            Bulk-create or update products from a spreadsheet. On the Products list use
            <strong>Import Products</strong> (top-right), upload a CSV, map your columns, and run it. Re-importing the
            same file updates existing products instead of duplicating them (matching is by slug).
        </p>
        <table>
            <thead>
                <tr><th>CSV column</th><th>Required</th><th>Notes</th></tr>
            </thead>
            <tbody>
                <tr><td>Product Name</td><td>Yes</td><td>The product title.</td></tr>
                <tr><td>Slug</td><td>No</td><td>Leave blank to generate from the name. An existing slug is updated, not duplicated.</td></tr>
                <tr><td>Category</td><td>No</td><td>Must exactly match an existing category name.</td></tr>
                <tr><td>Brand</td><td>No</td><td>Must exactly match an existing brand name.</td></tr>
                <tr><td>Description</td><td>No</td><td>Up to 5000 characters.</td></tr>
                <tr><td>Status</td><td>No</td><td><code>active</code>, <code>inactive</code> or <code>draft</code>. Defaults to active.</td></tr>
                <tr><td>Image URL</td><td>No</td><td>Direct link to a photo; add it later from the media library if blank.</td></tr>
            </tbody>
        </table>
        <div class="note">Create the categories and brands first — the importer will not invent them. After import,
            add variants (SKUs/prices) and put stock into Inventory; imported products without a variant are not
            sellable yet.</div>

        <h2 id="categories">7. Categories &amp; Brands</h2>
        <ul>
            <li><strong>Categories</strong> — name, slug, sort order, image and status. They drive the storefront
                category rails and filters. Keep the sort order tidy (lower numbers first).</li>
            <li><strong>Brands</strong> — name and slug only. Used for filtering and product pages.</li>
        </ul>

        <h2 id="inventory">8. Inventory</h2>
        <p>
            One row per variant per warehouse: how much is on the shelf. Fields include <strong>quantity</strong>
            (physical stock), <strong>reserved quantity</strong> (held by pending orders) and the
            <strong>reorder level</strong> that feeds the dashboard's Low Stock alert.
        </p>
        <ul>
            <li>When stock physically arrives, increase quantity here.</li>
            <li>If a row is missing for a variant/warehouse combination, create it — the storefront cannot sell what
                has no inventory row.</li>
            <li>Reserved quantities are managed by the store automatically at checkout; you do not edit them by hand.</li>
        </ul>

        <h2 id="cities">9. Cities &amp; Warehouses</h2>
        <ul>
            <li><strong>Cities</strong> — each city controls its own storefront experience: default delivery fee,
                free-delivery minimum, estimated delivery minutes, COD and prepaid toggles, plus the homepage
                <strong>banners</strong> and <strong>featured products</strong> shown to shoppers in that city.</li>
            <li><strong>Warehouses</strong> — the dark stores that fulfil orders (name, code, address, map location,
                status). Inventory and floor operations are organised per warehouse.</li>
        </ul>

        <h2 id="delivery">10. Delivery Agents</h2>
        <p>
            Your rider roster: name, phone, city, status and current availability. Only <strong>active</strong> riders
            are offered when dispatching from the floor board.
        </p>

        <h2 id="free-delivery">11. Free Delivery Matrix</h2>
        <p>Rules that give customers free delivery and tell them why. Each rule has:</p>
        <ul>
            <li><strong>Rule name</strong> — shown to the customer, e.g. “Free delivery on 2+ Amul Butter”.</li>
            <li><strong>City</strong> — leave empty to apply in all cities.</li>
            <li><strong>Specific product</strong> <em>or</em> <strong>any product in a category</strong> — the trigger.</li>
            <li><strong>Minimum quantity</strong> of that product/category (defaults to 1).</li>
            <li><strong>Minimum cart subtotal (Rs)</strong> — optional extra condition, or use alone as a city-wide threshold.</li>
            <li><strong>Active</strong> toggle to switch the rule on/off without deleting it.</li>
        </ul>
        <div class="note">If several rules match the same cart, the best one wins at checkout. Test new rules by
            adding the product to a storefront cart for the right city.</div>

        <h2 id="coupons">12. Coupons</h2>
        <ul>
            <li><strong>Code</strong> — what the customer types, e.g. <code>DHARANFAST</code>.</li>
            <li><strong>City</strong> — restrict to one city, or leave empty for all cities.</li>
            <li><strong>Type / value</strong> — fixed rupees off or a percentage, with an optional
                <strong>maximum discount</strong> cap for percentages.</li>
            <li><strong>Minimum order</strong>, <strong>usage limit</strong> and <strong>status</strong>.</li>
        </ul>

        <h2 id="returns">13. Return Requests</h2>
        <p>
            Requests raised by customers from their storefront account appear here for review. Open a request to see
            the order, reason and items, and update its status as you process it.
        </p>

        <h2 id="settings">14. Settings</h2>
        <p>Store-wide content used across the site:</p>
        <ul>
            <li><strong>Store name</strong>, store <strong>icon</strong> and <strong>favicon</strong>.</li>
            <li><strong>SEO defaults</strong> — meta title, meta description and social share image.</li>
            <li><strong>Contact page content</strong> — the text shown on the storefront contact page.</li>
        </ul>

        <h2 id="audit">15. Audit Logs</h2>
        <p>
            A read-only, searchable history of important actions — who changed what, when and why (including order
            status changes from the floor board and this panel). Use it to answer “who cancelled this order?”.
        </p>

        <h2 id="routine">16. Daily Routine (Quick List)</h2>
        <ol>
            <li>Open the <strong>Dashboard</strong> — check Low Stock Alerts first.</li>
            <li><strong>Warehouse Floor Operations</strong> — work New orders through Picking → Packing → Ready.</li>
            <li><strong>Dispatch</strong> ready bags so riders get assigned.</li>
            <li><strong>Orders</strong> — filter <em>Dispatched</em> and confirm deliveries; mark <strong>Delivered</strong>.</li>
            <li>Top up <strong>Inventory</strong> for anything the dashboard flagged, then re-check Products/Variants.</li>
        </ol>

        <div class="warn">Superadmin housekeeping: the seeded admin account starts with a default password — change
            it, and keep one shared ops login out of customer hands. Backups of the database run from the server;
            ask the developer before big bulk imports.</div>
    </div>
</x-filament-panels::page>
