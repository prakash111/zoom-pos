# Flutter POS Cross-Platform Client — Compilation & White-Labeling Guide

## 1. Overview

The **Zoom POS** client application is built with **Flutter 3.22+** (Dart 3.4+) and provides cross-platform support across:
- **Android**: Wireless handheld POS terminals, tablets, and smartphones.
- **Windows Desktop**: 64-bit native desktop application with direct ESC/POS thermal printer support.
- **Web (PWA)**: Progressive Web Application running in modern browsers.

This guide walks you through configuring your branding, pointing the app to your deployed SaaS backend, and compiling release binaries for Android, Windows, and Web.

```
+-------------------------------------------------------------------------+
|                  Zoom POS Cross-Platform Client Architecture            |
+-------------------------------------------------------------------------+
                                    |
      +-----------------------------+-----------------------------+
      |                             |                             |
      v                             v                             v
+------------------+      +-------------------+      +--------------------+
|  Android Device  |      |  Windows Desktop  |      |   Web PWA Client   |
| (APK / AAB)      |      | (.exe Runner)     |      |  (public/pos-web/) |
+------------------+      +-------------------+      +--------------------+
      |                             |                             |
      +-----------------------------+-----------------------------+
                                    |
                       REST API / Server-Driven UI
                                    v
+-------------------------------------------------------------------------+
|              Your Laravel SaaS Backend (https://yourdomain.com)         |
+-------------------------------------------------------------------------+
```

---

## 2. Prerequisites & Environment Setup

Before compiling, install the required development tools for your platform:

### For All Platforms:
1. **Flutter SDK**: Version 3.22.x or higher ([flutter.dev/docs/get-started/install](https://docs.flutter.dev/get-started/install))
2. **Dart SDK**: Version 3.4.x or higher (included with Flutter)
3. Run `flutter doctor` to verify your environment is ready.

### For Android Compilation:
- **Android Studio** with Android SDK Platform 34+ and Android Command-line Tools.
- Java Development Kit (JDK 17).

### For Windows Desktop Compilation:
- **Visual Studio 2022** (Community or higher) with the **"Desktop development with C++"** workload selected.
- Windows 10/11 64-bit.
- *(Optional)* **Inno Setup 6** for generating a standalone Windows setup installer (`.exe`).

---

## 3. Connecting to Your SaaS Backend

Open the configuration file located at:
`lib/core/config/app_config.dart`

Update `defaultBaseUrl` to point to your live SaaS server domain:

```dart
class AppConfig {
  AppConfig._();

  // Change this to your SaaS platform domain:
  static const String defaultBaseUrl = 'https://yourdomain.com';
  static const String apiPrefix = '/api/v1/pos';
}
```

---

## 4. White-Labeling & Branding Customization

### Changing the App Name
- **Android**: Open `android/app/src/main/AndroidManifest.xml` and change the `android:label`:
  ```xml
  <application
      android:label="My Brand POS"
      android:name="${applicationName}"
      android:icon="@mipmap/ic_launcher">
  ```
- **Windows**: Open `windows/runner/Runner.rc` and update the product name strings:
  ```rc
  VALUE "ProductName", "My Brand POS"
  ```
- **Web**: Open `web/index.html` and update `<title>My Brand POS</title>`.

### Changing the Package Identifier (Application ID)
- Open `android/app/build.gradle`:
  ```groovy
  defaultConfig {
      applicationId "com.mycompany.pos"
      minSdkVersion 21
      targetSdkVersion 34
      versionCode 1
      versionName "1.0.0"
  }
  ```

### Customizing App Icons
1. Place your high-resolution logo (`1024x1024` PNG) in `assets/icon/launcher.png`.
2. Configure `pubspec.yaml` with `flutter_launcher_icons`:
   ```yaml
   flutter_launcher_icons:
     android: true
     windows: true
     web: true
     image_path: "assets/icon/launcher.png"
   ```
3. Run:
   ```bash
   flutter pub run flutter_launcher_icons
   ```

---

## 5. Compilation Commands

Open a terminal inside the `flutter_pos_source` (or `mobile`) directory:

### Step 1: Install Dependencies
```bash
flutter pub get
```

### Step 2: Build for Android (Release APK)
```bash
flutter build apk --release
```
The compiled release APK will be located at:
`build/app/outputs/flutter-apk/app-release.apk`

### Step 3: Build for Android (Google Play Store App Bundle)
```bash
flutter build appbundle --release
```
The compiled AAB will be located at:
`build/app/outputs/bundle/release/app-release.aab`

### Step 4: Build for Windows Desktop (64-bit)
```bash
flutter build windows --release
```
The compiled Windows binary and supporting DLLs will be located at:
`build/windows/x64/runner/Release/`

To create a single-file Windows setup installer, open `installer.iss` in **Inno Setup** and click **Compile**.

### Step 5: Build for Web POS
```bash
flutter build web --base-href /pos-web/ --release
```
Copy all files from `build/web/` into your web server's `public/pos-web/` directory.

---

## 6. Hardware & Peripheral Support

The compiled POS client automatically supports common retail and restaurant peripherals out of the box:

- **Thermal Receipt Printers (58mm / 80mm)**:
  - USB direct connection (Windows & Android OTG)
  - LAN / Ethernet IP printers (ESC/POS network printing)
  - Bluetooth mobile thermal printers
- **Barcode & 2D QR Scanners**:
  - USB HID Keyboard Emulation mode
  - Bluetooth wireless scanners
- **Electronic Cash Drawers**:
  - Standard RJ11 / RJ12 connection through thermal printer kick-out pulse
- **Dual Display / Kitchen Display**:
  - Live order dispatch screens on tablets and desktop monitors
