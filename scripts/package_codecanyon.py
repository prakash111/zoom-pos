#!/usr/bin/env python3
import os
import sys
import shutil
import zipfile
import subprocess
import time

ROOT_DIR = "/home/zoomnearby-saas/htdocs/saas.zoomnearby.com"
SCRATCH_DIR = "/home/zoomnearby-saas/.gemini/antigravity-cli/brain/c55ae524-66e2-4ccb-8289-c3afb8d9b1c3/scratch"
STAGING_DIR = os.path.join(SCRATCH_DIR, "codecanyon_package_v105")
OUTPUT_ZIP = os.path.join(ROOT_DIR, "public", "Zoom-Sales-CRM-POS-v1.0.5-Codecanyon-Package.zip")
ALIAS_ZIP = os.path.join(ROOT_DIR, "public", "zoom-sales-crm-codecanyon.zip")

print("=== Starting CodeCanyon Package Build (v1.0.5) ===")

if os.path.exists(STAGING_DIR):
    print(f"Cleaning existing staging directory {STAGING_DIR}...")
    shutil.rmtree(STAGING_DIR)

os.makedirs(STAGING_DIR, exist_ok=True)

# -------------------------------------------------------------
# 1. 01_Laravel_Backend_Web_Source
# -------------------------------------------------------------
print("\n[1/4] Preparing 01_Laravel_Backend_Web_Source...")
backend_dir = os.path.join(STAGING_DIR, "01_Laravel_Backend_Web_Source")
os.makedirs(backend_dir, exist_ok=True)

backend_copies = [
    "app",
    "config",
    "database",
    "lang",
    "resources",
    "routes",
    "tests",
    "vendor",
]

for item in backend_copies:
    src = os.path.join(ROOT_DIR, item)
    dst = os.path.join(backend_dir, item)
    print(f"  Copying {item}...")
    shutil.copytree(src, dst, symlinks=True, ignore=shutil.ignore_patterns(
        ".git*", "__pycache__", "*.pyc", "*.log", "*.sqlite3"
    ))

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

# Fonts if present
src_fonts = os.path.join(ROOT_DIR, "storage", "fonts")
if os.path.exists(src_fonts):
    shutil.copytree(src_fonts, os.path.join(backend_dir, "storage", "fonts"), dirs_exist_ok=True)

# Public directory (cleanly filtered)
print("  Setting up clean public/ in backend...")
dst_public = os.path.join(backend_dir, "public")
os.makedirs(dst_public, exist_ok=True)

# Copy base public files
for pf in ["index.php", ".htaccess", "robots.txt", "favicon.ico", "favicon.png"]:
    sp = os.path.join(ROOT_DIR, "public", pf)
    if os.path.exists(sp):
        shutil.copy2(sp, os.path.join(dst_public, pf))

# Copy public subfolders
public_subdirs = ["assets", "build", "pwa", "vendor"]
for ps in public_subdirs:
    src_ps = os.path.join(ROOT_DIR, "public", ps)
    if os.path.exists(src_ps):
        shutil.copytree(src_ps, os.path.join(dst_public, ps), symlinks=True, ignore=shutil.ignore_patterns("*.zip"))

# Copy pos-web (exclude zips)
src_pos_web = os.path.join(ROOT_DIR, "public", "pos-web")
if os.path.exists(src_pos_web):
    print("  Copying pos-web build...")
    shutil.copytree(src_pos_web, os.path.join(dst_public, "pos-web"), symlinks=True, ignore=shutil.ignore_patterns("*.zip", "release-output"))

# Copy modules web directory (exclude zips)
src_mod_web = os.path.join(ROOT_DIR, "public", "modules")
if os.path.exists(src_mod_web):
    shutil.copytree(src_mod_web, os.path.join(dst_public, "modules"), symlinks=True, ignore=shutil.ignore_patterns("*.zip"))

# Copy backend root configuration files
backend_root_files = [
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
    ".env.example",
]
for rf in backend_root_files:
    s = os.path.join(ROOT_DIR, rf)
    if os.path.exists(s):
        shutil.copy2(s, os.path.join(backend_dir, rf))

