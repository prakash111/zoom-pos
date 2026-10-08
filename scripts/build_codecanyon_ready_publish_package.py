#!/usr/bin/env python3
"""
Master CodeCanyon Submission Package Builder (v1.0.6)
Generates a 100% clean, verified, installable CodeCanyon-ready release archive
with complete marketplace content, documentation, database SQL, Flutter source,
and full resolution graphics/banners.
"""

import os
import sys
import shutil
import zipfile
import subprocess
import time
import hashlib

ROOT_DIR = "/home/zoomnearby-saas/htdocs/saas.zoomnearby.com"
SCRATCH_DIR = "/home/zoomnearby-saas/.gemini/antigravity-cli/brain/6ba9fd9d-a989-4691-8541-ba1afbe22dbc/scratch"
STAGING_DIR = os.path.join(SCRATCH_DIR, "codecanyon_package_v106")

PRIMARY_ZIP = os.path.join(ROOT_DIR, "public", "Zoom-Sales-CRM-POS-v1.0.6-Codecanyon-Package.zip")
ALIAS_ZIP_1 = os.path.join(ROOT_DIR, "public", "zoom-sales-crm-codecanyon.zip")
ALIAS_ZIP_2 = os.path.join(ROOT_DIR, "public", "zoom-pos-system.zip")
ALIAS_ZIP_3 = os.path.join(ROOT_DIR, "public", "zoom-sales-pos-saas.zip")
MARKETPLACE_ZIP = os.path.join(ROOT_DIR, "public", "CodeCanyon_Marketplace_Assets_and_Banners.zip")
LARAVEL_INSTALLABLE_ZIP = os.path.join(ROOT_DIR, "public", "laravel-installable.zip")
LARAVEL_INSTALLABLE_ALIAS = os.path.join(ROOT_DIR, "public", "zoom-sales-crm-laravel-installable.zip")
FLUTTER_SOURCE_ZIP = os.path.join(ROOT_DIR, "public", "zoom-sales-crm-flutter-pos-source-v1.0.6.zip")
FLUTTER_SOURCE_ALIAS = os.path.join(ROOT_DIR, "public", "zoom-sales-crm-flutter-pos-source-v1.0.4.zip")
DOCUMENTATION_ZIP = os.path.join(ROOT_DIR, "public", "zoom-sales-crm-documentation.zip")
DOCUMENTATION_ALIAS = os.path.join(ROOT_DIR, "public", "documenation.zip")

print("================================================================================")
print("  ZOOM SALES CRM & POS — CODECANYON SUBMISSION PACKAGE BUILDER (v1.0.6)        ")
print("================================================================================")

if os.path.exists(STAGING_DIR):
    print(f"Cleaning previous staging directory: {STAGING_DIR}...")
    shutil.rmtree(STAGING_DIR)

os.makedirs(STAGING_DIR, exist_ok=True)

# ------------------------------------------------------------------------------
# STEP 1: 01_Laravel_Backend_Web_Source
# ------------------------------------------------------------------------------
print("\n[1/6] Preparing 01_Laravel_Backend_Web_Source...")
backend_dir = os.path.join(STAGING_DIR, "01_Laravel_Backend_Web_Source")
os.makedirs(backend_dir, exist_ok=True)

core_dirs = [
    "app",
    "config",
    "database",
    "lang",
    "resources",
    "routes",
    "vendor",
]

for item in core_dirs:
    src = os.path.join(ROOT_DIR, item)
    dst = os.path.join(backend_dir, item)
    if os.path.exists(src):
        print(f"  -> Copying {item}...")
        shutil.copytree(src, dst, symlinks=True, ignore=shutil.ignore_patterns(
            ".git*", "__pycache__", "*.pyc", "*.log", "*.sqlite3", ".phpunit.result.cache", "*.zip", "*.tmp"
        ))

# Ensure modules folder exists and is clean
print("  -> Configuring clean modules/ directory...")
os.makedirs(os.path.join(backend_dir, "modules"), exist_ok=True)
with open(os.path.join(backend_dir, "modules", ".gitkeep"), "w") as f:
    pass
with open(os.path.join(backend_dir, "modules", ".htaccess"), "w") as f:
    f.write("Deny from all\n")
with open(os.path.join(backend_dir, "modules", "README.md"), "w") as f:
    f.write("""# Pluggable Modules Directory

The 2 core business verticals (**Retail POS** and **Restaurant & Cafe POS**) are natively built into the core script architecture.

Additional extension modules can be uploaded and managed via the SuperAdmin portal (**SuperAdmin -> Modules**).
""")

# Bootstrap directory
print("  -> Configuring clean bootstrap/cache/...")
os.makedirs(os.path.join(backend_dir, "bootstrap", "cache"), exist_ok=True)
for bf in ["app.php", "providers.php"]:
    src_f = os.path.join(ROOT_DIR, "bootstrap", bf)
    if os.path.exists(src_f):
        shutil.copy2(src_f, os.path.join(backend_dir, "bootstrap", bf))
with open(os.path.join(backend_dir, "bootstrap", "cache", ".gitignore"), "w") as f:
    f.write("*\n!.gitignore\n")

