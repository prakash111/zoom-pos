License Order Delivery & Invoice Attachment Update

NEW FEATURES:
1. Automated PDF Invoice Attachment:
   - When an order is completed (via Stripe/Razorpay webhook or Admin "Save & Email" / "Send Order Email"),
     a clean, professional PDF tax invoice (Invoice-<reference>.pdf) is generated and attached to the fulfillment email.
   - The email body now displays a clear notification to the customer:
     "📎 Invoice Attached: A formal PDF tax invoice (Invoice-ORD-xxx.pdf) is attached to this email for your accounting records."
   - Pure PHP 8 standard library implementation with zero Composer dependencies.

2. Admin Invoice Management:
   - Admin -> Payment Orders now has direct action buttons for completed/paid orders:
     * "📄 View Invoice": Interactive, responsive, printable HTML invoice in a new tab.
     * "📥 Invoice PDF": Direct one-click downloadable PDF invoice.

3. Configurable Invoice & Seller Settings (Admin -> Settings):
   - Checkbox: "Attach PDF Invoice to Completed Order Email automatically" (enabled by default).
   - Legal Company / Seller Name (defaults to Brand Name if blank).
   - Tax ID / GSTIN / VAT Number (displayed on the invoice header if specified).
   - Company Address & Contact Info (displayed on the invoice if specified).
   - Invoice Footer Note (customizable closing note).

4. Email Sender / From Address Fix:
   - Enforces the configured Sender Email (licenses@zoomnearby.com) across From, Reply-To, Return-Path, and Sender headers.
   - For PHP native mail(), the envelope sender flag `-f licenses@zoomnearby.com` is explicitly passed.

5. UTF-8 Custom Features & 500 Error Fix:
   - Fixed byte-oriented trim bug in `admin/products.php` and `admin/bundles.php` that corrupted multi-byte UTF-8 characters (like `✘` \xE2\x9C\x98) and caused MySQL JSON validation errors / HTTP 500 Internal Server Error.
   - Safely parses and preserves Unicode symbols (`✔`, `✘`, `Café`, bullet points, and section titles like `Complete Core POS Platform`, `Included Modules`, `Not Included`).
   - Added automatic migration checking for `custom_features` column on `products` and `bundles`.
   - Wrapped database saves in try/catch exception handlers to prevent uncaught fatal server crashes.

DEPLOYMENT INSTRUCTIONS:
1. Upload and extract this ZIP into the license.zoomnearby.com root directory on Hostinger / cPanel.
   Overwrite existing files when prompted.
2. Visit Admin -> Settings (https://license.zoomnearby.com/admin/settings.php) to review:
   - "Attach PDF Invoice to Completed Order Email automatically" (checked by default)
   - Optional Seller Legal Name, Tax ID / GSTIN / VAT Number, and Address
3. Visit Admin -> Products or Admin -> Bundles: you can now freely paste full feature lists with checkmarks, crosses, and section headers without 500 errors.
4. Test by completing an order or clicking "Resend Licenses & Downloads" / "Send Order Email" on any completed order.