# Clean README for backend
backend_readme = """# Zoom Sales CRM & Inventory — Multi-Tenant SaaS Web Platform (v1.0.5)

Welcome to **Zoom Sales CRM & Inventory**, a complete enterprise SaaS platform with integrated **Retail POS**, **Cafe & Restaurant (Dine-in, Tables & KOT)**, Multi-Store Warehousing, Customer Storefront, and Centralized Administration.

---

## 🚀 Server Requirements

- **PHP**: 8.2 or 8.3
- **PHP Extensions**:
  - `BCMath`, `Ctype`, `cURL`, `DOM`, `Fileinfo`, `Filter`, `Hash`, `Intl`, `JSON`, `Mbstring`, `OpenSSL`, `PCRE`, `PDO`, `PDO_MySQL`, `Session`, `Tokenizer`, `XML`, `ZIP`
- **Database**: MySQL 5.7+ / 8.0+ or MariaDB 10.3+
- **Web Server**: Apache with `mod_rewrite` enabled OR Nginx
- **HTTPS / SSL Certificate**: Recommended for production

---

## 📦 Installation Options

### Option 1: 1-Click Web Installer (Recommended)
1. Upload and extract all files into your domain's web directory (or `public_html`).
2. Point your web server document root to the `public/` directory.
3. Open your browser and navigate to:
   `https://yourdomain.com/install`
4. Follow the interactive setup wizard:
   - System requirements check
   - Database connection settings
   - Automatic database migration & seeding
   - SuperAdmin account creation
5. Once completed, your system is live and ready!

### Option 2: Manual Installation via Database SQL
1. Upload and extract the project files to your web root.
2. In phpMyAdmin or MySQL terminal, import `03_Database_SQL/database.sql`.
3. Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```
4. Edit `.env` with your database credentials:
   ```ini
   APP_URL=https://yourdomain.com
   DB_DATABASE=your_database_name
   DB_USERNAME=your_database_user
   DB_PASSWORD=your_database_password
   ```
5. Generate an application encryption key:
   ```bash
   php artisan key:generate
   ```
6. Create the storage symbolic link:
   ```bash
   php artisan storage:link
   ```
7. Log in with the default credentials:
   - **URL**: `https://yourdomain.com/login`
   - **Email**: `admin@zoompos.com`
   - **Password**: `admin1234`

---

## 🔒 Directory Permissions

Ensure the web server user has write permissions for:
```bash
chmod -R 775 storage bootstrap/cache
```

---

## ⏰ Cron Jobs & Automation

Add the following Cron job to run every minute in your hosting cPanel or server crontab:
```bash
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🛡️ License & Support

Thank you for choosing Zoom Sales CRM & Inventory on CodeCanyon!
For technical support and documentation, please check the `04_Documentation` directory.
"""
with open(os.path.join(backend_dir, "README.md"), "w") as f:
    f.write(backend_readme)


# -------------------------------------------------------------
# 2. 02_Flutter_POS_Mobile_Desktop_Source
# -------------------------------------------------------------
print("\n[2/4] Preparing 02_Flutter_POS_Mobile_Desktop_Source...")
flutter_dir = os.path.join(STAGING_DIR, "02_Flutter_POS_Mobile_Desktop_Source")
os.makedirs(flutter_dir, exist_ok=True)
mobile_src = os.path.join(ROOT_DIR, "mobile")

flutter_subdirs = ["android", "windows", "web", "lib", "test", "assets"]
for fsub in flutter_subdirs:
    s = os.path.join(mobile_src, fsub)
    d = os.path.join(flutter_dir, fsub)
    print(f"  Copying {fsub}...")
    shutil.copytree(s, d, symlinks=True, ignore=shutil.ignore_patterns(
        ".git*", ".dart_tool*", "ephemeral", ".gradle", "local.properties", "*.log", "build"
    ))

# Flutter root files
flutter_files = ["pubspec.yaml", "pubspec.lock", "analysis_options.yaml"]
for ff in flutter_files:
    s = os.path.join(mobile_src, ff)
    if os.path.exists(s):
        shutil.copy2(s, os.path.join(flutter_dir, ff))

flutter_readme = """# Zoom POS — Flutter Cross-Platform Client (Android, Windows Desktop & Web) (v1.0.5)

This repository contains the complete source code for the **Zoom POS** client application built with **Flutter 3.22+**.

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
...
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
print("\n[3/4] Preparing 03_Database_SQL...")
db_dir = os.path.join(STAGING_DIR, "03_Database_SQL")
os.makedirs(db_dir, exist_ok=True)

# Copy clean database sql
clean_sql = os.path.join(ROOT_DIR, "public", "zoom-sales-crm-database-clean.sql")
shutil.copy2(clean_sql, os.path.join(db_dir, "database.sql"))

db_readme = """Zoom Sales CRM & Inventory — Database Setup Guide (v1.0.5)
===========================================================

