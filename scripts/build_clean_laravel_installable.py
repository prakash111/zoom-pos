#!/usr/bin/env python3
import os
import sys
import shutil
import zipfile
import subprocess
import time

ROOT_DIR = "/home/zoomnearby-saas/htdocs/saas.zoomnearby.com"
SCRATCH_DIR = "/home/zoomnearby-saas/.gemini/antigravity-cli/brain/68267744-b8c8-44da-b7be-00231cc98f9a/scratch"
STAGING_DIR = os.path.join(SCRATCH_DIR, "clean_laravel_installable")
PRIMARY_ZIP = os.path.join(ROOT_DIR, "public", "zoom-sales-crm-laravel-installable.zip")
ALIAS_ZIP = os.path.join(ROOT_DIR, "public", "laravel-installable.zip")

print("=== Starting Clean Laravel Installable Package Build ===")

if os.path.exists(STAGING_DIR):
    print(f"Cleaning existing staging directory {STAGING_DIR}...")
    shutil.rmtree(STAGING_DIR)

os.makedirs(STAGING_DIR, exist_ok=True)

# -------------------------------------------------------------
# 1. Copy Laravel Core Directories
# -------------------------------------------------------------
print("\n[1/8] Copying Laravel Server core directories...")
core_dirs = [
    "app",
    "config",
    "database",
    "lang",
    "resources",
    "routes",
    "tests",
    "vendor",
]

for item in core_dirs:
    src = os.path.join(ROOT_DIR, item)
    dst = os.path.join(STAGING_DIR, item)
    if os.path.exists(src):
        print(f"  Copying {item}/...")
        shutil.copytree(src, dst, symlinks=True, ignore=shutil.ignore_patterns(
            ".git*", "__pycache__", "*.pyc", "*.log", "*.sqlite3", ".phpunit.result.cache", "*.zip"
        ))

# -------------------------------------------------------------
# 2. Setup Clean bootstrap/
# -------------------------------------------------------------
print("\n[2/8] Setting up clean bootstrap/ directory...")
bootstrap_dst = os.path.join(STAGING_DIR, "bootstrap")
os.makedirs(os.path.join(bootstrap_dst, "cache"), exist_ok=True)

for bf in ["app.php", "providers.php"]:
    src_f = os.path.join(ROOT_DIR, "bootstrap", bf)
    if os.path.exists(src_f):
        shutil.copy2(src_f, os.path.join(bootstrap_dst, bf))

with open(os.path.join(bootstrap_dst, "cache", ".gitignore"), "w") as f:
    f.write("*\n!.gitignore\n")

# -------------------------------------------------------------
# 3. Setup Clean storage/
# -------------------------------------------------------------
print("\n[3/8] Setting up clean storage/ directory...")
storage_dirs = [
    "app/public",
    "framework/cache/data",
    "framework/sessions",
    "framework/views",
    "logs",
]

for sd in storage_dirs:
    full_sd = os.path.join(STAGING_DIR, "storage", sd)
    os.makedirs(full_sd, exist_ok=True)
    with open(os.path.join(full_sd, ".gitignore"), "w") as f:
        f.write("*\n!.gitignore\n")

# Fonts if present in storage
src_fonts = os.path.join(ROOT_DIR, "storage", "fonts")
if os.path.exists(src_fonts):
    shutil.copytree(src_fonts, os.path.join(STAGING_DIR, "storage", "fonts"), dirs_exist_ok=True)

# -------------------------------------------------------------
# 4. Setup Clean public/ (Excluding lic, marketing, modules, zips, binaries)
# -------------------------------------------------------------
print("\n[4/8] Setting up clean public/ directory (excluding license, marketing, addon zips)...")
dst_public = os.path.join(STAGING_DIR, "public")
os.makedirs(dst_public, exist_ok=True)

# Base public files
for pf in ["index.php", ".htaccess", "robots.txt", "favicon.ico", "favicon.png", "restaurant.mp3", "sw.js", "offline.html"]:
    sp = os.path.join(ROOT_DIR, "public", pf)
    if os.path.exists(sp):
        shutil.copy2(sp, os.path.join(dst_public, pf))

# Public subfolders: assets, build, pwa, vendor
for ps in ["assets", "build", "pwa", "vendor"]:
    src_ps = os.path.join(ROOT_DIR, "public", ps)
    if os.path.exists(src_ps):
        print(f"  Copying public/{ps}/...")
        shutil.copytree(src_ps, os.path.join(dst_public, ps), symlinks=True, ignore=shutil.ignore_patterns("*.zip", "*.tar.gz"))

# Copy pos-web (Flutter Web client for Retail & Restaurant POS)
# Exclude recursive build/ duplicate, release-output, and zips
src_pos_web = os.path.join(ROOT_DIR, "public", "pos-web")
if os.path.exists(src_pos_web):
    print("  Copying public/pos-web/ (clean Flutter Web POS)...")
    shutil.copytree(
        src_pos_web,
        os.path.join(dst_public, "pos-web"),
        symlinks=True,
        ignore=shutil.ignore_patterns("*.zip", "release-output", "build")
    )