# Storage directory structure
print("  -> Configuring clean storage/ subdirectories...")
storage_dirs = [
    "app/public",
    "framework/cache/data",
    "framework/sessions",
    "framework/views",
    "logs",
]
for sd in storage_dirs:
    full_sd = os.path.join(backend_dir, "storage", sd)
    os.makedirs(full_sd, exist_ok=True)
    with open(os.path.join(full_sd, ".gitignore"), "w") as f:
        f.write("*\n!.gitignore\n")

# Copy fonts if present
src_fonts = os.path.join(ROOT_DIR, "storage", "fonts")
if os.path.exists(src_fonts):
    shutil.copytree(src_fonts, os.path.join(backend_dir, "storage", "fonts"), dirs_exist_ok=True)

# Clean public directory
print("  -> Configuring clean public/ directory...")
dst_public = os.path.join(backend_dir, "public")
os.makedirs(dst_public, exist_ok=True)

for pf in ["index.php", ".htaccess", "robots.txt", "favicon.ico", "favicon.png", "restaurant.mp3", "sw.js", "offline.html"]:
    sp = os.path.join(ROOT_DIR, "public", pf)
    if os.path.exists(sp):
        shutil.copy2(sp, os.path.join(dst_public, pf))

for ps in ["assets", "build", "pwa", "vendor"]:
    src_ps = os.path.join(ROOT_DIR, "public", ps)
    if os.path.exists(src_ps):
        shutil.copytree(src_ps, os.path.join(dst_public, ps), symlinks=True, ignore=shutil.ignore_patterns("*.zip", "*.tar.gz"))

# Web POS Client
src_pos_web = os.path.join(ROOT_DIR, "public", "pos-web")
if os.path.exists(src_pos_web):
    print("  -> Copying public/pos-web/ (Pre-compiled Web POS)...")
    shutil.copytree(
        src_pos_web,
        os.path.join(dst_public, "pos-web"),
        symlinks=True,
        ignore=shutil.ignore_patterns("*.zip", "release-output", "build")
    )

# Root configuration files
root_files = [
    "index.php",
    "artisan",
    "composer.json",
    "composer.lock",
    "package.json",
    "package-lock.json",
    "vite.config.js",
    "phpunit.xml",
    ".gitattributes",
    ".gitignore",
    ".editorconfig",
    ".htaccess",
]
for rf in root_files:
    s = os.path.join(ROOT_DIR, rf)
    if os.path.exists(s):
        shutil.copy2(s, os.path.join(backend_dir, rf))

# Clean .env.example with v1.0.6 and DEMO_MODE=false
src_env_example = os.path.join(ROOT_DIR, ".env.example")
if os.path.exists(src_env_example):
    with open(src_env_example, "r") as f:
        env_content = f.read()
    env_content = env_content.replace("APP_VERSION=1.0.5", "APP_VERSION=1.0.6")
    env_content = env_content.replace("DEMO_MODE=true", "DEMO_MODE=false")
    env_content = env_content.replace("APP_KEY=base64:1oDNYMXOaW8NhkdlqHXkEIrktd7dv579AIovWD/v294=", "APP_KEY=")
    with open(os.path.join(backend_dir, ".env.example"), "w") as f:
        f.write(env_content)

