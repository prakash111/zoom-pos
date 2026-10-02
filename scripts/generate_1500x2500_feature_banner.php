<?php
/**
 * 1500x2500 Feature Showcase Banner Generator
 * Ideal for medium-length marketplace descriptions.
 * Covers Cloud App Builder, $49 vs $119 Plans, Core POS, Verticals, and Offline Sync.
 */

ini_set('memory_limit', '1024M');
set_time_limit(300);

echo "Starting 1500x2500 feature banner generation...\n";

$tw = 1500;
$th = 2500;

$canvas = imagecreatetruecolor($tw, $th);

// Gradient background
for ($y = 0; $y < $th; $y++) {
    $r = (int)(8 + 4 * sin($y / 500.0));
    $g = (int)(12 + 5 * cos($y / 600.0));
    $b = (int)(26 + 8 * sin($y / 550.0));
    $col = imagecolorallocate($canvas, max(4, $r), max(8, $g), max(18, $b));
    imageline($canvas, 0, $y, $tw, $y, $col);
}

$fontBold = "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf";
$fontReg  = "/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf";

// Colors
$white        = imagecolorallocate($canvas, 255, 255, 255);
$offWhite     = imagecolorallocate($canvas, 241, 245, 249);
$muted        = imagecolorallocate($canvas, 148, 163, 184);
$cyan         = imagecolorallocate($canvas, 56, 189, 248);
$purple       = imagecolorallocate($canvas, 168, 85, 247);
$gold         = imagecolorallocate($canvas, 251, 191, 36);
$green        = imagecolorallocate($canvas, 52, 211, 153);
$cardBg       = imagecolorallocatealpha($canvas, 15, 23, 42, 40);
$cardBorder   = imagecolorallocatealpha($canvas, 99, 102, 241, 80);
$cardBorderCyan = imagecolorallocatealpha($canvas, 56, 189, 248, 80);

// Top Bar
imagefilledrectangle($canvas, 0, 0, $tw, 55, imagecolorallocatealpha($canvas, 15, 23, 42, 20));
imageline($canvas, 0, 55, $tw, 55, imagecolorallocatealpha($canvas, 99, 102, 241, 70));
imagettftext($canvas, 12, 0, 50, 36, $cyan, $fontBold, "* ZOOM POS & CRM");
imagettftext($canvas, 10.5, 0, 320, 36, $white, $fontBold, "MULTI-TENANT SAAS");
imagettftext($canvas, 10.5, 0, 600, 36, $gold, $fontBold, "WHITE-LABEL CLOUD APP BUILDER");
imagettftext($canvas, 10.5, 0, 990, 36, $green, $fontBold, "100% OFFLINE SQLITE SYNC");
imagettftext($canvas, 10.5, 0, 1310, 36, $offWhite, $fontBold, "1-CLICK SETUP");

// Hero Title
$h1 = "Multi-Tenant SaaS POS & Cloud App Builder";
$b1 = imagettfbbox(28, 0, $fontBold, $h1);
$x1 = (int)(($tw - ($b1[2] - $b1[0])) / 2);
imagettftext($canvas, 28, 0, $x1, 115, $white, $fontBold, $h1);

$sub = "Launch your own Square, Toast or Clover alternative. Unlimited stores, automated billing & Flutter apps.";
$bSub = imagettfbbox(13, 0, $fontReg, $sub);
$xSub = (int)(($tw - ($bSub[2] - $bSub[0])) / 2);
imagettftext($canvas, 13, 0, $xSub, 150, $muted, $fontReg, $sub);

// Badges
$badges = [
    ["[$$$] $29   $149/mo Recurring MRR", $gold],
    ["[FAST] 0-Code Cloud App Builder", $green],
    ["[MOBILE] Android APK & Windows EXE", $cyan],
    ["[OPEN-SOURCE] 100% Open Source Code", $purple]
];
$totalW = 0; $boxes = [];
foreach ($badges as $b) {
    $box = imagettfbbox(10, 0, $fontBold, "  " . $b[0] . "  ");
    $w = $box[2] - $box[0] + 16;
    $boxes[] = $w;
    $totalW += $w + 16;
}
$curX = (int)(($tw - $totalW) / 2);
foreach ($badges as $idx => $b) {
    $bw = $boxes[$idx];
    imagefilledrectangle($canvas, $curX, 175, $curX + $bw, 208, imagecolorallocatealpha($canvas, 15, 23, 42, 40));
    imagerectangle($canvas, $curX, 175, $curX + $bw, 208, $b[1]);
    imagettftext($canvas, 9.5, 0, $curX + 8, 197, $b[1], $fontBold, "  " . $b[0]);
    $curX += $bw + 16;
}

