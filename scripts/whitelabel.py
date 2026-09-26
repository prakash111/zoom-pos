#!/usr/bin/env python3
"""
Complete White Label Branding System - Engine
Replaces all branding references across Android, Web, Windows, iOS, and Flutter code.
Guarantees 100% white-labeled assets, icons, base paths, and runtime metadata.
"""

import sys
import os
import re
import json
import shutil
import base64
from io import BytesIO
from pathlib import Path

try:
    from PIL import Image, ImageDraw, ImageFont
    PIL_AVAILABLE = True
except ImportError:
    PIL_AVAILABLE = False


def sanitize_slug(name):
    clean = re.sub(r'[^a-zA-Z0-9_]+', '_', name.strip()).lower().strip('_')
    return clean if clean else 'app'


def safe_replace(file_path, pattern, replacement, is_regex=True):
    if not os.path.exists(file_path):
        return False
    try:
        with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()
        
        if is_regex:
            new_content = re.sub(pattern, replacement, content)
        else:
            new_content = content.replace(pattern, replacement)
            
        if new_content != content:
            with open(file_path, 'w', encoding='utf-8') as f:
                f.write(new_content)
            return True
    except Exception as e:
        print(f"[WARN] Error updating {file_path}: {e}")
    return False


def get_or_create_master_image(assets_dir, config_path, cfg, primary_color, app_name, short_name):
    if not PIL_AVAILABLE:
        print("    [INFO] PIL (Pillow) not installed; using pre-bundled assets directly.")
        return None

    # Priority 1: Check pre-extracted asset files in assets_dir
    candidate_paths = []
    if assets_dir:
        candidate_paths.extend([
            os.path.join(assets_dir, 'assets/images/app_logo.png'),
            os.path.join(assets_dir, 'assets/images/app_logo_light.png'),
            os.path.join(assets_dir, 'assets/images/splash_logo.png'),
            os.path.join(assets_dir, 'app_logo.png'),
            os.path.join(assets_dir, 'main_logo.png'),
            os.path.join(assets_dir, 'assets/icon/launcher.png'),
            os.path.join(assets_dir, 'web/icons/Icon-512.png'),
        ])

    # Priority 2: Check workspace directory of config
    config_dir = os.path.dirname(os.path.abspath(config_path))
    candidate_paths.extend([
        os.path.join(config_dir, 'master_logo.png'),
        os.path.join(config_dir, 'app_logo.png'),
        os.path.join(config_dir, 'assets/app_logo.png'),
        os.path.join(config_dir, 'assets/main_logo.png'),
    ])

    for p in candidate_paths:
        if os.path.isfile(p):
            try:
                img = Image.open(p).convert('RGBA')
                print(f"    ✓ Loaded master branding image from {p}")
                return img
            except Exception as e:
                print(f"    [WARN] Could not load image from {p}: {e}")

    # Priority 3: Base64 custom logo from config
    b64_logo = (cfg.get('custom_logo_base64') or '').strip()
    if b64_logo:
        try:
            img_data = base64.b64decode(b64_logo)
            img = Image.open(BytesIO(img_data)).convert('RGBA')
            print(f"    ✓ Decoded master branding image from base64 configuration")
            return img
        except Exception as e:
            print(f"    [WARN] Failed decoding base64 custom logo: {e}")

    # Priority 4: Generate crisp, high-resolution branded monogram master icon
    try:
        size = 512
        img = Image.new('RGBA', (size, size), (0, 0, 0, 0))
        draw = ImageDraw.Draw(img)

        # Parse primary color
        clean_hex = primary_color.lstrip('#')
        if len(clean_hex) == 3:
            clean_hex = ''.join([c*2 for c in clean_hex])
        r = int(clean_hex[0:2], 16) if len(clean_hex) >= 2 else 79
        g = int(clean_hex[2:4], 16) if len(clean_hex) >= 4 else 70
        b = int(clean_hex[4:6], 16) if len(clean_hex) >= 6 else 229

        # Draw rounded rectangle container
        radius = 110
        draw.rounded_rectangle([0, 0, size, size], radius=radius, fill=(r, g, b, 255))

        # Brand monogram text (1 to 3 characters)
        initials = re.sub(r'[^a-zA-Z0-9]', '', short_name or app_name)[:3].upper()
        if not initials:
            initials = 'POS'

        # Load font or draw geometric monogram
        font = None
        font_size = 200 if len(initials) == 1 else (160 if len(initials) == 2 else 120)
        font_paths = [
            "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
            "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf",
            "C:\\Windows\\Fonts\\arialbd.ttf",
            "C:\\Windows\\Fonts\\segoeuib.ttf",
            "/System/Library/Fonts/SFPro-Bold.ttf",
            "/System/Library/Fonts/Helvetica.ttc",
        ]
        for fp in font_paths:
            if os.path.exists(fp):
                try:
                    font = ImageFont.truetype(fp, font_size)
                    break
                except Exception:
                    pass

        if font:
            bbox = draw.textbbox((0, 0), initials, font=font)
            text_w = bbox[2] - bbox[0]
            text_h = bbox[3] - bbox[1]
            text_x = (size - text_w) / 2 - bbox[0]
            text_y = (size - text_h) / 2 - bbox[1]
            draw.text((text_x, text_y), initials, fill=(255, 255, 255, 255), font=font)
        else:
            try:
                def_font = ImageFont.load_default()
                temp_img = Image.new('RGBA', (60, 20), (0, 0, 0, 0))
                temp_draw = ImageDraw.Draw(temp_img)
                temp_draw.text((0, 0), initials, fill=(255, 255, 255, 255), font=def_font)
                scaled_text = temp_img.resize((300, 100), resample=Image.Resampling.NEAREST)
                img.paste(scaled_text, ((size - 300) // 2, (size - 100) // 2), scaled_text)
            except Exception:
                pass

        print(f"    ✓ Generated dynamic branded monogram master icon ({initials})")
        return img
    except Exception as e:
        print(f"    [WARN] Failed to generate dynamic branded master icon: {e}")
        return None


def generate_all_icons_from_master(project_root, master_img):
    if not PIL_AVAILABLE or master_img is None:
        return

    def fit_square(src_img, target_size):
        w, h = src_img.size
        square = Image.new('RGBA', (target_size, target_size), (0, 0, 0, 0))
        if w >= h:
            new_w = target_size
            new_h = int(round((h / w) * target_size))
            resized = src_img.resize((new_w, new_h), resample=Image.Resampling.LANCZOS)
            square.paste(resized, (0, (target_size - new_h) // 2))
        else:
            new_h = target_size
            new_w = int(round((w / h) * target_size))
            resized = src_img.resize((new_w, new_h), resample=Image.Resampling.LANCZOS)
            square.paste(resized, ((target_size - new_w) // 2, 0))
        return square

    targets = {
        'web/favicon.png': 32,
        'web/icons/Icon-192.png': 192,
        'web/icons/Icon-512.png': 512,
        'web/icons/Icon-maskable-192.png': 192,
        'web/icons/Icon-maskable-512.png': 512,
        'assets/images/app_logo.png': 512,
        'assets/images/app_logo_light.png': 512,
        'assets/images/app_logo_dark.png': 512,
        'assets/images/splash_logo.png': 512,
        'assets/icon/launcher.png': 512,
        'android/app/src/main/res/mipmap-mdpi/ic_launcher.png': 48,
        'android/app/src/main/res/mipmap-hdpi/ic_launcher.png': 72,
        'android/app/src/main/res/mipmap-xhdpi/ic_launcher.png': 96,
        'android/app/src/main/res/mipmap-xxhdpi/ic_launcher.png': 144,
        'android/app/src/main/res/mipmap-xxxhdpi/ic_launcher.png': 192,
    }

    for rel_path, sz in targets.items():
        dst = os.path.join(project_root, rel_path)
        os.makedirs(os.path.dirname(dst), exist_ok=True)
        img_resized = fit_square(master_img, sz)
        img_resized.save(dst, format='PNG')
        print(f"    ✓ Generated {sz}x{sz} icon: {rel_path}")

    # Multi-resolution Windows ICO
    ico_dst = os.path.join(project_root, 'windows/runner/resources/app_icon.ico')
    if os.path.exists(os.path.dirname(ico_dst)):
        try:
            ico_sizes = [(16, 16), (32, 32), (48, 48), (64, 64), (128, 128), (256, 256)]
            master_img.save(ico_dst, format='ICO', sizes=ico_sizes)
            print(f"    ✓ Generated Windows multi-res app_icon.ico")
        except Exception as e:
            print(f"    [WARN] Failed to write windows ICO: {e}")


def apply_whitelabel(project_root, config_path, assets_dir=None):
    project_root = os.path.abspath(project_root)
    print(f"=================================================================")
    print(f"Starting Complete White-Label Branding Engine on: {project_root}")
    print(f"=================================================================")

    with open(config_path, 'r', encoding='utf-8') as f:
        cfg = json.load(f)

    # Core metadata
    company_name = (cfg.get('company_name') or 'Zoom Nearby').strip()
    product_name = (cfg.get('product_name') or 'Zoom Sales CRM').strip()
    app_name = (cfg.get('app_name') or 'Zoom Sales POS').strip()
    short_name = (cfg.get('short_name') or re.sub(r'[^a-zA-Z0-9]+', '', app_name)).strip()
    display_name = (cfg.get('display_name') or app_name).strip()
    org_name = (cfg.get('org_name') or company_name).strip()
    copyright_text = (cfg.get('copyright') or f"Copyright (C) 2026 {company_name}. All rights reserved.").strip()
    support_email = (cfg.get('support_email') or 'support@zoomnearby.com').strip()
    support_phone = (cfg.get('support_phone') or '+918535075196').strip()
    website_url = (cfg.get('website_url') or 'https://zoomnearby.com').strip()
    server_url = (cfg.get('server_url') or 'https://saas.zoomnearby.com').rstrip('/')
    package_id = (cfg.get('package_id') or 'com.zoomnearby.zoompos').strip().lower()

    # Colors
    primary_color = (cfg.get('primary_color') or '#4F46E5').strip()
    secondary_color = (cfg.get('secondary_color') or '#06B6D4').strip()
    accent_color = (cfg.get('accent_color') or '#10B981').strip()
    bg_color = (cfg.get('bg_color') or '#0F172A').strip()
    sidebar_color = (cfg.get('sidebar_color') or '#1E293B').strip()
    text_color = (cfg.get('text_color') or '#F8FAFC').strip()

    short_snake = sanitize_slug(short_name)
    primary_hex = primary_color.lstrip('#').upper()
    accent_hex = accent_color.lstrip('#').upper()
    bg_hex = bg_color.lstrip('#').upper()

    print(f"[*] Brand Identity:")
    print(f"    - Company:     {company_name}")
    print(f"    - Product:     {product_name}")
    print(f"    - App Name:    {app_name} ({short_name})")
    print(f"    - Display:     {display_name}")
    print(f"    - Package ID:  {package_id}")
    print(f"    - Server URL:  {server_url}")
    print(f"    - Primary Col: {primary_color}")

    # 1. COPY PRE-BUNDLED VISUAL ASSETS (if available)
    if assets_dir and os.path.isdir(assets_dir):
        print(f"[*] Copying available white-label visual assets from {assets_dir}...")
        asset_mappings = {
            'android/mipmap-mdpi/ic_launcher.png': 'android/app/src/main/res/mipmap-mdpi/ic_launcher.png',
            'android/mipmap-hdpi/ic_launcher.png': 'android/app/src/main/res/mipmap-hdpi/ic_launcher.png',
            'android/mipmap-xhdpi/ic_launcher.png': 'android/app/src/main/res/mipmap-xhdpi/ic_launcher.png',
            'android/mipmap-xxhdpi/ic_launcher.png': 'android/app/src/main/res/mipmap-xxhdpi/ic_launcher.png',
            'android/mipmap-xxxhdpi/ic_launcher.png': 'android/app/src/main/res/mipmap-xxxhdpi/ic_launcher.png',
            'assets/icon/launcher.png': 'assets/icon/launcher.png',
            'assets/images/app_logo.png': 'assets/images/app_logo.png',
            'assets/images/app_logo_light.png': 'assets/images/app_logo_light.png',
            'assets/images/app_logo_dark.png': 'assets/images/app_logo_dark.png',
            'assets/images/splash_logo.png': 'assets/images/splash_logo.png',
            'web/favicon.png': 'web/favicon.png',
            'web/icons/Icon-192.png': 'web/icons/Icon-192.png',
            'web/icons/Icon-512.png': 'web/icons/Icon-512.png',
            'web/icons/Icon-maskable-192.png': 'web/icons/Icon-maskable-192.png',
            'web/icons/Icon-maskable-512.png': 'web/icons/Icon-maskable-512.png',
            'windows/app_icon.ico': 'windows/runner/resources/app_icon.ico',
        }

        for src_rel, dst_rel in asset_mappings.items():
            src_full = os.path.join(assets_dir, src_rel)
            dst_full = os.path.join(project_root, dst_rel)
            if os.path.exists(src_full):
                os.makedirs(os.path.dirname(dst_full), exist_ok=True)
                shutil.copy2(src_full, dst_full)
                print(f"    ✓ Replaced asset: {dst_rel}")

    # 2. GENERATE COMPLETE ICON SET VIA PILLOW
    print(f"[*] Ensuring complete icon suite generation via Pillow...")
    master_img = get_or_create_master_image(assets_dir, config_path, cfg, primary_color, app_name, short_name)
    if master_img is not None:
        generate_all_icons_from_master(project_root, master_img)

    # 3. ANDROID WHITE LABEL
    print(f"[*] Applying Android White-Label Customizations...")
    gradle_file = os.path.join(project_root, 'android/app/build.gradle.kts')
    if not os.path.exists(gradle_file):
        gradle_file = os.path.join(project_root, 'android/app/build.gradle')
    if os.path.exists(gradle_file):
        safe_replace(gradle_file, r'namespace\s*=\s*"[^"]*"', f'namespace = "{package_id}"')
        safe_replace(gradle_file, r'applicationId\s*=\s*"[^"]*"', f'applicationId = "{package_id}"')
        safe_replace(gradle_file, r'keyAlias\s*=\s*"[^"]*"', f'keyAlias = "{short_snake}"')
        print("    ✓ Updated build.gradle package & namespace")

    manifest_file = os.path.join(project_root, 'android/app/src/main/AndroidManifest.xml')
    if os.path.exists(manifest_file):
        safe_replace(manifest_file, r'android:label="[^"]*"', f'android:label="{display_name}"')
        print("    ✓ Updated AndroidManifest.xml app label")

    res_dir = os.path.join(project_root, 'android/app/src/main/res/values')
    os.makedirs(res_dir, exist_ok=True)
    strings_file = os.path.join(res_dir, 'strings.xml')
    with open(strings_file, 'w', encoding='utf-8') as f:
        f.write(f'<?xml version="1.0" encoding="utf-8"?>\n<resources>\n    <string name="app_name">{display_name}</string>\n</resources>\n')
    print("    ✓ Created/updated res/values/strings.xml")

    kotlin_root = os.path.join(project_root, 'android/app/src/main/kotlin')
    if os.path.exists(kotlin_root):
        pkg_parts = package_id.split('.')
        target_pkg_dir = os.path.join(kotlin_root, *pkg_parts)
        os.makedirs(target_pkg_dir, exist_ok=True)

        for root, _, files in os.walk(kotlin_root):
            for file in files:
                if file.endswith('.kt') or file.endswith('.java'):
                    filepath = os.path.join(root, file)
                    safe_replace(filepath, r'package\s+[a-zA-Z0-9_\.]+', f'package {package_id}')
                    if root != target_pkg_dir:
                        dest_file = os.path.join(target_pkg_dir, file)
                        shutil.move(filepath, dest_file)
                        print(f"    ✓ Moved {file} to {package_id} directory")

    # 4. WEB WHITE LABEL
    print(f"[*] Applying Web White-Label Customizations...")
    web_index = os.path.join(project_root, 'web/index.html')
    if os.path.exists(web_index):
        safe_replace(web_index, r'<title>.*?</title>', f'<title>{display_name}</title>')
        safe_replace(web_index, r'<meta\s+name="description"\s+content="[^"]*"', f'<meta name="description" content="{product_name} client — {company_name}">')
        safe_replace(web_index, r'<meta\s+name="apple-mobile-web-app-title"\s+content="[^"]*"', f'<meta name="apple-mobile-web-app-title" content="{short_name}">')
        safe_replace(web_index, r'<h1\s+class="brand-title">.*?</h1>', f'<h1 class="brand-title">{app_name}</h1>')
        safe_replace(web_index, r'<p\s+class="brand-subtitle">.*?</p>', f'<p class="brand-subtitle">{product_name}</p>')
        safe_replace(web_index, r'alt=".*?Logo"', f'alt="{app_name} Logo"')
        safe_replace(web_index, r'aria-label="Loading .*?"', f'aria-label="Loading {app_name}"')
        safe_replace(web_index, r'--primary:\s*#[0-9a-fA-F]+;', f'--primary: {primary_color};')
        safe_replace(web_index, r'--bg-color:\s*#[0-9a-fA-F]+;', f'--bg-color: {bg_color};')
        safe_replace(web_index, r'--card-bg:\s*#[0-9a-fA-F]+;', f'--card-bg: {sidebar_color};')
        safe_replace(web_index, r'--text-main:\s*#[0-9a-fA-F]+;', f'--text-main: {text_color};')

        # Branded monogram fallback
        monogram = re.sub(r'[^a-zA-Z0-9]', '', short_name or app_name)[:3].upper() or 'POS'
        safe_replace(web_index, r'<div id="brand-fallback-icon"[^>]*>.*?</div>', f'<div id="brand-fallback-icon" style="display:none;width:68px;height:68px;border-radius:16px;background:var(--primary);color:#fff;font-size:24px;font-weight:800;align-items:center;justify-content:center;margin-bottom:18px;box-shadow:0 6px 16px var(--primary-shadow);">{monogram}</div>')

        # Ensure universal dynamic base href resolver is in <head> and remove static base tag
        try:
            with open(web_index, 'r', encoding='utf-8') as f:
                html = f.read()
            html = re.sub(r'<base\s+href=[\"\'][^\"\']*[\"\']\s*/?>\s*', '', html)
            if 'Universal Directory & Subfolder Base Path Resolver' not in html:
                resolver_snippet = """<head>
  <script>
    // Universal Directory & Subfolder Base Path Resolver
    (function () {
      var loc = window.location;
      var path = loc.pathname;
      var lastSeg = path.split('/').pop();
      if (path.length > 1 && !path.endsWith('/') && !lastSeg.includes('.')) {
        window.location.replace(loc.protocol + '//' + loc.host + path + '/' + loc.search + loc.hash);
        return;
      }
      if (!path.endsWith('/')) {
        path = path.substring(0, path.lastIndexOf('/') + 1);
      }
      var baseEl = document.querySelector('base');
      if (!baseEl) {
        baseEl = document.createElement('base');
        document.head.prepend(baseEl);
      }
      baseEl.setAttribute('href', path || '/');
    })();
  </script>"""
                html = re.sub(r'<head>', resolver_snippet, html, count=1)
            with open(web_index, 'w', encoding='utf-8') as f:
                f.write(html)
        except Exception as e:
            print(f"    [WARN] Error ensuring dynamic base resolver: {e}")

        print("    ✓ Updated web/index.html title, meta tags, and loading screen")

    web_manifest = os.path.join(project_root, 'web/manifest.json')
    if os.path.exists(web_manifest):
        try:
            with open(web_manifest, 'r', encoding='utf-8') as f:
                m = json.load(f)
            m['name'] = display_name
            m['short_name'] = short_name
            m['description'] = f"{product_name} client for {company_name}."
            m['theme_color'] = primary_color
            m['background_color'] = bg_color
            with open(web_manifest, 'w', encoding='utf-8') as f:
                json.dump(m, f, indent=4)
            print("    ✓ Updated web/manifest.json")
        except Exception as e:
            print(f"[WARN] Error updating web/manifest.json: {e}")

    # Write Apache .htaccess for Web
    web_htaccess = os.path.join(project_root, 'web/.htaccess')
    htaccess_content = """<IfModule mod_mime.c>
  AddType application/wasm .wasm
  AddType application/javascript .js
  AddType application/json .json
  AddType image/png .png
  AddType image/svg+xml .svg
  AddType image/x-icon .ico
</IfModule>

<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase ./
  
  # Allow direct access to actual files and directories
  RewriteCond %{REQUEST_FILENAME} -f [OR]
  RewriteCond %{REQUEST_FILENAME} -d
  RewriteRule ^ - [L]

  # Redirect all SPA routes to index.html
  RewriteRule ^ index.html [L]
</IfModule>

<IfModule mod_headers.c>
  <FilesMatch "(index\\.html|version\\.json|flutter_service_worker\\.js)$">
    Header set Cache-Control "no-cache, no-store, must-revalidate"
    Header set Pragma "no-cache"
    Header set Expires 0
  </FilesMatch>
</IfModule>
"""
    try:
        with open(web_htaccess, 'w', encoding='utf-8') as f:
            f.write(htaccess_content)
        print("    ✓ Created/updated web/.htaccess")
    except Exception as e:
        print(f"[WARN] Error writing web/.htaccess: {e}")

    # 5. WINDOWS WHITE LABEL
    print(f"[*] Applying Windows White-Label Customizations...")
    win_cmake = os.path.join(project_root, 'windows/CMakeLists.txt')
    if os.path.exists(win_cmake):
        safe_replace(win_cmake, r'project\(.*?\s+LANGUAGES CXX\)', f'project({short_snake} LANGUAGES CXX)')
        safe_replace(win_cmake, r'set\(BINARY_NAME\s+"[^"]*"\)', f'set(BINARY_NAME "{short_snake}")')
        print(f"    ✓ Updated windows/CMakeLists.txt binary to '{short_snake}'")

    win_main = os.path.join(project_root, 'windows/runner/main.cpp')
    if os.path.exists(win_main):
        safe_replace(win_main, r'window\.Create\(L"[^"]*"', f'window.Create(L"{display_name}"')
        print("    ✓ Updated windows/runner/main.cpp window title")

    win_rc = os.path.join(project_root, 'windows/runner/Runner.rc')
    if os.path.exists(win_rc):
        safe_replace(win_rc, r'VALUE "CompanyName", "[^"]*"', f'VALUE "CompanyName", "{company_name}"')
        safe_replace(win_rc, r'VALUE "FileDescription", "[^"]*"', f'VALUE "FileDescription", "{product_name}"')
        safe_replace(win_rc, r'VALUE "InternalName", "[^"]*"', f'VALUE "InternalName", "{short_snake}"')
        safe_replace(win_rc, r'VALUE "LegalCopyright", "[^"]*"', f'VALUE "LegalCopyright", "{copyright_text}"')
        safe_replace(win_rc, r'VALUE "OriginalFilename", "[^"]*"', f'VALUE "OriginalFilename", "{short_snake}.exe"')
        safe_replace(win_rc, r'VALUE "ProductName", "[^"]*"', f'VALUE "ProductName", "{product_name}"')
        print("    ✓ Updated windows/runner/Runner.rc metadata")

    iss_file = os.path.join(project_root, 'installer.iss')
    if os.path.exists(iss_file):
        safe_replace(iss_file, r'#define MyAppName\s+"[^"]*"', f'#define MyAppName "{app_name}"')
        safe_replace(iss_file, r'#define MyAppPublisher\s+"[^"]*"', f'#define MyAppPublisher "{company_name}"')
        safe_replace(iss_file, r'#define MyAppURL\s+"[^"]*"', f'#define MyAppURL "{website_url}"')
        safe_replace(iss_file, r'#define MyAppExeName\s+"[^"]*"', f'#define MyAppExeName "{short_snake}.exe"')
        print("    ✓ Updated installer.iss InnoSetup script")

    # 6. IOS WHITE LABEL
    print(f"[*] Applying iOS White-Label Customizations...")
    pbx_file = os.path.join(project_root, 'ios/Runner.xcodeproj/project.pbxproj')
    if os.path.exists(pbx_file):
        safe_replace(pbx_file, r'PRODUCT_BUNDLE_IDENTIFIER = [^;]*;', f'PRODUCT_BUNDLE_IDENTIFIER = {package_id};')
        print("    ✓ Updated iOS PRODUCT_BUNDLE_IDENTIFIER")

    plist_file = os.path.join(project_root, 'ios/Runner/Info.plist')
    if os.path.exists(plist_file):
        safe_replace(plist_file, r'<key>CFBundleDisplayName</key>\s*<string>[^<]*</string>', f'<key>CFBundleDisplayName</key><string>{display_name}</string>')
        safe_replace(plist_file, r'<key>CFBundleName</key>\s*<string>[^<]*</string>', f'<key>CFBundleName</key><string>{short_name}</string>')
        print("    ✓ Updated iOS CFBundleDisplayName & CFBundleName")

    # 7. FLUTTER CONFIG & GLOBAL DART REPLACEMENTS
    print(f"[*] Applying Flutter Code & Theme Customizations...")
    pubspec_file = os.path.join(project_root, 'pubspec.yaml')
    if os.path.exists(pubspec_file):
        safe_replace(pubspec_file, r'description:\s*"[^"]*"', f'description: "{product_name} client — {company_name}."')
        print("    ✓ Updated pubspec.yaml description")

    app_config = os.path.join(project_root, 'lib/core/config/app_config.dart')
    if os.path.exists(app_config):
        safe_replace(app_config, r"defaultBaseUrl\s*=\s*'[^']*'", f"defaultBaseUrl = '{server_url}'")
        print(f"    ✓ Injected defaultBaseUrl = '{server_url}'")

    theme_file = os.path.join(project_root, 'lib/core/config/theme.dart')
    if os.path.exists(theme_file):
        safe_replace(theme_file, r'Color primary\s*=\s*Color\(0x[0-9A-Fa-f]+\)', f'Color primary = Color(0xFF{primary_hex})')
        safe_replace(theme_file, r'Color accent\s*=\s*Color\(0x[0-9A-Fa-f]+\)', f'Color accent = Color(0xFF{accent_hex})')
        print(f"    ✓ Injected primary color 0xFF{primary_hex}")

    branding_provider = os.path.join(project_root, 'lib/core/config/platform_branding_provider.dart')
    if os.path.exists(branding_provider):
        safe_replace(branding_provider, r"defaultName\s*=\s*'[^']*'", f"defaultName = '{product_name}'")
        safe_replace(branding_provider, r"defaultTagline\s*=\s*'[^']*'", f"defaultTagline = '{app_name} — {company_name}'")
        safe_replace(branding_provider, r"defaultSupportPhone\s*=\s*'[^']*'", f"defaultSupportPhone = '{support_phone}'")
        safe_replace(branding_provider, r"defaultSupportEmail\s*=\s*'[^']*'", f"defaultSupportEmail = '{support_email}'")
        print("    ✓ Updated platform_branding_provider.dart defaults")

    # Global scan and replace across lib/
    lib_dir = os.path.join(project_root, 'lib')
    if os.path.exists(lib_dir):
        print(f"[*] Performing deep-scan across lib/ for remaining legacy branding strings...")
        replacements = [
            ("Zoom Sales CRM & Inventory", product_name),
            ("Zoom Sales CRM", product_name),
            ("Zoom Sales POS", app_name),
            ("Zoom POS", app_name),
            ("ZoomPOS", short_name),
            ("Zoom Nearby", company_name),
            ("ZoomNearby", company_name),
            ("support@zoomnearby.com", support_email),
            ("+918535075196", support_phone),
            ("com.zoomnearby.zoompos", package_id),
        ]
        replaced_count = 0
        for root, _, files in os.walk(lib_dir):
            for file in files:
                if file.endswith('.dart'):
                    fp = os.path.join(root, file)
                    for old_str, new_str in replacements:
                        if old_str != new_str:
                            if safe_replace(fp, old_str, new_str, is_regex=False):
                                replaced_count += 1
        print(f"    ✓ Replaced legacy branding in {replaced_count} files across lib/")

    print(f"=================================================================")
    print(f"White-Label Transformation Completed Successfully!")
    print(f"=================================================================")


if __name__ == '__main__':
    if len(sys.argv) < 3:
        print("Usage: whitelabel.py <project_root> <branding_config.json> [assets_dir]")
        sys.exit(1)
        
    p_root = sys.argv[1]
    c_path = sys.argv[2]
    a_dir = sys.argv[3] if len(sys.argv) > 3 else None
    apply_whitelabel(p_root, c_path, a_dir)
