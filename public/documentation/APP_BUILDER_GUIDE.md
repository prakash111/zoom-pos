# Flutter App Builder with GitHub Actions — Guide & Architecture

## 1. Overview & Architecture

The **Zoom POS App Builder** provides a self-service, cloud-compiled build pipeline allowing licensed customers to generate customized, white-labeled client binaries for all supported Flutter platforms:
- **Android**: APK binary (`.apk`)
- **Web**: Progressive Web Application distribution bundle (`.zip`)
- **Windows**: Native desktop executable & runtime package (`.zip`)
- **iOS**: Enterprise archive / Xcode project distribution (`.zip`)

Compilation is executed entirely within isolated **GitHub Actions** runner runners. Sensitive credentials (such as repository tokens, workflow configurations, and backend repository URLs) are strictly protected server-side and never exposed to client browsers.

```
+-----------------------------------------------------------------------+
|                       Customer / License Holder                       |
+-----------------------------------------------------------------------+
                                  |
            1. Validates License Key (No login password required)
                                  v
+-----------------------------------------------------------------------+
|               Zoom POS App Builder Dashboard & Wizard                 |
|               (Hosted at /app-builder/ on SaaS or License Server)     |
+-----------------------------------------------------------------------+
        |                                       |
 2A. Source Option A                     2B. Source Option B
 Fetch Latest Git Branch                 Upload Custom Flutter ZIP
 (feat/windows-offline-sync)             (Zip Slip protected, pubspec checked)
        \                                       /
         \                                     /
          v                                   v
+-----------------------------------------------------------------------+
|      App Customizer: Logo, Package ID, Color Picker, Server URL       |
+-----------------------------------------------------------------------+
                                  |
                      3. Check Monthly Quota
                                  |
                      4. Dispatch GitHub Actions
                                  v
+-----------------------------------------------------------------------+
|                     GitHub Actions Build Pipeline                     |
|           mobile/.github/workflows/app-builder.yml                    |
|             (Android, Web, Windows, iOS Runners)                     |
+-----------------------------------------------------------------------+
                                  |
           5. Real-time Status Polling & Artifact Ingestion
                                  |
            +---------------------+---------------------+
            |                                           |
            v                                           v
+-----------------------+                   +-----------------------+
|  Secure Local Storage |                   | Automated Email Alert |
|  & Binary Download    |                   | with Download Link    |
+-----------------------+                   +-----------------------+
```

---

## 2. License Authentication & Security

- **Strict License Access**: Access to the builder requires an active, valid license key. Existing license verification logic is reused without duplication or bypass.
- **Multi-Platform Parallel Compilation**: Supports selecting multiple target platforms simultaneously (e.g., Android, Web, Windows, and iOS). The engine dispatches concurrent GitHub Actions runner jobs and tracks each job live on the dashboard with individual artifact ingestion.
- **Owner Identification**: The licensee's email and plan entitlement (e.g. Standard, Professional, Enterprise, Extended) are resolved upon authentication.
- **Repository Abstraction**: Customers choose between "Latest Cloud Release" or "Upload Source Package". The internal GitHub repository URL and Personal Access Tokens are never disclosed.
- **ZIP Security (Zip Slip Prevention)**: Uploaded ZIP archives are verified for directory traversal vulnerabilities, size limits (max 100MB), and valid `pubspec.yaml` Flutter project structure.
- **Asset Sanitization**: Uploaded logos are validated for MIME type (`image/png`, `image/jpeg`, `image/webp`, `image/svg+xml`) and maximum dimensions/file size (2MB).

---

## 3. License Eligibility & Quota Management

### Who is Eligible for the App Builder?
Build usage and monthly quotas are tied strictly to the **Main Core Script License** (`licenses` table) within the License Manager.

- **ELIGIBLE**:
  - **Core SaaS Script Licenses**: All tiers including Regular, Extended, and Enterprise licenses.
  - **Bundle Packages containing Core**: In bundle orders, the quota is assigned automatically to the core script license.
  - **SaaS Professional Plan Subscribers**: Included with active yearly subscriptions.
- **NOT ELIGIBLE**:
  - **Standalone Modules & Plugins**: Addon modules (such as Lead Management, WhatsApp Gateway addon, WooCommerce Sync, Restaurant KDS module alone) are server-side extensions and do NOT receive standalone build quotas.
  - Standalone module purchases cannot be used alone to authenticate or trigger builds in the App Builder.