# -------------------------------------------------------------
# 5. Root Configuration Files & Clean .env.example
# -------------------------------------------------------------
print("\n[5/8] Copying root configuration files...")
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
        shutil.copy2(s, os.path.join(STAGING_DIR, rf))

# Clean .env.example with DEMO_MODE=false
src_env_example = os.path.join(ROOT_DIR, ".env.example")
if os.path.exists(src_env_example):
    with open(src_env_example, "r") as f:
        env_content = f.read()
    # Ensure DEMO_MODE=false in installable template
    env_content = env_content.replace("DEMO_MODE=true", "DEMO_MODE=false")
    with open(os.path.join(STAGING_DIR, ".env.example"), "w") as f:
        f.write(env_content)

# -------------------------------------------------------------
# 6. Clean Database SQL Dump
# -------------------------------------------------------------
print("\n[6/8] Adding clean database.sql with Retail & Restaurant core modules...")
src_sql = os.path.join(ROOT_DIR, "public", "zoom-sales-crm-database-clean.sql")
if os.path.exists(src_sql):
    # Copy to root and database/
    shutil.copy2(src_sql, os.path.join(STAGING_DIR, "database.sql"))
    shutil.copy2(src_sql, os.path.join(STAGING_DIR, "database", "database.sql"))
    print("  database.sql added to root and database/ folder.")

# -------------------------------------------------------------
# 7. Documentation & Quick Start Guides
# -------------------------------------------------------------
print("\n[7/8] Generating clean installation documentation...")

readme_content = """# Zoom Sales CRM & Inventory — Laravel SaaS Web Platform

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
4. Import the provided `database.sql` file via phpMyAdmin or terminal:
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

Enjoy using **Zoom Sales CRM & Inventory**!
"""

with open(os.path.join(STAGING_DIR, "README.md"), "w") as f:
    f.write(readme_content)

with open(os.path.join(STAGING_DIR, "INSTALL.md"), "w") as f:
    f.write(readme_content)

# -------------------------------------------------------------
# 8. Strict Exclusion Verification
# -------------------------------------------------------------
print("\n[8/8] Verifying strict exclusions...")

forbidden_paths = [
    "lic",
    "public/lic",
    "public/marketing",
    "public/app-builder",
    "public/modules",
    "module-packages",
    "mobile",
    "read",
    "scripts",
    "scratchpad",
    "node_modules",
    ".git",
]

violations = []
for root, dirs, files in os.walk(STAGING_DIR):
    rel_dir = os.path.relpath(root, STAGING_DIR)
    for d in dirs:
        full_rel = os.path.normpath(os.path.join(rel_dir, d))
        for forbidden in forbidden_paths:
            if full_rel == forbidden or full_rel.startswith(forbidden + os.sep):
                violations.append(f"Directory: {full_rel}")
    for f in files:
        full_rel = os.path.normpath(os.path.join(rel_dir, f))
        if f.endswith(".apk") or (f.endswith(".exe") and not full_rel.startswith("vendor" + os.sep) and not "win_ble" in full_rel):
            violations.append(f"Forbidden binary: {full_rel}")
        if f.endswith(".zip"):
            violations.append(f"Forbidden zip archive: {full_rel}")
        if f.endswith(".py") and not full_rel.startswith("vendor" + os.sep):
            violations.append(f"Forbidden script: {full_rel}")
        for forbidden in forbidden_paths:
            if full_rel.startswith(forbidden + os.sep) or full_rel == forbidden:
                violations.append(f"Forbidden file in {forbidden}: {full_rel}")

if violations:
    print("ERROR: Found forbidden items in staging directory:")
    for v in violations[:10]:
        print(f"  - {v}")
    sys.exit(1)
else:
    print("  Exclusion verification passed! No license, marketing landing, addon packages, mobile source, or binaries found.")

# -------------------------------------------------------------
# 9. Create Final ZIP Archive
# -------------------------------------------------------------
print("\nCreating final ZIP archive...")
print(f"Target: {PRIMARY_ZIP}")

if os.path.exists(PRIMARY_ZIP):
    os.remove(PRIMARY_ZIP)
if os.path.exists(ALIAS_ZIP):
    os.remove(ALIAS_ZIP)

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

# Copy/Alias
shutil.copy2(PRIMARY_ZIP, ALIAS_ZIP)
os.chmod(PRIMARY_ZIP, 0o644)
os.chmod(ALIAS_ZIP, 0o644)
print(f"Created alias {ALIAS_ZIP} (0644).")

# Verify Archive Integrity
print("\nVerifying archive integrity with unzip -t...")
test_res = subprocess.run(["unzip", "-t", PRIMARY_ZIP], capture_output=True, text=True)
if test_res.returncode == 0:
    print("Archive integrity verified: OK!")
else:
    print(f"Archive test failed: {test_res.stderr}")
    sys.exit(1)

print("\n=== Clean Laravel Installable Package Build Complete! ===")