# Clean backend README and INSTALL
backend_readme = """# Zoom Sales CRM & Inventory — Multi-Tenant SaaS Web Platform (v1.0.6)

Welcome to **Zoom Sales CRM & Inventory**, an enterprise-grade multi-tenant SaaS point-of-sale and business management platform.

This package contains the complete, production-ready system including:
1. 🛒 **Retail POS**: Barcode scanning, weighing-scale barcodes, fast cart checkout, quotations, receipt printing, customer ledgers, and multi-store inventory.
2. 🍽️ **Cafe & Restaurant POS**: Dining tables floor plan, Kitchen Order Tickets (KOT) printing, Kitchen Display System (KDS), and waiter ordering.
3. 🌐 **Web POS Client**: Pre-compiled progressive web app running at `/pos-web/`.
4. 📱 **Flutter Mobile & Desktop POS Source**: Full source code included in `flutter_pos_source/` (Android APK, Windows Desktop .exe, Web PWA).
5. 📖 **Offline Documentation Portal**: Complete offline interactive guides included in `documentation/`.
6. ⚡ **Universal Hosting Support**: Works out of the box on any hosting type (cPanel, Plesk, LiteSpeed, Apache, Nginx, Shared Hosting, or VPS).

---

## 📋 System Requirements

- **PHP**: 8.2 or 8.3
- **PHP Extensions Required**:
  - `BCMath`, `Ctype`, `cURL`, `DOM`, `Fileinfo`, `Filter`, `Hash`, `Intl`, `JSON`, `Mbstring`, `OpenSSL`, `PCRE`, `PDO`, `PDO_MySQL`, `Session`, `Tokenizer`, `XML`, `ZIP`
- **Database**: MySQL 5.7+ / 8.0+ or MariaDB 10.4+
- **Web Server**: Apache (`mod_rewrite` enabled), LiteSpeed, or Nginx
- **HTTPS / SSL**: Recommended for production

---

## 🚀 Installation Instructions

### Option 1: 1-Click Interactive Web Installer (Recommended)
1. Extract all files into your web root directory (e.g. `public_html` on cPanel or `/var/www/zoompos` on VPS).
2. Open your browser and navigate to your domain:
   ```
   https://yourdomain.com
   ```
   *(The system automatically detects fresh hosting and directs you to the setup wizard at `https://yourdomain.com/install`)*
3. Follow the interactive setup wizard:
   - System requirements & extension check
   - Database connection settings
   - Automatic database migration & seeding
   - SuperAdmin account creation
4. Finish and log in!

### Option 2: Fast Manual Installation via database.sql
1. Extract all files to your web server root and point document root to `public/`.
2. Create a clean MySQL database (e.g. `zoom_pos`) in cPanel or MySQL console.
3. Import `database.sql` from `03_Database_SQL/database.sql` via phpMyAdmin or terminal:
   ```bash
   mysql -u your_user -p your_database < database.sql
   ```
4. Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```
5. Update your database connection in `.env`:
   ```ini
   APP_NAME="Zoom Sales CRM & Inventory"
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://yourdomain.com

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=your_database_name
   DB_USERNAME=your_database_user
   DB_PASSWORD=your_database_password
   ```
6. Generate an application encryption key:
   ```bash
   php artisan key:generate
   ```
7. Link public storage:
   ```bash
   php artisan storage:link
   ```
8. Set directory permissions:
   ```bash
   chmod -R 775 storage bootstrap/cache
   ```

---

## 🔐 Default Credentials (When importing database.sql)

- **SuperAdmin Portal**:
  - **URL**: `https://yourdomain.com/login`
  - **Email**: `admin@zoompos.com`
  - **Password**: `admin1234`

- **Demo Store Manager**:
  - **URL**: `https://yourdomain.com/store/login` (or `/tenant/login`)
  - **Email**: `store@demo.com`
  - **Password**: `admin1234`

- **Demo Cashier**:
  - **Email**: `cashier@demo.com`
  - **Password**: `admin1234`

*(Please change default passwords immediately upon logging into the platform!)*

---

## ⚙️ Cron Job & Automation

Configure the Laravel scheduler to run every minute in server crontab or cPanel Cron Jobs:
```bash
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🌐 Web POS Access

The package includes the full Web POS client pre-compiled in `public/pos-web/`:
- **Web POS**: `https://yourdomain.com/pos-web/`
- **Native Livewire POS**: `https://yourdomain.com/pos`
- **Restaurant POS & Tables**: `https://yourdomain.com/restaurant/pos` and `/restaurant/tables`
"""

with open(os.path.join(backend_dir, "README.md"), "w") as f:
    f.write(backend_readme)
with open(os.path.join(backend_dir, "INSTALL.md"), "w") as f:
    f.write(backend_readme)

# Copy database.sql into backend database/ folder as well for convenience
clean_sql_src = os.path.join(ROOT_DIR, "public", "zoom-sales-crm-database-clean.sql")
if os.path.exists(clean_sql_src):
    shutil.copy2(clean_sql_src, os.path.join(backend_dir, "database", "database.sql"))

# ------------------------------------------------------------------------------
# STEP 2: 02_Flutter_POS_Mobile_Desktop_Source
# ------------------------------------------------------------------------------
print("\n[2/6] Preparing 02_Flutter_POS_Mobile_Desktop_Source...")
flutter_dir = os.path.join(STAGING_DIR, "02_Flutter_POS_Mobile_Desktop_Source")
os.makedirs(flutter_dir, exist_ok=True)
mobile_src = os.path.join(ROOT_DIR, "mobile")

flutter_subdirs = ["android", "windows", "web", "lib", "test", "assets"]
for fsub in flutter_subdirs:
    s = os.path.join(mobile_src, fsub)
    d = os.path.join(flutter_dir, fsub)
    if os.path.exists(s):
        print(f"  -> Copying {fsub}...")
        shutil.copytree(s, d, symlinks=True, ignore=shutil.ignore_patterns(
            ".git*", ".dart_tool*", "ephemeral", ".gradle", "local.properties", "*.log", "build", "*.jks"
        ))

flutter_files = ["pubspec.yaml", "pubspec.lock", "analysis_options.yaml", "codemagic.yaml", "installer.iss", "installer_info.txt"]
for ff in flutter_files:
    s = os.path.join(mobile_src, ff)
    if os.path.exists(s):
        shutil.copy2(s, os.path.join(flutter_dir, ff))