### License Manager Setup (`admin/build-stats.php`)
1. Navigate to the **License Manager Admin** -> **Build Stats** (`admin/build-stats.php`).
2. Scroll to the **Global App Builder & Monthly Plan Quotas** section.
3. Configure the monthly build limits per license plan:
   - **Extended / Unlimited Plans**: `-1` (Unlimited builds).
   - **Pro Plan**: Default `30` builds/month.
   - **Regular / Basic Plans**: Default `10` builds/month.
   - **Trial / Free Plans**: Default `2` builds/month.
4. Individual licenses can also have a custom override in the `licenses.app_builder_monthly_limit` column (Core script licenses only; modules are enforced as NULL/NA).

### Monthly Quota Enforcement
- Quota is tracked per `license_key` in the `app_builds` table for the current calendar month.
- Quotas reset automatically on the 1st of each calendar month at 00:00 UTC.
- When a license holder reaches their monthly limit, a notice informs them of their remaining allocation and reset date, preventing unauthorized dispatch.

---

## 4. Standalone License Server Deployment (`license.zoomnearby.com`)

The App Builder is bundled into `/public/license-server-files.zip` for deployment on standalone licensing hosts.

### Step 1: Upload and Extract Files
Upload `/public/license-server-files.zip` to the document root of `https://license.zoomnearby.com`:
```bash
unzip -o license-server-files.zip -d /path/to/license.zoomnearby.com/
```

### Step 2: Run Database Setup / Installer
Run the installer script via web browser or command line:
```bash
php bin/install.php
# OR run setup.php via browser: https://license.zoomnearby.com/setup.php
```
This script automatically executes `database/add_app_builder.sql`, creating the `app_builds` table and seeding default builder settings.

### Step 3: Configure GitHub Actions Integration
1. Log into the License Server Admin Panel: `https://license.zoomnearby.com/admin/`
2. Navigate to **App Builder** (`admin/builder.php`) or **Settings** (`admin/settings.php`).
3. Set the following parameters:
   - **GitHub Repository**: `prakash111/zoom-pos`
   - **GitHub Branch**: `feat/windows-offline-sync`
   - **GitHub Workflow File**: `app-builder.yml` (located in `.github/workflows/`)
   - **GitHub Personal Access Token**: Token with `workflow` and `repo` permissions.
   - **Default Monthly Limit**: `10` builds per month.

---

## 5. Building an App Step-by-Step

1. **Access Builder**: Go to `https://saas.zoomnearby.com/app-builder/` (or `https://license.zoomnearby.com/app-builder/`).
2. **Authenticate**: Enter the purchase license key.
3. **Initiate New Build**: Click **+ Create New Build**.
4. **Step 1 — Source**: Select **Latest Cloud Release** (recommended) or upload a custom Flutter ZIP file.
5. **Step 2 — Branding & Settings**:
   - **Application Name**: e.g., `My Brand POS`
   - **Package ID / Bundle Identifier**: e.g., `com.mybrand.pos`
   - **Backend Server API URL**: e.g., `https://pos.mybrand.com`
   - **Primary Brand Color**: Pick via visual color picker or hex input (e.g. `#10B981`)
   - **App Logo**: Upload a high-resolution PNG or JPEG logo (auto-centered on white canvas).
6. **Step 3 — Target Platform**:
   - Choose **Android (.apk)**, **Web (.zip)**, **Windows Desktop (.zip)**, or **iOS (.zip)**.
7. **Step 4 — Review & Trigger**:
   - Verify all parameters and click **Start Cloud Build**.
8. **Real-time Monitoring**:
   - The build status view polls GitHub Actions in real time through `queued` -> `preparing` -> `building` -> `completed`.
9. **Artifact Retrieval**:
   - Once completed, click **Download Artifact** to download the compiled binary.
   - An automated email notification containing the direct download link is also dispatched to the licensee's email address.

---

## 6. License Server Admin Statistics & Monitoring

Server administrators can monitor all customer compilation activity in `admin/builder.php`:
- **Summary Metrics**: Total builds, successful compilations, in-progress jobs, and failure rates.
- **Licensee Quota Auditing**: Search and filter builds by license key, customer email, or platform.
- **Workflow Diagnostics**: Inspect GitHub Actions Run IDs, execution durations, and error logs for troubleshooting failed compilations.
