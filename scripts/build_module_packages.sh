#!/usr/bin/env bash
#
# Build installable ZIP packages for the standalone vertical modules.
#
#   scripts/build_module_packages.sh            # build all zips + master bundle
#   scripts/build_module_packages.sh reset      # wipe these modules from THIS install (DB + files)
#
# Sources live in   module-packages/<key>/      (git-tracked)
# Output goes to     storage/app/module-dist/   (git-ignored build artifacts)
#
# Each <key>.zip has module.json at its root, exactly as
# App\Services\Modular\ModulePackageService::install() expects. The master
# bundle all-modules-clean.zip simply contains the individual module zips.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC_DIR="$ROOT/module-packages"
OUT_DIR="$ROOT/storage/app/module-dist"
MASTER="all-modules-clean.zip"

# Keys we manage here. Add a directory under module-packages/ and list it here.
MODULES=(pharmacy repairtechnician salon)

sha() { command -v sha256sum >/dev/null 2>&1 && sha256sum "$1" | awk '{print $1}' || shasum -a 256 "$1" | awk '{print $1}'; }

cmd_build() {
    command -v zip >/dev/null 2>&1 || { echo "ERROR: 'zip' is not installed."; exit 1; }

    mkdir -p "$OUT_DIR"
    rm -f "$OUT_DIR"/*.zip

    for key in "${MODULES[@]}"; do
        local dir="$SRC_DIR/$key"
        [ -f "$dir/module.json" ] || { echo "ERROR: $dir/module.json not found"; exit 1; }

        echo "==> packaging $key"
        ( cd "$dir" && zip -qr "$OUT_DIR/$key.zip" . \
            -x '*.git*' -x '*/node_modules/*' -x '*/vendor/*' -x '.DS_Store' -x '*/.DS_Store' )
        echo "    $(basename "$OUT_DIR/$key.zip")  $(du -h "$OUT_DIR/$key.zip" | cut -f1)  sha256:$(sha "$OUT_DIR/$key.zip")"
    done

    echo "==> master bundle"
    ( cd "$OUT_DIR" && zip -qr "$MASTER" ./*.zip -x "$MASTER" )
    echo "    $MASTER  $(du -h "$OUT_DIR/$MASTER" | cut -f1)  sha256:$(sha "$OUT_DIR/$MASTER")"

    echo
    echo "Done. Upload each <key>.zip at Super Admin -> Modules, or hand over"
    echo "$OUT_DIR/$MASTER"
}

cmd_reset() {
    echo "This removes the packaged modules from THIS installation:"
    echo "  - deletes sdui_modules rows with slug in: ${MODULES[*]}"
    echo "  - drops their pharmacy_mod_* / repair_mod_* / salon_mod_* tables"
    echo "  - deletes modules/<key>/ directories"
    read -r -p "Type 'wipe' to continue: " confirm
    [ "$confirm" = "wipe" ] || { echo "Aborted."; exit 1; }

    php "$ROOT/artisan" tinker --execute='
        $slugs = ["pharmacy", "repairtechnician", "salon"];
        foreach ([
            "pharmacy_mod_prescription_items","pharmacy_mod_prescriptions","pharmacy_mod_drug_batches",
            "repair_mod_ticket_items","repair_mod_tickets","repair_mod_device_categories",
            "salon_mod_appointments","salon_mod_stylists","salon_mod_services",
        ] as $t) {
            if (Schema::hasTable($t)) { Schema::drop($t); echo "dropped $t\n"; }
        }
        if (Schema::hasTable("sdui_modules")) {
            $n = App\Models\SduiModule::whereIn("slug", $slugs)->where("source_type","package")->delete();
            echo "deleted $n sdui_modules rows\n";
        }
    '
    for key in "${MODULES[@]}"; do
        rm -rf "$ROOT/modules/$key" && echo "removed modules/$key"
    done
    echo "Reset complete. System is clean for a fresh install."
}

case "${1:-build}" in
    build) cmd_build ;;
    reset) cmd_reset ;;
    *) echo "usage: $0 [build|reset]"; exit 1 ;;
esac
