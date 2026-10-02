# Standalone Marketing Landing Page Script

A high-converting, independent marketing landing page designed to sell the **ZoomNearby Multi-Tenant POS & Business Management SaaS Script** and its add-on vertical modules (Lead Manager, Pharmacy POS, Salon Management, Repair Technician, etc.).

This script is **100% portable** and can be hosted on **ANY domain**, sub-domain, or hosting provider (cPanel, CloudPanel, Nginx, Apache, VPS, or shared hosting).

---

## 🌟 Key Features

1. **Host on ANY Domain**:
   - Completely standalone PHP application.
   - Zero framework dependencies (runs on PHP 7.4 through PHP 8.4+).
   - No Composer or NPM build steps required.

2. **Core Script & Bundle Selling Options**:
   - Sell Core Platform Script standalone (e.g. $49.00).
   - Pre-configured discounted bundles (e.g. **Core Script + Lead Manager** at $69.00, or **All-in-One Enterprise Suite** at $119.00).
   - **Interactive Custom Bundle Builder**: Buyers can dynamically check/uncheck modules (Lead Management, Pharmacy, Salon, Repair Center) and see real-time price totals with automatic multi-item bundle discounts!

3. **Integrated with Central License Manager (`/lic`)**:
   - Uses the existing payment gateways (Razorpay, Stripe) already configured in your License Manager (`/lic/admin/settings.php`).
   - All transactions, order references, and customer purchase histories are automatically saved into the License Manager's `payments` table.
   - Separate license keys are automatically generated for the Core platform and each module in the bundle.
   - All license records are stored in the License Manager's `licenses` table.

4. **Automated License Delivery via Email**:
   - As soon as payment is confirmed, a branded HTML email is dispatched to the buyer's registered email address containing:
     - Order reference & payment receipt
     - Bound target domain
     - Individual license keys for Core and each purchased module
     - Step-by-step installation and module activation instructions.

5. **Built-in License Verification Widget**:
   - Customers can check the live status, expiry, and domain binding of their keys directly on `verify-license.php`.

---

## 🚀 How to Host on Any Domain

### Step 1: Upload Files
Upload the contents of the `landing-marketing/` folder (or the downloaded `marketing-landing-script.zip`) to the document root of your desired marketing domain (e.g. `https://buypos.com` or `https://zoomnearby.com`).

### Step 2: Configure `config.php`
Open `config.php` and set your License Server URL:

```php
// Point this to your License Manager URL
define('LICENSE_SERVER_URL', 'https://license.zoomnearby.com'); // or https://yourdomain.com/lic

// Customize branding
define('SITE_NAME', 'ZoomNearby POS & Business SaaS');
define('SITE_TAGLINE', 'The Complete Self-Hosted Business Management & POS Platform');
define('SUPPORT_EMAIL', 'support@zoomnearby.com');
```

That's it! Your marketing landing page is immediately live and ready to take customer orders.

---

## 🛠️ Managing Products & Bundles in Admin

You can manage all products, prices, and bundles from your central License Manager admin panel (`/lic/admin/`):

1. **Products (`/lic/admin/products.php`)**:
   - Update Core platform price (e.g. $49.00).
   - Add/edit modules (`leadmanagement`, `pharmacy`, `salon`, `repairtechnician`) and upload their `.zip` packages.

2. **Bundles (`/lic/admin/bundles.php`)**:
   - Create new bundles combining Core with any modules.
   - Set custom bundle pricing (e.g. Core + Lead Manager for $69.00).
   - The marketing page automatically fetches active bundles from the API!

3. **Orders & History (`/lic/admin/payments.php`)**:
   - View all customer orders, gateway references, and issued keys.
   - Resend license keys email to the buyer anytime with a single click.

4. **Email / SMTP Settings (`/lic/admin/settings.php`)**:
   - Configure PHP `mail()` or custom SMTP server (Gmail, Postmark, SendGrid, Amazon SES, or private mail server).
   - Use the built-in "Send Test Email" tool to verify delivery.

---

## 📁 File Structure

```
landing-marketing/
├── assets/
│   ├── css/
│   │   └── marketing.css            # Responsive, modern styling with dark hero & sections
│   ├── js/
│   │   └── marketing.js             # Interactive bundle configurator & live pricing
│   └── images/
│       ├── hero-devices.png         # Modern POS hardware terminal showcase
│       ├── dashboard-preview.png    # High-res SuperAdmin & store analytics preview
│       ├── restaurant-pos-mockup.png# Dine-in tables & kitchen display preview
│       ├── retail-pos-mockup.png    # Barcode & retail checkout preview
│       └── ecosystem-diagram.png    # Centralized core & module ecosystem
├── config.php                       # License server URL & dynamic API bridge
├── index.php                        # High-converting marketing landing page
├── .htaccess                        # Standalone Apache / LiteSpeed configuration (prevents Laravel rewrite conflicts)
├── translations.php                 # Multi-language translation dictionaries
├── privacy-policy.php               # Privacy Policy page
├── return-policy.php                # Refund & Return Policy page
├── checkout.php                     # Bridge forwarder to license manager checkout
├── verify-license.php               # Customer self-service license lookup
└── README.md                        # Documentation
```

---

## ⚡ Hostinger / LiteSpeed / cPanel Deployment Tips

When uploading to **Hostinger** (or any shared host using LiteSpeed / Apache):
1. **Ensure Directory & File Permissions**:
   - Folders (`assets`, `css`, `images`, `js`): `755` (`rwxr-xr-x`)
   - Files (`index.php`, `marketing.css`, `hero-devices.png`, etc.): `644` (`rw-r--r--`)
2. **Subfolder Setup** (e.g. `domain.com/marketing/`):
   - The included `.htaccess` automatically ensures LiteSpeed serves static assets (`.css`, `.js`, `.png`) directly without being intercepted by parent Laravel routing.
   - Always access with a trailing slash (e.g. `https://yourdomain.com/marketing/`). The script also auto-redirects to add trailing slashes automatically.