This folder contains the complete, clean MySQL/MariaDB database dump (`database.sql`).
Includes: Retail & Restaurant/Cafe Modules, Default Tables, KOT, and SuperAdmin pre-seeded.

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
print("\n[4/4] Preparing 04_Documentation & Root Guide Files...")
doc_dir = os.path.join(STAGING_DIR, "04_Documentation")
os.makedirs(doc_dir, exist_ok=True)

doc_src = os.path.join(ROOT_DIR, "public", "documentation")
for item in ["index.html", "README.md", "Zoom_Sales_CRM_Feature_Guide.md", "CODECANYON_DESCRIPTION.html", "CODECANYON_LISTING_GUIDE.md"]:
    s = os.path.join(doc_src, item)
    if os.path.exists(s):
        shutil.copy2(s, os.path.join(doc_dir, item))

if os.path.exists(os.path.join(doc_src, "images")):
    shutil.copytree(os.path.join(doc_src, "images"), os.path.join(doc_dir, "images"), dirs_exist_ok=True)

# Copy root listing guide and description
for item in ["CODECANYON_DESCRIPTION.html", "CODECANYON_LISTING_GUIDE.md"]:
    s = os.path.join(doc_src, item)
    if os.path.exists(s):
        shutil.copy2(s, os.path.join(STAGING_DIR, item))

quick_start_guide = """# Zoom Sales CRM & Inventory — CodeCanyon Product Package (v1.0.5)

Thank you for choosing **Zoom Sales CRM & Inventory**!

## 📁 Package Structure

| Directory | Content Description |
| :--- | :--- |
| `01_Laravel_Backend_Web_Source/` | Complete Laravel 11 SaaS Web Platform Source Code (Retail & Restaurant/Cafe Built-in, SuperAdmin, Store Admin, Storefront, APIs) |
| `02_Flutter_POS_Mobile_Desktop_Source/` | Flutter 3.22+ Cross-Platform Source Code (Android APK, Windows Desktop, Web PWA) with offline sync & multi-language support |
| `03_Database_SQL/` | Clean MySQL Database Dump (`database.sql`) + Import Instructions |
| `04_Documentation/` | Full Offline Interactive Documentation Portal (`index.html`), Setup Guides, API reference & Diagrams |
| `CODECANYON_DESCRIPTION.html` | Ready-to-use HTML Product Description for CodeCanyon item page |
| `CODECANYON_LISTING_GUIDE.md` | Category, tags, pricing, and submission metadata guide |

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
# 5. Build ZIP Package
# -------------------------------------------------------------
print("\nCreating final ZIP package...")
print(f"Target: {OUTPUT_ZIP}")

if os.path.exists(OUTPUT_ZIP):
    os.remove(OUTPUT_ZIP)

start_time = time.time()
# Use zip utility for speed and optimal compression
zip_cmd = ["zip", "-r", "-q", "-9", OUTPUT_ZIP, "."]
res = subprocess.run(zip_cmd, cwd=STAGING_DIR)

if res.returncode != 0:
    print(f"Error executing zip: {res.stderr}")
    sys.exit(1)

elapsed = time.time() - start_time
size_bytes = os.path.getsize(OUTPUT_ZIP)
size_mb = size_bytes / (1024 * 1024)

print(f"Successfully created {OUTPUT_ZIP} ({size_mb:.2f} MB) in {elapsed:.1f}s.")

# Copy/Update alias
shutil.copy2(OUTPUT_ZIP, ALIAS_ZIP)
os.chmod(OUTPUT_ZIP, 0o644)
os.chmod(ALIAS_ZIP, 0o644)
print(f"Updated alias {ALIAS_ZIP}.")

# Verification
print("\nVerifying archive integrity with unzip -t...")
test_res = subprocess.run(["unzip", "-t", OUTPUT_ZIP], capture_output=True, text=True)
if test_res.returncode == 0:
    print("Archive integrity verified: OK!")
else:
    print(f"Archive test failed: {test_res.stderr}")
    sys.exit(1)

print("\n=== CodeCanyon Package Build Complete! ===")