// 1. CLOUD APP BUILDER HIGHLIGHT (Y: 230 - 640)
imagefilledrectangle($canvas, 60, 230, 1440, 640, imagecolorallocatealpha($canvas, 15, 23, 42, 50));
imagerectangle($canvas, 60, 230, 1440, 640, $cardBorderCyan);

imagettftext($canvas, 16, 0, 90, 275, $cyan, $fontBold, "[FAST] WHITE-LABEL CLOUD APP BUILDER     ZERO FLUTTER SETUP REQUIRED");
imagettftext($canvas, 12, 0, 90, 305, $muted, $fontReg, "No Android Studio, Java JDK, or Flutter SDK needed. Compile custom-branded apps via automated GitHub Actions cloud runners.");

$bCards = [
    ["0-Code Cloud Compilation", "Automated CI/CD compiles APK, AAB & Windows EXE in cloud runners.", $cyan],
    ["Multi-Platform Outputs", "Android APK for sideload, Google Play AAB, Windows Desktop & Web PWA.", $purple],
    ["100% White-Label Branding", "Custom App Name, Package ID (com.client.pos), Logo & Hex Colors.", $green],
    ["Live Logs & Email Delivery", "Real-time build logs, status webhooks & email alert with direct download.", $gold]
];
$bcW = 630; $bcH = 110;
foreach ($bCards as $idx => $bc) {
    $col = $idx % 2;
    $row = (int)floor($idx / 2);
    $bx = 90 + ($col * ($bcW + 30));
    $by = 330 + ($row * ($bcH + 18));
    imagefilledrectangle($canvas, $bx, $by, $bx + $bcW, $by + $bcH, imagecolorallocatealpha($canvas, 30, 41, 59, 70));
    imagerectangle($canvas, $bx, $by, $bx + $bcW, $by + $bcH, $bc[2]);
    imagettftext($canvas, 12.5, 0, $bx + 18, $by + 30, $bc[2], $fontBold, $bc[0]);
    imagettftext($canvas, 11, 0, $bx + 18, $by + 60, $offWhite, $fontReg, $bc[1]);
}

// 2. PRICING COMPARISON: $49 VS $119 (Y: 665 - 1280)
imagefilledrectangle($canvas, 60, 665, 1440, 1280, imagecolorallocatealpha($canvas, 15, 23, 42, 50));
imagerectangle($canvas, 60, 665, 1440, 1280, imagecolorallocatealpha($canvas, 251, 191, 36, 80));

imagettftext($canvas, 16, 0, 90, 710, $gold, $fontBold, "[$$$] PRICING STRATEGY: $49 CORE POS VS $119 ALL-IN-ONE ENTERPRISE BUNDLE");
imagettftext($canvas, 12, 0, 90, 740, $muted, $fontReg, "Choose the license plan that matches your business model. Both include 100% unencrypted code with zero royalties.");

// $49 Box
$pBoxW = 630; $pBoxH = 500;
imagefilledrectangle($canvas, 90, 765, 90 + $pBoxW, 765 + $pBoxH, imagecolorallocatealpha($canvas, 20, 30, 50, 70));
imagerectangle($canvas, 90, 765, 90 + $pBoxW, 765 + $pBoxH, $cardBorder);

imagettftext($canvas, 12, 0, 115, 800, $muted, $fontBold, "STARTER SAAS / SINGLE STORE");
imagettftext($canvas, 26, 0, 115, 840, $white, $fontBold, "$49");
imagettftext($canvas, 12, 0, 180, 840, $muted, $fontReg, "One-Time - Lifetime License");

$p1Lines = [
    "+ Full Multi-Tenant Core POS Engine",
    "+ Retail POS Module & Barcode Scanning",
    "+ Restaurant & Caf   with Floor Tables & KOT",
    "+ Kitchen Display System (KDS) Live Order Station",
    "+ Offline-First SQLite with Cloud Sync",
    "+ ESC/POS Thermal Printing (58mm & 80mm)",
    "+ Customer Online Web Storefront",
    "+ WhatsApp Cloud API & SMS Messaging",
    "+ SuperAdmin Panel & Stripe Billing",
    "+ 1 Cloud App Build / Month Quota"
];
$ly = 880;
foreach ($p1Lines as $l) {
    imagettftext($canvas, 11, 0, 115, $ly, $offWhite, $fontReg, $l);
    $ly += 33;
}

