#!/usr/bin/env python3
import os
import sys
import shutil
import zipfile
import subprocess
import time

ROOT_DIR = "/home/zoomnearby-saas/htdocs/saas.zoomnearby.com"
SCRATCH_DIR = "/home/zoomnearby-saas/.gemini/antigravity-cli/brain/60c1ea18-3a7b-4d1b-9ab0-5e2a9926ab91/scratch"
STAGING_DIR = os.path.join(SCRATCH_DIR, "clean_publishable_pos_package")
PRIMARY_ZIP = os.path.join(ROOT_DIR, "public", "zoom-pos-system.zip")
ALIAS_ZIP_1 = os.path.join(ROOT_DIR, "public", "zoom-sales-pos-saas.zip")
ALIAS_ZIP_2 = os.path.join(ROOT_DIR, "public", "zoom-pos-platform.zip")
ALIAS_ZIP_3 = os.path.join(ROOT_DIR, "public", "laravel-installable.zip")
ALIAS_ZIP_4 = os.path.join(ROOT_DIR, "public", "zoom-sales-crm-laravel-installable.zip")
ALIAS_ZIP_5 = os.path.join(ROOT_DIR, "public", "zoom-sales-crm-codecanyon.zip")
ALL_OUTPUT_ZIPS = [PRIMARY_ZIP, ALIAS_ZIP_1, ALIAS_ZIP_2, ALIAS_ZIP_3, ALIAS_ZIP_4, ALIAS_ZIP_5]

print("=== Building Clean Publishable POS Package ===")

if os.path.exists(STAGING_DIR):
    print(f"Cleaning existing staging directory {STAGING_DIR}...")
    shutil.rmtree(STAGING_DIR)

os.makedirs(STAGING_DIR, exist_ok=True)

# -------------------------------------------------------------
# 1. 01_Laravel_Backend_Web_Source
# -------------------------------------------------------------
print("\n[1/5] Preparing 01_Laravel_Backend_Web_Source...")
backend_dir = os.path.join(STAGING_DIR, "01_Laravel_Backend_Web_Source")
os.makedirs(backend_dir, exist_ok=True)

core_dirs = [
    "app",
    "config",
    "database",
    "lang",
    "module-packages",
    "resources",
    "routes",
    "tests",
    "vendor",
]

for item in core_dirs:
    src = os.path.join(ROOT_DIR, item)
    dst = os.path.join(backend_dir, item)
    if os.path.exists(src):
        print(f"  Copying {item}...")
        shutil.copytree(src, dst, symlinks=True, ignore=shutil.ignore_patterns(
            ".git*", "__pycache__", "*.pyc", "*.log", "*.sqlite3", ".phpunit.result.cache", "*.zip"
        ))

# Modules folder (clean destination for active modules)
print("  Setting up clean modules/...")
os.makedirs(os.path.join(backend_dir, "modules"), exist_ok=True)
with open(os.path.join(backend_dir, "modules", ".gitkeep"), "w") as f:
    pass
with open(os.path.join(backend_dir, "modules", ".htaccess"), "w") as f:
    f.write("Deny from all\n")

# Bootstrap folder
print("  Setting up clean bootstrap/...")
os.makedirs(os.path.join(backend_dir, "bootstrap", "cache"), exist_ok=True)
for bf in ["app.php", "providers.php"]:
    src_f = os.path.join(ROOT_DIR, "bootstrap", bf)
    if os.path.exists(src_f):
        shutil.copy2(src_f, os.path.join(backend_dir, "bootstrap", bf))
with open(os.path.join(backend_dir, "bootstrap", "cache", ".gitignore"), "w") as f:
    f.write("*\n!.gitignore\n")

# Storage folder
print("  Setting up clean storage/...")
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
print("  Setting up clean public/ in backend...")
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

src_pos_web = os.path.join(ROOT_DIR, "public", "pos-web")
if os.path.exists(src_pos_web):
    print("  Copying public/pos-web/ (clean Flutter Web POS client)...")
    shutil.copytree(
        src_pos_web,
        os.path.join(dst_public, "pos-web"),
        symlinks=True,
        ignore=shutil.ignore_patterns("*.zip", "release-output", "build")
    )