flutter_readme = """# Zoom POS — Flutter Cross-Platform Client (Android, Windows Desktop & Web) (v1.0.6)

This folder contains the complete source code for the **Zoom POS** client application built with **Flutter 3.22+**.

It supports:
- 📱 **Android**: Handheld wireless POS terminals, tablets, and smartphones.
- 🪟 **Windows**: 64-bit native desktop application with direct ESC/POS thermal printer support.
- 🌐 **Web**: Progressive Web Application (PWA) running in all modern browsers.

---

## 🛠️ Prerequisites

- **Flutter SDK**: 3.22.x or higher
- **Dart SDK**: 3.4.x or higher
- **Android Studio** (for Android APK / AAB compilation)
- **Visual Studio 2022** with "Desktop development with C++" (for Windows compilation)
- **Inno Setup 6** (optional, for compiling Windows desktop installer `.exe`)

---

## ⚙️ Configuration: Connecting to Your Backend

Open:
`lib/core/config/app_config.dart`

Update `defaultBaseUrl` to point to your deployed Laravel backend:
```dart
class AppConfig {
  AppConfig._();

  // Change this to your SaaS platform domain:
  static const String defaultBaseUrl = 'https://yourdomain.com';
  static const String apiPrefix = '/api/v1/pos';
}
```

---

## 🚀 Build Instructions

### 1. Install Dependencies
```bash
flutter pub get
```

### 2. Build for Android (APK)
```bash
flutter build apk --release
```
The generated APK will be at:
`build/app/outputs/flutter-apk/app-release.apk`

### 3. Build for Android (Google Play App Bundle)
```bash
flutter build appbundle --release
```
The generated bundle will be at:
`build/app/outputs/bundle/release/app-release.aab`

### 4. Build for Windows Desktop
```bash
flutter build windows --release
```
The compiled Windows binary and DLLs will be in:
`build/windows/x64/runner/Release/`

### 5. Build for Web POS
```bash
flutter build web --base-href /pos-web/ --release
```
Copy the contents of `build/web/` to your server's `public/pos-web/` directory.

---

## 🖨️ Hardware & Thermal Printers Supported

- **Direct ESC/POS 80mm & 58mm**: Thermal receipt printers (USB, Network/LAN, Bluetooth).
- **Barcode & 2D Scanners**: USB HID keyboard emulation & Bluetooth wireless scanners.
- **Cash Drawers**: RJ11 / RJ12 cash drawer kick-out via receipt printer.
"""
with open(os.path.join(flutter_dir, "README.md"), "w") as f:
    f.write(flutter_readme)

# ------------------------------------------------------------------------------
# STEP 3: 03_Database_SQL
# ------------------------------------------------------------------------------
print("\n[3/6] Preparing 03_Database_SQL...")
db_dir = os.path.join(STAGING_DIR, "03_Database_SQL")
os.makedirs(db_dir, exist_ok=True)

if os.path.exists(clean_sql_src):
    shutil.copy2(clean_sql_src, os.path.join(db_dir, "database.sql"))
    print("  -> database.sql copied successfully.")

db_readme = """Zoom Sales CRM & Inventory — Database Setup Guide (v1.0.6)
===========================================================

This folder contains the complete, clean MySQL/MariaDB database dump (`database.sql`).
Includes: Clean MySQL database schema, Retail & Restaurant/Cafe POS Modules Built-in, Floorplans, KOT, and SuperAdmin pre-seeded.

HOW TO IMPORT:

Method A: Via phpMyAdmin (cPanel / DirectAdmin / CloudPanel)
------------------------------------------------------------
1. Create a new MySQL Database in your hosting control panel (e.g. `yourprefix_zoompos`).
2. Create a MySQL user and grant ALL PRIVILEGES to the database.
3. Open phpMyAdmin, select your newly created database.
4. Click the 'Import' tab at the top.
5. Select `database.sql` and click 'Go' / 'Import'.
6. In your Laravel backend `.env` file, configure your DB credentials:
   DB_DATABASE=your_database_name
   DB_USERNAME=your_database_user
   DB_PASSWORD=your_database_password

Method B: Via MySQL Command Line (VPS / Terminal)
-------------------------------------------------
mysql -u your_username -p your_database_name < database.sql

DEFAULT CREDENTIALS INCLUDED:
-----------------------------
1. SuperAdmin Portal (`https://yourdomain.com/login`):
   Email:    admin@zoompos.com
   Password: admin1234

2. Demo Store Admin (`https://yourdomain.com/store/login`):
   Email:    store@demo.com
   Password: admin1234

3. Demo Cashier:
   Email:    cashier@demo.com
   Password: admin1234

NOTE: Change these passwords immediately upon production deployment in the Admin panel!
"""
with open(os.path.join(db_dir, "README.txt"), "w") as f:
    f.write(db_readme)

# ------------------------------------------------------------------------------
# STEP 4: 04_Documentation
# ------------------------------------------------------------------------------
print("\n[4/6] Preparing 04_Documentation...")
doc_dir = os.path.join(STAGING_DIR, "04_Documentation")
os.makedirs(doc_dir, exist_ok=True)

doc_src = os.path.join(ROOT_DIR, "public", "documentation")
for item in [
    "index.html",
    "README.md",
    "Zoom_Sales_CRM_Feature_Guide.md",
    "APP_BUILDER_GUIDE.md",
    "NOTIFICATION_AND_WHATSAPP_INTEGRATION_GUIDE.md",
]:
    s = os.path.join(doc_src, item)
    if os.path.exists(s):
        shutil.copy2(s, os.path.join(doc_dir, item))