// $119 Box (Golden)
$p2x = 90 + $pBoxW + 30;
imagefilledrectangle($canvas, $p2x, 765, $p2x + $pBoxW, 765 + $pBoxH, imagecolorallocatealpha($canvas, 25, 35, 70, 70));
imagerectangle($canvas, $p2x, 765, $p2x + $pBoxW, 765 + $pBoxH, $gold);

imagettftext($canvas, 12, 0, $p2x + 25, 800, $gold, $fontBold, "SAAS FOUNDER & AGENCY BUNDLE");
imagettftext($canvas, 26, 0, $p2x + 25, 840, $gold, $fontBold, "$119");
imagettftext($canvas, 12, 0, $p2x + 95, 840, $muted, $fontReg, "One-Time - Best Value");

$p2Lines = [
    "* EVERYTHING IN CORE POS PLAN INCLUDED",
    "* 10 Cloud App Builds / Month (10X Mobile Quota!)",
    "* Google Play Ready AAB (.aab) + Release APK",
    "* Windows Desktop Native (.exe) Client",
    "* Full White-Label Branding (Name, Package, Logo)",
    "* ALL 4 VERTICAL MODULES INCLUDED ($200+ Value):",
    "   - Lead Management CRM (Kanban Pipeline & Deals)",
    "   - Pharmacy POS (Batches, Expiry & Prescriptions)",
    "   - Salon & Spa System (Stylist Calendar & Fees)",
    "   - Repair Service Workbench (Intake Tickets & Parts)",
    "* VIP Deployment Support & Server Setup Assistance",
    "* Priority WhatsApp & Email Developer Support"
];
$ly2 = 880;
foreach ($p2Lines as $l) {
    $c = strpos($l, "*") !== false ? $gold : $offWhite;
    imagettftext($canvas, 11, 0, $p2x + 25, $ly2, $c, $fontReg, $l);
    $ly2 += 33;
}

// 3. CORE POS & 5 INDUSTRY VERTICALS (Y: 1305 - 1920)
imagefilledrectangle($canvas, 60, 1305, 1440, 1920, imagecolorallocatealpha($canvas, 15, 23, 42, 50));
imagerectangle($canvas, 60, 1305, 1440, 1920, $cardBorder);

imagettftext($canvas, 16, 0, 90, 1350, $purple, $fontBold, "[STORE] CORE POS & 5 SPECIALIZED INDUSTRY VERTICAL MODULES");
imagettftext($canvas, 12, 0, 90, 1380, $muted, $fontReg, "One unified platform designed for multiple commercial business types.");

$vCards = [
    ["[RETAIL] Retail & Supermarkets", "Barcode scanning, weigh-scale barcodes, multi-variants (Size/Color), thermal shelf labels, customer credit ledgers.", $cyan],
    ["[DINING] Restaurant, Caf   & Bar", "Visual floor tables, KDS kitchen display, KOT thermal printing, split bills by seat, contactless QR table ordering.", $gold],
    ["[PHARMACY] Pharmacy & Healthcare", "Drug batch tracking, expiry date alerts, prescription photo intake, prescription checkout, generic drug directory.", $green],
    ["[SALON] Salon, Spa & Wellness", "Visual stylist booking calendar, duration time blocks, chair rosters, automated commission tracking, hybrid invoices.", $purple],
    ["[REPAIR] Repair Service Workshop", "Device intake tickets, diagnostic checklists, technician assignment, spare parts usage, and customer self-tracking portal.", $cyan],
    ["[CRM] Lead Management CRM", "Visual Kanban deal pipeline, interaction logging, follow-up reminders, and 1-click lead to quotation/invoice conversion.", $gold]
];
$vcW = 425; $vcH = 140;
foreach ($vCards as $idx => $vc) {
    $col = $idx % 3;
    $row = (int)floor($idx / 3);
    $vx = 90 + ($col * ($vcW + 22));
    $vy = 1410 + ($row * ($vcH + 16));
    imagefilledrectangle($canvas, $vx, $vy, $vx + $vcW, $vy + $vcH, imagecolorallocatealpha($canvas, 30, 41, 59, 70));
    imagerectangle($canvas, $vx, $vy, $vx + $vcW, $vy + $vcH, $vc[2]);
    imagettftext($canvas, 12.5, 0, $vx + 16, $vy + 30, $vc[2], $fontBold, $vc[0]);
    
    // Desc
    $words = explode(" ", $vc[1]);
    $lines = []; $curLine = "";
    foreach ($words as $w) {
        $testLine = $curLine === "" ? $w : $curLine . " " . $w;
        $box = imagettfbbox(10, 0, $fontReg, $testLine);
        if ($box[2] - $box[0] > ($vcW - 32)) {
            $lines[] = $curLine;
            $curLine = $w;
        } else {
            $curLine = $testLine;
        }
    }
    if ($curLine !== "") $lines[] = $curLine;
    $lY = $vy + 60;
    foreach ($lines as $line) {
        imagettftext($canvas, 10, 0, $vx + 16, $lY, $offWhite, $fontReg, $line);
        $lY += 19;
    }
}