# Root configuration files
root_files = [
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

# Clean .env.example with DEMO_MODE=false and clean placeholders
src_env_example = os.path.join(ROOT_DIR, ".env.example")
if os.path.exists(src_env_example):
    with open(src_env_example, "r") as f:
        env_content = f.read()
    env_content = env_content.replace("DEMO_MODE=true", "DEMO_MODE=false")
    # Clean app key to encourage php artisan key:generate
    env_content = env_content.replace("APP_KEY=base64:1oDNYMXOaW8NhkdlqHXkEIrktd7dv579AIovWD/v294=", "APP_KEY=")
    with open(os.path.join(backend_dir, ".env.example"), "w") as f:
        f.write(env_content)

# Clean backend README and INSTALL
backend_readme = """# Zoom Sales CRM & Inventory — Multi-Tenant SaaS Web Platform

Welcome to **Zoom Sales CRM & Inventory**, an enterprise-grade multi-tenant SaaS point-of-sale and business management platform.

This package contains the complete, production-ready **Laravel Backend Server** including the **two built-in core modules**:
1. 🛒 **Retail POS**: Barcode scanning, fast cart checkout, quotations, receipt printing, customer ledgers, and multi-store inventory.
2. 🍽️ **Cafe & Restaurant POS**: Dining tables floor plan, Kitchen Order Tickets (KOT) printing, Kitchen Display System (KDS), and waiter ordering.

---

## 📋 System Requirements

- **PHP**: 8.2 or 8.3
- **PHP Extensions Required**:
  - `BCMath`, `Ctype`, `cURL`, `DOM`, `Fileinfo`, `Filter`, `Hash`, `Intl`, `JSON`, `Mbstring`, `OpenSSL`, `PCRE`, `PDO`, `PDO_MySQL`, `Session`, `Tokenizer`, `XML`, `ZIP`
- **Database**: MySQL 5.7+ / 8.0+ or MariaDB 10.3+
- **Web Server**: Apache (`mod_rewrite` enabled) or Nginx
- **HTTPS / SSL**: Recommended for production

---

## 🚀 Installation Instructions

### Option 1: 1-Click Interactive Web Installer (Recommended)
1. Extract all files into your web root directory (e.g. `public_html` or `/var/www/zoompos`).
2. Point your web server document root to the `public/` directory.
3. Open your browser and navigate to:
   ```
   https://yourdomain.com/install
   ```
4. Follow the interactive 5-step setup wizard:
   - **Step 1 - Requirements**: Automatic PHP version and extension verification.
   - **Step 2 - Environment**: Database host, database name, username, and password.
   - **Step 3 - Database Migration**: Automatic table creation and seeding.
   - **Step 4 - SuperAdmin Account**: Set your administrator email and password.
   - **Step 5 - Finish**: Generates application key and configures storage.

### Option 2: Fast Manual Installation via database.sql
1. Extract all files to your web server root.
2. Point your web server document root to the `public/` directory.
3. Create a clean MySQL database (e.g. `zoom_pos`) in cPanel or MySQL console.
4. Import the provided `database.sql` from `03_Database_SQL/database.sql` via phpMyAdmin or terminal:
   ```bash
   mysql -u your_user -p your_database < database.sql
   ```
5. Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```
6. Update your database connection in `.env`:
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
7. Generate an encryption key:
   ```bash
   php artisan key:generate
   ```
8. Link public storage:
   ```bash
   php artisan storage:link
   ```
9. Set directory permissions:
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

# Also copy database.sql into backend database/ folder for convenience
clean_sql_src = os.path.join(ROOT_DIR, "public", "zoom-sales-crm-database-clean.sql")
if os.path.exists(clean_sql_src):
    shutil.copy2(clean_sql_src, os.path.join(backend_dir, "database", "database.sql"))

# -------------------------------------------------------------
# 2. 02_Flutter_POS_Mobile_Desktop_Source
# -------------------------------------------------------------
print("\n[2/5] Preparing 02_Flutter_POS_Mobile_Desktop_Source...")
flutter_dir = os.path.join(STAGING_DIR, "02_Flutter_POS_Mobile_Desktop_Source")
os.makedirs(flutter_dir, exist_ok=True)
mobile_src = os.path.join(ROOT_DIR, "mobile")

flutter_subdirs = ["android", "windows", "web", "lib", "test", "assets"]
for fsub in flutter_subdirs:
    s = os.path.join(mobile_src, fsub)
    d = os.path.join(flutter_dir, fsub)
    if os.path.exists(s):
        print(f"  Copying {fsub}...")
        shutil.copytree(s, d, symlinks=True, ignore=shutil.ignore_patterns(
            ".git*", ".dart_tool*", "ephemeral", ".gradle", "local.properties", "*.log", "build", "*.jks"
        ))

flutter_files = ["pubspec.yaml", "pubspec.lock", "analysis_options.yaml", "codemagic.yaml", "installer.iss", "installer_info.txt"]
for ff in flutter_files:
    s = os.path.join(mobile_src, ff)
    if os.path.exists(s):
        shutil.copy2(s, os.path.join(flutter_dir, ff))

flutter_readme = """# Zoom POS — Flutter Cross-Platform Client (Android, Windows Desktop & Web)

This folder contains the complete source code for the **Zoom POS** client application built with **Flutter 3.22+**.

It supports:
- 📱 **Android**: Handheld wireless POS terminals, tablets, and smartphones.
- 🪟 **Windows**: 64-bit native desktop application with ESC/POS thermal printer support.
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

# -------------------------------------------------------------
# 3. 03_Database_SQL
# -------------------------------------------------------------
print("\n[3/5] Preparing 03_Database_SQL...")
db_dir = os.path.join(STAGING_DIR, "03_Database_SQL")
os.makedirs(db_dir, exist_ok=True)

if os.path.exists(clean_sql_src):
    shutil.copy2(clean_sql_src, os.path.join(db_dir, "database.sql"))

db_readme = """Zoom Sales CRM & Inventory — Database Setup Guide
===========================================================

This folder contains the complete, clean MySQL/MariaDB database dump (`database.sql`).
Includes: Retail & Restaurant/Cafe Modules, Default Tables, Floorplans, KOT, and SuperAdmin pre-seeded.

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

# -------------------------------------------------------------
# 4. 04_Documentation & Root Guide Files
# -------------------------------------------------------------
print("\n[4/5] Preparing 04_Documentation & Root Guide Files...")
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

# Product Description HTML (Clean, marketplace-agnostic)
desc_src = os.path.join(doc_src, "CODECANYON_DESCRIPTION.html")
if os.path.exists(desc_src):
    shutil.copy2(desc_src, os.path.join(STAGING_DIR, "PRODUCT_DESCRIPTION.html"))

# Marketplace Listing Guide (Clean, generic)
guide_src = os.path.join(doc_src, "CODECANYON_LISTING_GUIDE.md")
if os.path.exists(guide_src):
    shutil.copy2(guide_src, os.path.join(STAGING_DIR, "MARKETPLACE_LISTING_GUIDE.md"))

quick_start_guide = """# Zoom POS & Business Management Platform — Complete Product Package

Welcome to **Zoom POS & Business Management Platform**!

## 📁 Package Structure

| Directory | Content Description |
| :--- | :--- |
| `01_Laravel_Backend_Web_Source/` | Complete Laravel 11 SaaS Web Platform Source Code (Retail & Restaurant/Cafe Built-in, SuperAdmin, Store Admin, Storefront, APIs) |
| `02_Flutter_POS_Mobile_Desktop_Source/` | Flutter 3.22+ Cross-Platform Source Code (Android APK, Windows Desktop, Web PWA) with offline sync & multi-language support |
| `03_Database_SQL/` | Clean MySQL Database Dump (`database.sql`) + Import Instructions |
| `04_Documentation/` | Full Offline Interactive Documentation Portal (`index.html`), Setup Guides, API reference & Diagrams |
| `PRODUCT_DESCRIPTION.html` | Ready-to-use HTML Product Description for marketplace item page |
| `MARKETPLACE_LISTING_GUIDE.md` | Category, tags, pricing, and submission metadata guide |

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

# -------------------------------------------------------------
# 5. Strict Exclusion Verification
# -------------------------------------------------------------
print("\n[5/5] Verifying strict exclusions...")

forbidden_paths = [
    "lic",
    "public/lic",
    "public/marketing",
    "public/app-builder",
    "app-builder",
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
                violations.append(f"Directory: {full_rel}")
        # Top-level scripts folder
        if full_rel == "scripts" or full_rel == "01_Laravel_Backend_Web_Source" + os.sep + "scripts":
            violations.append(f"Directory: {full_rel}")
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

if violations:
    print(f"ERROR: Found {len(violations)} forbidden items in staging directory:")
    for v in violations[:15]:
        print(f"  - {v}")
    sys.exit(1)
else:
    print("  Exclusion verification passed! Zero forbidden license, app-builder, marketing, or sensitive items found.")

# -------------------------------------------------------------
# 6. Build Final ZIP Archive & Aliases
# -------------------------------------------------------------
print("\nCreating final ZIP archive...")
print(f"Target: {PRIMARY_ZIP}")

for z in ALL_OUTPUT_ZIPS:
    if os.path.exists(z):
        os.remove(z)

start_time = time.time()
zip_cmd = ["zip", "-r", "-q", "-9", PRIMARY_ZIP, "."]
res = subprocess.run(zip_cmd, cwd=STAGING_DIR)

if res.returncode != 0:
    print(f"Error executing zip: {res.stderr}")
    sys.exit(1)

elapsed = time.time() - start_time
size_bytes = os.path.getsize(PRIMARY_ZIP)
size_mb = size_bytes / (1024 * 1024)

print(f"Successfully created {PRIMARY_ZIP} ({size_mb:.2f} MB) in {elapsed:.1f}s.")

# Copy all aliases
os.chmod(PRIMARY_ZIP, 0o644)
for alias in ALL_OUTPUT_ZIPS:
    if alias != PRIMARY_ZIP:
        shutil.copy2(PRIMARY_ZIP, alias)
        os.chmod(alias, 0o644)
        print(f"Created alias {alias} (0644).")

# Verification
print("\nVerifying archive integrity with unzip -t...")
test_res = subprocess.run(["unzip", "-t", PRIMARY_ZIP], capture_output=True, text=True)
if test_res.returncode == 0:
    print("Archive integrity verified: OK!")
else:
    print(f"Archive test failed: {test_res.stderr}")
    sys.exit(1)

print("\n=== Clean Publishable POS Package Build Complete! ===")