if os.path.exists(os.path.join(doc_src, "images")):
    shutil.copytree(os.path.join(doc_src, "images"), os.path.join(doc_dir, "images"), dirs_exist_ok=True, ignore=shutil.ignore_patterns("*.zip"))
    print("  -> Offline documentation portal & architecture diagrams copied.")

# Bundle Flutter POS Mobile/Desktop Source and Documentation directly inside Laravel Installable
print("\n  -> Bundling flutter_pos_source/ directly inside 01_Laravel_Backend_Web_Source...")
shutil.copytree(flutter_dir, os.path.join(backend_dir, "flutter_pos_source"), dirs_exist_ok=True)

print("  -> Bundling documentation/ directly inside 01_Laravel_Backend_Web_Source...")
shutil.copytree(doc_dir, os.path.join(backend_dir, "documentation"), dirs_exist_ok=True)

# ------------------------------------------------------------------------------
# STEP 5: 05_Marketplace_Assets_and_Banners
# ------------------------------------------------------------------------------
print("\n[5/6] Preparing 05_Marketplace_Assets_and_Banners...")
mkt_dir = os.path.join(STAGING_DIR, "05_Marketplace_Assets_and_Banners")
banners_dir = os.path.join(mkt_dir, "Banners")
os.makedirs(banners_dir, exist_ok=True)

# Copy description and listing guides
desc_src = os.path.join(ROOT_DIR, "public", "marketing", "PRODUCT_DESCRIPTION.html")
if os.path.exists(desc_src):
    shutil.copy2(desc_src, os.path.join(mkt_dir, "CODECANYON_DESCRIPTION.html"))
    shutil.copy2(desc_src, os.path.join(STAGING_DIR, "CODECANYON_DESCRIPTION.html"))

guide_src = os.path.join(ROOT_DIR, "public", "marketing", "MARKETPLACE_LISTING_GUIDE.md")
if os.path.exists(guide_src):
    shutil.copy2(guide_src, os.path.join(mkt_dir, "MARKETPLACE_LISTING_GUIDE.md"))
    shutil.copy2(guide_src, os.path.join(STAGING_DIR, "MARKETPLACE_LISTING_GUIDE.md"))

# Comprehensive plain text marketplace listing metadata
listing_metadata = """================================================================================
CODECANYON ITEM SUBMISSION METADATA & MARKETPLACE CONTENT (v1.0.6)
================================================================================

ITEM TITLE (Max 100 characters):
ZooM Sales CRM & Inventory - Multi-Tenant SaaS POS, ERP Platform with Flutter App (Android & Windows)

SHORT DESCRIPTION / SEARCH SNIPPET:
Complete Multi-Tenant Sales CRM, POS & Inventory SaaS platform with Retail POS and Restaurant (KDS & KOT) modules. Includes cross-platform Flutter app for Android & Windows Desktop with 100% offline sync, automated subscription billing, and 1-click web installer.

PRIMARY CATEGORY:
PHP Scripts > Point of Sale (or Project Management Tools / Miscellaneous)

COMPATIBILITY:
- PHP 8.2.x, PHP 8.3.x
- Laravel 11.x
- MySQL 5.7+, MySQL 8.0+, MariaDB 10.4+
- Flutter 3.22+, Dart 3.4+
- All Modern Browsers (Chrome, Firefox, Safari, Edge, Opera)

30 SEARCH TAGS:
pos saas, point of sale, multi tenant pos, restaurant pos, retail pos, flutter pos, flutter app, android pos, windows pos, offline pos, crm pos, laravel pos, erp saas, barcode scanner, thermal printer, kot pos, kitchen display system, multi store inventory, subscription billing, stripe payment, razorpay, whitelabel saas, cloud pos, billing software, cashier app, weighing scale, inventory management, superadmin saas, store management, table ordering

PRICING RECOMMENDATION:
- Regular License: $49.00 - $69.00
- Extended License: $299.00 - $499.00

LIVE DEMO CREDENTIALS:
1. SuperAdmin SaaS Management Panel:
   URL:      https://saas.zoomnearby.com/login
   Email:    admin@zoomnearby.com
   Password: password

2. Tenant Store Owner & POS:
   URL:      https://saas.zoomnearby.com/store/login
   Email:    store@zoomnearby.com
   Password: password

3. Native Android Mobile App (APK):
   Download: https://saas.zoomnearby.com/zoom-pos-v1.0.2.apk

4. Windows Desktop POS (.exe):
   Download: https://saas.zoomnearby.com/zoom-sales-crm-software-1.0.2.exe

5. Documentation Portal:
   URL:      https://saas.zoomnearby.com/documentation
"""
with open(os.path.join(mkt_dir, "MARKETPLACE_LISTING_METADATA.txt"), "w") as f:
    f.write(listing_metadata)