// Multi-Store Bar
imagefilledrectangle($canvas, 90, 1750, 1410, 1890, imagecolorallocatealpha($canvas, 20, 30, 60, 80));
imagerectangle($canvas, 90, 1750, 1410, 1890, $green);
imagettftext($canvas, 13, 0, 115, 1790, $green, $fontBold, "     Multi-Store & Central Warehousing Engine Included:");
imagettftext($canvas, 11, 0, 115, 1825, $offWhite, $fontReg, "- Unlimited outlets & regional fulfillment warehouses - 3-step inter-branch stock transfers (Request -> Dispatch -> Receive)");
imagettftext($canvas, 11, 0, 115, 1855, $muted, $fontReg, "- Store-specific pricing - Low stock alerts - Bulk Excel/CSV catalog import/export - Supplier POs and Goods Received Notes (GRN)");


// 4. PLATFORM PILLARS & ARCHITECTURE (Y: 1945 - 2320)
imagefilledrectangle($canvas, 60, 1945, 1440, 2320, imagecolorallocatealpha($canvas, 15, 23, 42, 50));
imagerectangle($canvas, 60, 1945, 1440, 2320, $cardBorderCyan);

imagettftext($canvas, 15, 0, 90, 1985, $cyan, $fontBold, "[TECH] CORE ARCHITECTURE, OFFLINE SYNC, HARDWARE & SAAS ENGINE");

$archBoxes = [
    ["100% Offline SQLite Sync", "Uninterrupted POS checkout during internet drops. Encrypted local SQLite with automatic bi-directional cloud sync.", $cyan],
    ["Universal ESC/POS Printing", "Direct bytecode to 58mm/80mm printers via USB, Bluetooth & LAN. Cash drawer kick pulse & barcode label designer.", $gold],
    ["Omnichannel Notifications", "Meta WhatsApp Cloud API, Twilio & generic HTTP SMS gateway, automated event crons & branded SMTP email.", $green],
    ["SuperAdmin & Multi-Tenancy", "Automated recurring subscription billing via Stripe, PayPal & Razorpay. 1-click tenant impersonation & apex domains.", $purple]
];
$abW = 630; $abH = 110;
foreach ($archBoxes as $idx => $ab) {
    $col = $idx % 2;
    $row = (int)floor($idx / 2);
    $ax = 90 + ($col * ($abW + 30));
    $ay = 2015 + ($row * ($abH + 16));
    imagefilledrectangle($canvas, $ax, $ay, $ax + $abW, $ay + $abH, imagecolorallocatealpha($canvas, 30, 41, 59, 70));
    imagerectangle($canvas, $ax, $ay, $ax + $abW, $ay + $abH, $ab[2]);
    imagettftext($canvas, 12.5, 0, $ax + 18, $ay + 30, $ab[2], $fontBold, $ab[0]);
    imagettftext($canvas, 10.5, 0, $ax + 18, $ay + 60, $offWhite, $fontReg, $ab[1]);
}

// 5. FOOTER CALL TO ACTION (Y: 2345 - 2500)
imagefilledrectangle($canvas, 0, 2345, $tw, $th, imagecolorallocatealpha($canvas, 10, 16, 36, 10));
imageline($canvas, 0, 2345, $tw, 2345, imagecolorallocatealpha($canvas, 99, 102, 241, 100));

imagettftext($canvas, 18, 0, 250, 2410, $white, $fontBold, "Launch Your Multi-Tenant POS SaaS Business Today!");
imagettftext($canvas, 11.5, 0, 250, 2445, $muted, $fontReg, "100% Unencrypted Source Code - Laravel 11 - Flutter 3 - MySQL 8 - Zero Recurring Fees to Us");

$dest1500 = [
    "public/assets/images/promotional-banner-1500x2500.jpg",
    "public/documentation/images/promotional-banner-1500x2500.jpg",
    "public/promotional-banner-1500x2500.jpg"
];

echo "Saving 1500x2500 JPEG outputs...\n";
imagejpeg($canvas, $dest1500[0], 92);
foreach (array_slice($dest1500, 1) as $d) {
    @copy($dest1500[0], $d);
}
imagedestroy($canvas);
echo "1500x2500 Banner generated successfully!\n";
