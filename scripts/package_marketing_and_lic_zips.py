#!/usr/bin/env python3
"""
Packaging Script for Marketing Landing Page and License Server Files.
Ensures uniform permissions (dirs 0755, files 0644) and clean archives.
"""

import os
import shutil
import zipfile
import stat

BASE_DIR = "/home/zoomnearby-saas/htdocs/saas.zoomnearby.com"
MARKETING_DIR = os.path.join(BASE_DIR, "public/marketing")
LIC_DIR = os.path.join(BASE_DIR, "lic")

ZIP_TARGETS_MARKETING = [
    os.path.join(BASE_DIR, "public/marketing-landing-page.zip"),
    os.path.join(BASE_DIR, "public/marketing-landing-script.zip"),
    os.path.join(BASE_DIR, "lic/storage/marketing-landing-page.zip"),
    os.path.join(BASE_DIR, "lic/storage/marketing-landing-script.zip"),
]

ZIP_TARGET_LIC = os.path.join(BASE_DIR, "public/license-server-files.zip")

def fix_permissions(root_dir):
    for root, dirs, files in os.walk(root_dir):
        for d in dirs:
            os.chmod(os.path.join(root, d), 0o755)
        for f in files:
            os.chmod(os.path.join(root, f), 0o644)

def create_zip(src_dir, dest_zip, exclude_prefixes=(), exclude_names=()):
    os.makedirs(os.path.dirname(dest_zip), exist_ok=True)
    if os.path.exists(dest_zip):
        os.remove(dest_zip)

    file_count = 0
    with zipfile.ZipFile(dest_zip, 'w', zipfile.ZIP_DEFLATED) as zf:
        for root, dirs, files in sorted(os.walk(src_dir)):
            # Filter dirs
            dirs[:] = [d for d in dirs if d not in exclude_names and not any(d.startswith(p) for p in exclude_prefixes)]
            for file in sorted(files):
                if file in exclude_names or any(file.startswith(p) for p in exclude_prefixes):
                    continue
                full_path = os.path.join(root, file)
                rel_path = os.path.relpath(full_path, src_dir)
                
                # ZipInfo with strict permissions
                zinfo = zipfile.ZipInfo.from_file(full_path, arcname=rel_path)
                zinfo.external_attr = (0o644 & 0xFFFF) << 16
                with open(full_path, 'rb') as f:
                    zf.writestr(zinfo, f.read())
                file_count += 1

    os.chmod(dest_zip, 0o644)
    print(f"Created {dest_zip} ({file_count} files, {os.path.getsize(dest_zip)} bytes)")
    return file_count

def main():
    print("Fixing file permissions...")
    fix_permissions(MARKETING_DIR)
    fix_permissions(LIC_DIR)

    # 1. Package Marketing Landing Page
    print("\nPackaging Marketing Landing Page...")
    primary_marketing_zip = ZIP_TARGETS_MARKETING[0]
    create_zip(
        MARKETING_DIR,
        primary_marketing_zip,
        exclude_prefixes=('.', ),
        exclude_names=('__pycache__', )
    )
    # Re-add .htaccess specifically for Apache / Hostinger
    htaccess_path = os.path.join(MARKETING_DIR, ".htaccess")
    if os.path.exists(htaccess_path):
        with zipfile.ZipFile(primary_marketing_zip, 'a', zipfile.ZIP_DEFLATED) as zf:
            zinfo = zipfile.ZipInfo.from_file(htaccess_path, arcname=".htaccess")
            zinfo.external_attr = (0o644 & 0xFFFF) << 16
            with open(htaccess_path, 'rb') as f:
                zf.writestr(zinfo, f.read())
        print(f"Appended .htaccess to {primary_marketing_zip}")

    # Copy to the other targets
    for target in ZIP_TARGETS_MARKETING[1:]:
        os.makedirs(os.path.dirname(target), exist_ok=True)
        shutil.copy2(primary_marketing_zip, target)
        os.chmod(target, 0o644)
        print(f"Copied to {target}")

    # 2. Package License Server Files
    print("\nPackaging License Server Files...")
    create_zip(
        LIC_DIR,
        ZIP_TARGET_LIC,
        exclude_prefixes=('.', ),
        exclude_names=('storage', '__pycache__', 'read')
    )
    # Include storage dir with minimal empty directory structure or htaccess
    storage_htaccess = os.path.join(LIC_DIR, "storage/.htaccess")
    if os.path.exists(storage_htaccess):
        with zipfile.ZipFile(ZIP_TARGET_LIC, 'a', zipfile.ZIP_DEFLATED) as zf:
            zinfo = zipfile.ZipInfo.from_file(storage_htaccess, arcname="storage/.htaccess")
            zinfo.external_attr = (0o644 & 0xFFFF) << 16
            with open(storage_htaccess, 'rb') as f:
                zf.writestr(zinfo, f.read())
    
    # Also add root .htaccess if present
    lic_htaccess = os.path.join(LIC_DIR, ".htaccess")
    if os.path.exists(lic_htaccess):
        with zipfile.ZipFile(ZIP_TARGET_LIC, 'a', zipfile.ZIP_DEFLATED) as zf:
            zinfo = zipfile.ZipInfo.from_file(lic_htaccess, arcname=".htaccess")
            zinfo.external_attr = (0o644 & 0xFFFF) << 16
            with open(lic_htaccess, 'rb') as f:
                zf.writestr(zinfo, f.read())

    print("\nAll packages created successfully!")

if __name__ == '__main__':
    main()