changelog_content = """# Changelog & Release Notes

## Version 1.0.6 (Latest Release)
- **[ENHANCEMENT] Dual Core POS Modules:** Streamlined Retail POS (barcode scanning, weighing scales, registers) and Restaurant POS (table floorplan, KDS, KOT, and table QR).
- **[ENHANCEMENT] Cross-Platform POS App:** Flutter Android APK and Windows 64-bit desktop builds with direct ESC/POS thermal printing.
- **[ENHANCEMENT] SDUI Live Sync:** Real-time form refresh and debounced customer search on handheld POS terminals.
- **[UPDATE] Database & Performance:** Clean schema and complete database dump with zero-downtime execution.
- **[FEATURE] 1-Click Interactive Web Installer:** Automated installer wizard at `/install` with database migration and SuperAdmin onboarding.

## Version 1.0.5
- Cafe & Restaurant module with visual floor table plans, KDS, and KOT printing.
- Dual-tax fiscal receipt printing engine (80mm/58mm).
- Automated database migration & web installer at `/install`.
"""
with open(os.path.join(mkt_dir, "CHANGELOG.md"), "w") as f:
    f.write(changelog_content)

# Copy Banner Assets
banner_mappings = [
    ("public/documentation/images/preview_590x300.jpg", "590x300_Item_Preview.jpg"),
    ("public/documentation/images/thumbnail_80x80.jpg", "80x80_Thumbnail.jpg"),
    ("public/documentation/images/thumbnail_80x80.jpg", "80x80_Thumbnail.png"),
    ("public/documentation/images/promotional-banner-1500x2500.jpg", "1500x2500_Promotional_Feature_Banner.jpg"),
    ("public/documentation/images/promotional-banner-2000x10000.jpg", "2000x10000_Master_Feature_Tour_Banner.jpg"),
    ("public/documentation/images/promotional-banner-1920x800-detailed.jpg", "1920x800_Hero_Promo_Banner.jpg"),
    ("public/documentation/images/product-image.jpg", "Product_Showcase_1376x768.jpg"),
]

for src_rel, dst_name in banner_mappings:
    s = os.path.join(ROOT_DIR, src_rel)
    if os.path.exists(s):
        shutil.copy2(s, os.path.join(banners_dir, dst_name))
        print(f"  -> Banner copied: {dst_name}")

# Also copy screenshots if available
src_screenshots = os.path.join(ROOT_DIR, "public/marketing/assets/images")
if os.path.exists(src_screenshots):
    dst_screenshots = os.path.join(mkt_dir, "Screenshots")
    os.makedirs(dst_screenshots, exist_ok=True)
    for sc in os.listdir(src_screenshots):
        if sc.endswith(".png") or sc.endswith(".jpg"):
            shutil.copy2(os.path.join(src_screenshots, sc), os.path.join(dst_screenshots, sc))
    print("  -> Product screenshots copied.")

# Root Quick Start Guide
quick_start_guide = """# Zoom Sales CRM & Inventory — CodeCanyon Product Package (v1.0.6)

Thank you for choosing **Zoom Sales CRM & Inventory**!

## 📁 Package Structure

| Directory / File | Content Description |
| :--- | :--- |
| `01_Laravel_Backend_Web_Source/` | Complete Laravel 11 SaaS Web Platform Source Code (Retail POS & Restaurant/Cafe POS Built-in, SuperAdmin, Store Admin, Storefront, APIs) |
| `02_Flutter_POS_Mobile_Desktop_Source/` | Flutter 3.22+ Cross-Platform Source Code (Android APK, Windows Desktop, Web PWA) with offline sync & multi-language support |
| `03_Database_SQL/` | Clean MySQL Database Dump (`database.sql`) with all 130 tables + Import Instructions |
| `04_Documentation/` | Full Offline Interactive Documentation Portal (`index.html`), Setup Guides, API reference & Diagrams |
| `05_Marketplace_Assets_and_Banners/` | Official CodeCanyon 590x300 preview, 80x80 thumbnail, 1500x2500 & 2000x10000 high-res infographic banners, description HTML & tags |
| `CODECANYON_DESCRIPTION.html` | Ready-to-use HTML Product Description for CodeCanyon item page |
| `MARKETPLACE_LISTING_GUIDE.md` | Category, tags, pricing, and submission metadata guide |
| `QUICK_START_GUIDE.md` | Fast deployment summary |

---

## ⚡ Quick Deployment Steps

1. **Deploy Web Backend**:
   - Upload `01_Laravel_Backend_Web_Source` to your server.
   - Point your web server root to the `public/` directory.
   - Visit `https://yourdomain.com/install` or import `03_Database_SQL/database.sql`.
2. **Access SuperAdmin Portal**:
   - URL: `https://yourdomain.com/login`
   - Default Email: `admin@zoompos.com`
   - Default Password: `admin1234`
3. **Build or Configure Flutter POS**:
   - Open `02_Flutter_POS_Mobile_Desktop_Source/lib/core/config/app_config.dart`.
   - Update `defaultBaseUrl` to your web domain.
   - Compile for Android (`flutter build apk`) or Windows (`flutter build windows`).

For complete detailed steps, open `04_Documentation/index.html` in your browser.
"""
with open(os.path.join(STAGING_DIR, "QUICK_START_GUIDE.md"), "w") as f:
    f.write(quick_start_guide)
with open(os.path.join(STAGING_DIR, "README.md"), "w") as f:
    f.write(quick_start_guide)

# ------------------------------------------------------------------------------
# STEP 6: Strict Verification & Clean Permissions
# ------------------------------------------------------------------------------
print("\n[6/6] Verifying strict exclusions & setting uniform permissions...")

forbidden_paths = [
    "lic",
    "public/lic",
    "public/marketing",
    "public/app-builder",
    "app-builder",
    "module-packages",
    ".git",
    ".dart_tool",
    "read",
]

violations = []
for root, dirs, files in os.walk(STAGING_DIR):
    rel_dir = os.path.relpath(root, STAGING_DIR)
    for d in dirs:
        full_rel = os.path.normpath(os.path.join(rel_dir, d))
        for forbidden in forbidden_paths:
            if full_rel == forbidden or full_rel.endswith(os.sep + forbidden) or (os.sep + forbidden + os.sep) in full_rel:
                violations.append(f"Forbidden Directory: {full_rel}")
        if full_rel == "scripts" or full_rel == "01_Laravel_Backend_Web_Source" + os.sep + "scripts":
            violations.append(f"Forbidden Directory: {full_rel}")
    for f in files:
        full_rel = os.path.normpath(os.path.join(rel_dir, f))
        if f.endswith(".apk") or (f.endswith(".exe") and not full_rel.startswith("01_Laravel_Backend_Web_Source" + os.sep + "vendor" + os.sep) and not "win_ble" in full_rel):
            violations.append(f"Forbidden binary: {full_rel}")
        if f.endswith(".zip"):
            violations.append(f"Forbidden zip archive: {full_rel}")
        if f == ".env":
            violations.append(f"Forbidden .env file: {full_rel}")
        for forbidden in forbidden_paths:
            if full_rel.startswith(forbidden + os.sep) or full_rel == forbidden or (os.sep + forbidden + os.sep) in full_rel:
                violations.append(f"Forbidden file in {forbidden}: {full_rel}")
        if f.endswith((".php", ".example", ".env", ".json", ".md", ".txt")) and "vendor" not in full_rel:
            fpath = os.path.join(root, f)
            try:
                with open(fpath, "r", errors="ignore") as tf:
                    tcontent = tf.read()
                    if "OPENAI_API_KEY" in tcontent or "GEMINI_API_KEY" in tcontent:
                        violations.append(f"Forbidden AI Key Reference in {full_rel}")
            except Exception:
                pass

if violations:
    print(f"ERROR: Found {len(violations)} forbidden items:")
    for v in violations[:15]:
        print(f"  - {v}")
    sys.exit(1)
else:
    print("  -> Zero sensitive or forbidden items found. Cleanliness check passed!")

# Fix permissions
print("  -> Setting directory permissions to 0755 and files to 0644...")
for root, dirs, files in os.walk(STAGING_DIR):
    for d in dirs:
        os.chmod(os.path.join(root, d), 0o755)
    for f in files:
        fpath = os.path.join(root, f)
        if f == "artisan":
            os.chmod(fpath, 0o755)
        else:
            os.chmod(fpath, 0o644)

# ------------------------------------------------------------------------------
# STEP 7: Build Final ZIP Packages & Standalone Archives
# ------------------------------------------------------------------------------
print("\nCreating final CodeCanyon release archives...")

# 1. Standalone Marketplace Assets ZIP
print(f"Building standalone Marketplace Assets ZIP: {MARKETPLACE_ZIP}...")
if os.path.exists(MARKETPLACE_ZIP):
    os.remove(MARKETPLACE_ZIP)
subprocess.run(["zip", "-r", "-q", "-9", MARKETPLACE_ZIP, "."], cwd=mkt_dir, check=True)
os.chmod(MARKETPLACE_ZIP, 0o644)
print(f"  -> Marketplace Assets ZIP: {os.path.getsize(MARKETPLACE_ZIP) / (1024*1024):.2f} MB")

# 2. Standalone Clean Laravel Installable ZIP
print(f"Building standalone Clean Laravel Installable ZIP: {LARAVEL_INSTALLABLE_ZIP}...")
for lz in [LARAVEL_INSTALLABLE_ZIP, LARAVEL_INSTALLABLE_ALIAS]:
    if os.path.exists(lz):
        os.remove(lz)
subprocess.run(["zip", "-r", "-q", "-9", LARAVEL_INSTALLABLE_ZIP, "."], cwd=backend_dir, check=True)
os.chmod(LARAVEL_INSTALLABLE_ZIP, 0o644)
shutil.copy2(LARAVEL_INSTALLABLE_ZIP, LARAVEL_INSTALLABLE_ALIAS)
os.chmod(LARAVEL_INSTALLABLE_ALIAS, 0o644)
laravel_size_mb = os.path.getsize(LARAVEL_INSTALLABLE_ZIP) / (1024 * 1024)
print(f"  -> Laravel Installable ZIP: {laravel_size_mb:.2f} MB")

# 3. Standalone Flutter Source ZIP
print(f"Building standalone Flutter POS Source ZIP: {FLUTTER_SOURCE_ZIP}...")
for fz in [FLUTTER_SOURCE_ZIP, FLUTTER_SOURCE_ALIAS]:
    if os.path.exists(fz):
        os.remove(fz)
subprocess.run(["zip", "-r", "-q", "-9", FLUTTER_SOURCE_ZIP, "."], cwd=flutter_dir, check=True)
os.chmod(FLUTTER_SOURCE_ZIP, 0o644)
shutil.copy2(FLUTTER_SOURCE_ZIP, FLUTTER_SOURCE_ALIAS)
os.chmod(FLUTTER_SOURCE_ALIAS, 0o644)
flutter_size_mb = os.path.getsize(FLUTTER_SOURCE_ZIP) / (1024 * 1024)
print(f"  -> Flutter Source ZIP: {flutter_size_mb:.2f} MB")

# 4. Standalone Documentation ZIP
print(f"Building standalone Documentation ZIP: {DOCUMENTATION_ZIP}...")
for dz in [DOCUMENTATION_ZIP, DOCUMENTATION_ALIAS]:
    if os.path.exists(dz):
        os.remove(dz)
subprocess.run(["zip", "-r", "-q", "-9", DOCUMENTATION_ZIP, "."], cwd=doc_dir, check=True)
os.chmod(DOCUMENTATION_ZIP, 0o644)
shutil.copy2(DOCUMENTATION_ZIP, DOCUMENTATION_ALIAS)
os.chmod(DOCUMENTATION_ALIAS, 0o644)
doc_size_mb = os.path.getsize(DOCUMENTATION_ZIP) / (1024 * 1024)
print(f"  -> Documentation ZIP: {doc_size_mb:.2f} MB")

# 5. Master Full CodeCanyon Package
print(f"Building Main Master CodeCanyon Release ZIP: {PRIMARY_ZIP}...")
for z in [PRIMARY_ZIP, ALIAS_ZIP_1, ALIAS_ZIP_2, ALIAS_ZIP_3]:
    if os.path.exists(z):
        os.remove(z)

start_time = time.time()
subprocess.run(["zip", "-r", "-q", "-9", PRIMARY_ZIP, "."], cwd=STAGING_DIR, check=True)
elapsed = time.time() - start_time
size_mb = os.path.getsize(PRIMARY_ZIP) / (1024 * 1024)
os.chmod(PRIMARY_ZIP, 0o644)
print(f"  -> Successfully created {PRIMARY_ZIP} ({size_mb:.2f} MB) in {elapsed:.1f}s.")

# Copy aliases
for alias in [ALIAS_ZIP_1, ALIAS_ZIP_2, ALIAS_ZIP_3]:
    shutil.copy2(PRIMARY_ZIP, alias)
    os.chmod(alias, 0o644)
    print(f"  -> Created alias: {os.path.basename(alias)}")

# Calculate MD5 checksum
md5_hash = hashlib.md5()
with open(PRIMARY_ZIP, "rb") as f:
    for chunk in iter(lambda: f.read(4096), b""):
        md5_hash.update(chunk)
md5_digest = md5_hash.hexdigest()

# Verify integrity with unzip -t
print("\nVerifying archive integrity with unzip -t...")
test_res = subprocess.run(["unzip", "-t", PRIMARY_ZIP], capture_output=True, text=True)
if test_res.returncode == 0:
    print("Master Archive integrity verified: 100% OK!")
else:
    print(f"Master Archive test failed: {test_res.stderr}")
    sys.exit(1)

test_laravel = subprocess.run(["unzip", "-t", LARAVEL_INSTALLABLE_ZIP], capture_output=True, text=True)
if test_laravel.returncode == 0:
    print("Laravel Installable integrity verified: 100% OK!")
else:
    print(f"Laravel Installable test failed: {test_laravel.stderr}")
    sys.exit(1)

print("\n================================================================================")
print("  CODECANYON SUBMISSION PACKAGE BUILD SUMMARY                                    ")
print("================================================================================")
print(f"Master Release Package:   {PRIMARY_ZIP} ({size_mb:.2f} MB)")
print(f"Download URL:             https://saas.zoomnearby.com/{os.path.basename(PRIMARY_ZIP)}")
print(f"Alias URL:                https://saas.zoomnearby.com/{os.path.basename(ALIAS_ZIP_1)}")
print(f"Laravel Installable URL:  https://saas.zoomnearby.com/{os.path.basename(LARAVEL_INSTALLABLE_ZIP)} ({laravel_size_mb:.2f} MB)")
print(f"Flutter Source Code URL:  https://saas.zoomnearby.com/{os.path.basename(FLUTTER_SOURCE_ZIP)} ({flutter_size_mb:.2f} MB)")
print(f"Documentation Portal URL: https://saas.zoomnearby.com/{os.path.basename(DOCUMENTATION_ZIP)} ({doc_size_mb:.2f} MB)")
print(f"Marketplace Assets URL:   https://saas.zoomnearby.com/{os.path.basename(MARKETPLACE_ZIP)}")
print(f"Master Package MD5:       {md5_digest}")
print("================================================================================")
