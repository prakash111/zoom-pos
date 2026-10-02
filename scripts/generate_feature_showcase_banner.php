<?php
/**
 * Master Marketplace Feature Showcase Banner Generator
 * Generates an ultra-crisp, comprehensive 2000x10700 infographic banner
 * covering ALL software features, Cloud App Builder, and Pricing comparison ($49 vs $119).
 */

ini_set('memory_limit', '2048M');
set_time_limit(600);

echo "Starting master marketplace feature banner generation...\n";

$tw = 2000;
$th = 10700;

$canvas = imagecreatetruecolor($tw, $th);

// Background gradient: sleek dark navy obsidian #070c1b to #0b132b
for ($y = 0; $y < $th; $y++) {
    $r = (int)(7 + 5 * sin($y / 700.0));
    $g = (int)(11 + 6 * cos($y / 900.0));
    $b = (int)(25 + 9 * sin($y / 800.0));
    $col = imagecolorallocate($canvas, max(4, $r), max(8, $g), max(18, $b));
    imageline($canvas, 0, $y, $tw, $y, $col);
}

$fontBold = "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf";
$fontReg  = "/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf";

// Color Palette
$white        = imagecolorallocate($canvas, 255, 255, 255);
$offWhite     = imagecolorallocate($canvas, 241, 245, 249);
$muted        = imagecolorallocate($canvas, 148, 163, 184);
$darkMuted    = imagecolorallocate($canvas, 100, 116, 139);
$cyan         = imagecolorallocate($canvas, 56, 189, 248);
$brightCyan   = imagecolorallocate($canvas, 14, 165, 233);
$purple       = imagecolorallocate($canvas, 168, 85, 247);
$brightPurple = imagecolorallocate($canvas, 192, 132, 252);
$gold         = imagecolorallocate($canvas, 251, 191, 36);
$green        = imagecolorallocate($canvas, 52, 211, 153);
$emerald      = imagecolorallocate($canvas, 16, 185, 129);
$coral        = imagecolorallocate($canvas, 244, 63, 94);

$cardBg       = imagecolorallocatealpha($canvas, 15, 23, 42, 40);
$cardBorder   = imagecolorallocatealpha($canvas, 99, 102, 241, 80);
$cardBorderCyan = imagecolorallocatealpha($canvas, 56, 189, 248, 80);
$cardBorderGreen= imagecolorallocatealpha($canvas, 16, 185, 129, 80);
$cardBorderGold = imagecolorallocatealpha($canvas, 251, 191, 36, 80);

// Helper: Draw Section Header
function drawSectionHdr($canvas, $y, $stepNum, $tag, $title, $sub, $accentCol, $fontBold, $fontReg, $white, $muted) {
    global $tw;
    
    // Glowing accent pill
    $pillText = "  " . strtoupper($stepNum . " - " . $tag) . "  ";
    $bbox = imagettfbbox(13, 0, $fontBold, $pillText);
    $pw = $bbox[2] - $bbox[0] + 30;
    $px = (int)(($tw - $pw) / 2);
    
    imagefilledrectangle($canvas, $px, $y, $px + $pw, $y + 36, imagecolorallocatealpha($canvas, 15, 23, 42, 30));
    imagerectangle($canvas, $px, $y, $px + $pw, $y + 36, $accentCol);
    imagettftext($canvas, 12, 0, $px + 15, $y + 24, $accentCol, $fontBold, $pillText);
    
    // Main Title (Center aligned)
    $tBox = imagettfbbox(30, 0, $fontBold, $title);
    $tx = (int)(($tw - ($tBox[2] - $tBox[0])) / 2);
    imagettftext($canvas, 30, 0, $tx, $y + 88, $white, $fontBold, $title);
    
    // Sub-title
    $sBox = imagettfbbox(15, 0, $fontReg, $sub);
    $sx = (int)(($tw - ($sBox[2] - $sBox[0])) / 2);
    imagettftext($canvas, 15, 0, $sx, $y + 126, $muted, $fontReg, $sub);
    
    // Decorative divider line
    $lineW = 500;
    $lx = (int)(($tw - $lineW) / 2);
    imageline($canvas, $lx, $y + 150, $lx + $lineW, $y + 150, imagecolorallocatealpha($canvas, 99, 102, 241, 100));
}

// Helper: Draw Feature Card
function drawFeatureCard($canvas, $x, $y, $w, $h, $tag, $tagCol, $title, $desc, $fontBold, $fontReg, $white, $muted, $borderCol) {
    global $cardBg;
    imagefilledrectangle($canvas, $x, $y, $x + $w, $y + $h, $cardBg);
    imagerectangle($canvas, $x, $y, $x + $w, $y + $h, $borderCol);
    
    // Tag pill top right
    $tBox = imagettfbbox(10, 0, $fontBold, $tag);
    $tagWidth = $tBox[2] - $tBox[0];
    imagettftext($canvas, 10, 0, $x + $w - $tagWidth - 20, $y + 30, $tagCol, $fontBold, $tag);
    
    // Title
    imagettftext($canvas, 15, 0, $x + 24, $y + 34, $white, $fontBold, $title);
    
    // Word wrap desc
    $words = explode(" ", $desc);
    $lines = [];
    $curLine = "";
    foreach ($words as $word) {
        $testLine = $curLine === "" ? $word : $curLine . " " . $word;
        $box = imagettfbbox(12, 0, $fontReg, $testLine);
        if ($box[2] - $box[0] > ($w - 48)) {
            $lines[] = $curLine;
            $curLine = $word;
        } else {
            $curLine = $testLine;
        }
    }
    if ($curLine !== "") $lines[] = $curLine;
    
    $lineY = $y + 66;
    foreach ($lines as $line) {
        imagettftext($canvas, 12, 0, $x + 24, $lineY, $muted, $fontReg, $line);
        $lineY += 23;
    }
}

// Helper: Embed & Center Image
function embedImage($canvas, $path, $targetY, $maxW, $maxH) {
    global $tw;
    if (!file_exists($path)) return;
    $src = @imagecreatefromstring(file_get_contents($path));
    if (!$src) return;
    $sw = imagesx($src);
    $sh = imagesy($src);
    
    $ratio = min($maxW / (float)$sw, $maxH / (float)$sh);
    $dw = (int)round($sw * $ratio);
    $dh = (int)round($sh * $ratio);
    $dx = (int)round(($tw - $dw) / 2);
    
    // Frame
    imagefilledrectangle($canvas, $dx - 4, $targetY - 4, $dx + $dw + 4, $targetY + $dh + 4, imagecolorallocatealpha($canvas, 99, 102, 241, 100));
    imagerectangle($canvas, $dx - 4, $targetY - 4, $dx + $dw + 4, $targetY + $dh + 4, imagecolorallocatealpha($canvas, 56, 189, 248, 60));
    
    imagecopyresampled($canvas, $src, $dx, $targetY, 0, 0, $dw, $dh, $sw, $sh);
    imagedestroy($src);
}

// Helper: Embed Image at specific coordinates
function embedImageAt($canvas, $path, $x, $y, $w, $h) {
    if (!file_exists($path)) return;
    $src = @imagecreatefromstring(file_get_contents($path));
    if (!$src) return;
    $sw = imagesx($src);
    $sh = imagesy($src);
    
    imagefilledrectangle($canvas, $x - 3, $y - 3, $x + $w + 3, $y + $h + 3, imagecolorallocatealpha($canvas, 99, 102, 241, 100));
    imagerectangle($canvas, $x - 3, $y - 3, $x + $w + 3, $y + $h + 3, imagecolorallocatealpha($canvas, 56, 189, 248, 70));
    
    imagecopyresampled($canvas, $src, $x, $y, 0, 0, $w, $h, $sw, $sh);
    imagedestroy($src);
}

// =========================================================================
// SECTION 0: TOP BAR & HERO (Y: 0 - 920)
// =========================================================================
echo "Rendering Hero Section...\n";
imagefilledrectangle($canvas, 0, 0, $tw, 70, imagecolorallocatealpha($canvas, 15, 23, 42, 20));
imageline($canvas, 0, 70, $tw, 70, imagecolorallocatealpha($canvas, 99, 102, 241, 70));

imagettftext($canvas, 14, 0, 80, 44, $cyan, $fontBold, "* ZOOM POS & CRM PLATFORM");
imagettftext($canvas, 12, 0, 480, 44, $white, $fontBold, "MULTI-TENANT B2B SAAS");
imagettftext($canvas, 12, 0, 850, 44, $gold, $fontBold, "WHITE-LABEL CLOUD APP BUILDER");
imagettftext($canvas, 12, 0, 1300, 44, $green, $fontBold, "100% OFFLINE SQLITE SYNC");
imagettftext($canvas, 12, 0, 1680, 44, $offWhite, $fontBold, "1-CLICK INSTALLER");

// Main Hero Title
$heroH1 = "Launch Your Own Multi-Tenant POS & ERP SaaS Business";
$h1Box = imagettfbbox(36, 0, $fontBold, $heroH1);
$h1X = (int)(($tw - ($h1Box[2] - $h1Box[0])) / 2);
imagettftext($canvas, 36, 0, $h1X, 145, $white, $fontBold, $heroH1);

$heroSub = "The turnkey white-label platform to launch your own Square, Toast, or Clover alternative. Unlimited stores, automated billing & Flutter apps.";
$hSubBox = imagettfbbox(16, 0, $fontReg, $heroSub);
$hSubX = (int)(($tw - ($hSubBox[2] - $hSubBox[0])) / 2);
imagettftext($canvas, 16, 0, $hSubX, 190, $muted, $fontReg, $heroSub);

// Hero Badges
$badges = [
    ["[$$$] $29   $149/mo Recurring MRR", $gold],
    ["[FAST] 0-Code Cloud App Builder", $green],
    ["[MOBILE] Android APK & Windows Desktop EXE", $cyan],
    ["[OPEN-SOURCE] 100% Unencrypted Source Code", $purple]
];
$totalBW = 0; $bBoxes = [];
foreach ($badges as $b) {
    $box = imagettfbbox(12, 0, $fontBold, "  " . $b[0] . "  ");
    $w = $box[2] - $box[0] + 20;
    $bBoxes[] = $w;
    $totalBW += $w + 20;
}
$curBX = (int)(($tw - $totalBW) / 2);
foreach ($badges as $idx => $b) {
    $bw = $bBoxes[$idx];
    imagefilledrectangle($canvas, $curBX, 220, $curBX + $bw, 260, imagecolorallocatealpha($canvas, 15, 23, 42, 40));
    imagerectangle($canvas, $curBX, 220, $curBX + $bw, 260, $b[1]);
    imagettftext($canvas, 11.5, 0, $curBX + 10, 246, $b[1], $fontBold, "  " . $b[0]);
    $curBX += $bw + 20;
}

// Embed Hero Mockup Banner (Y: 285 to 885)
embedImage($canvas, "public/marketplace-banner.jpg", 285, 1740, 580);


// =========================================================================
// SECTION 1: WHITE-LABEL CLOUD APP BUILDER (Y: 920 - 1800)
// =========================================================================
echo "Rendering Cloud App Builder Section...\n";
drawSectionHdr($canvas, 940, "01", "MOBILE COMPILATION ENGINE", "White-Label Cloud App Builder     Zero Flutter Setup Required", "Compile custom-branded Android APK, Google Play AAB, and Windows Desktop apps directly in the cloud.", $cyan, $fontBold, $fontReg, $white, $muted);

$builderFeats = [
    [
        "title" => "[FAST] 0-Code Cloud CI/CD Engine",
        "tag" => "AUTOMATED PIPELINE",
        "tagCol" => $cyan,
        "desc" => "No Android Studio, Java JDK, or Flutter SDK needed on your computer. Compilation runs in isolated, high-speed GitHub Actions cloud runners with zero configuration headaches."
    ],
    [
        "title" => "[MOD] Multi-Platform Parallel Outputs",
        "tag" => "ANDROID - WINDOWS - WEB",
        "tagCol" => $purple,
        "desc" => "Generate production-ready Android APK for instant sideloading, Google Play ready AAB (.aab), Windows Desktop native (.exe), and Web PWA simultaneously."
    ],
    [
        "title" => "     Full White-Label Customization",
        "tag" => "100% BRANDABLE",
        "tagCol" => $green,
        "desc" => "Customize App Name, Android Package ID (e.g. com.clientpos.app), primary HEX brand color, custom app icon, and launch splash screen in under 2 minutes."
    ],
    [
        "title" => "     Real-Time Logs & Email Delivery",
        "tag" => "LIVE STATUS",
        "tagCol" => $gold,
        "desc" => "Monitor live build progress on your admin dashboard. Receive automated email alerts and webhook notifications with direct binary download links upon completion."
    ]
];

$cw2 = 880; $ch2 = 145; $cg2 = 40;
$cxStart2 = (int)(($tw - (2 * $cw2 + $cg2)) / 2);
foreach ($builderFeats as $idx => $bf) {
    $col = $idx % 2;
    $row = (int)floor($idx / 2);
    $cx = $cxStart2 + ($col * ($cw2 + $cg2));
    $cy = 1120 + ($row * ($ch2 + 20));
    drawFeatureCard($canvas, $cx, $cy, $cw2, $ch2, $bf["tag"], $bf["tagCol"], $bf["title"], $bf["desc"], $fontBold, $fontReg, $white, $muted, $cardBorderCyan);
}

// 4-Step Builder Process Strip (Y: 1470 to 1720)
imagefilledrectangle($canvas, 100, 1470, 1900, 1720, imagecolorallocatealpha($canvas, 15, 23, 42, 50));
imagerectangle($canvas, 100, 1470, 1900, 1720, $cardBorderCyan);

imagettftext($canvas, 17, 0, 140, 1515, $white, $fontBold, ">>> How the Cloud App Builder Works in 4 Simple Steps:");

$steps = [
    ["1. Enter Details", "App Name, Package ID, Version & Hex Color", $cyan],
    ["2. Upload Brand", "Drop your PNG App Icon & Splash Screen", $purple],
    ["3. Cloud Build", "GitHub Actions compiles APK, AAB & Windows EXE", $gold],
    ["4. Ready to Use", "Download binaries directly or receive via Email", $green]
];
$stepW = 400; $stepGap = 20; $stepX = 140;
foreach ($steps as $st) {
    imagefilledrectangle($canvas, $stepX, 1545, $stepX + $stepW, 1680, imagecolorallocatealpha($canvas, 30, 41, 59, 80));
    imagerectangle($canvas, $stepX, 1545, $stepX + $stepW, 1680, $st[2]);
    imagettftext($canvas, 14, 0, $stepX + 20, 1585, $st[2], $fontBold, $st[0]);
    imagettftext($canvas, 11, 0, $stepX + 20, 1625, $offWhite, $fontReg, $st[1]);
    $stepX += $stepW + $stepGap;
}


// =========================================================================
// SECTION 2: PRICING ARCHITECTURE & COMPARISON (Y: 1760 - 2720)
// =========================================================================
echo "Rendering Pricing Comparison Section...\n";
drawSectionHdr($canvas, 1780, "02", "PRICING STRATEGY & ROI", "Transparent Pricing: $49 Core POS vs $119 All-In-One Enterprise Bundle", "Study the exact feature matrix. Choose the plan that maximizes your business revenue.", $gold, $fontBold, $fontReg, $white, $muted);

$pCardW = 880; $pCardH = 680;
// Plan 1: Core POS ($49)
$p1X = $cxStart2; $p1Y = 1960;
imagefilledrectangle($canvas, $p1X, $p1Y, $p1X + $pCardW, $p1Y + $pCardH, imagecolorallocatealpha($canvas, 15, 23, 42, 50));
imagerectangle($canvas, $p1X, $p1Y, $p1X + $pCardW, $p1Y + $pCardH, $cardBorder);

imagettftext($canvas, 14, 0, $p1X + 30, $p1Y + 45, $muted, $fontBold, "STARTER SAAS / SINGLE STORE");
imagettftext($canvas, 32, 0, $p1X + 30, $p1Y + 95, $white, $fontBold, "$49");
imagettftext($canvas, 14, 0, $p1X + 115, $p1Y + 95, $muted, $fontReg, "One-Time Payment - Lifetime License");
imagettftext($canvas, 16, 0, $p1X + 30, $p1Y + 135, $cyan, $fontBold, "Core POS & Multi-Store Platform");

$p1Items = [
    "+ Full Multi-Tenant Core POS Engine with Barcode Scanning",
    "+ Restaurant & Caf   Mode (Floor Tables & KOT Kitchen Tickets)",
    "+ Kitchen Display System (KDS) Live Order Station",
    "+ Offline-First SQLite Architecture with Background Cloud Sync",
    "+ ESC/POS Direct Thermal Printing (58mm/80mm USB, Bluetooth & LAN)",
    "+ Cash Register Float Management, Drawer Kick & Z-Reports",
    "+ Multi-Store Outlets & Central Warehousing Transfers",
    "+ Customer-Public Web Storefront (Online Ordering Catalog)",
    "+ Dual-Tax Engines (GST, VAT, Split & Fiscal QR Codes)",
    "+ WhatsApp Cloud API & SMS Messaging Gateway",
    "+ SuperAdmin Control Panel with Stripe & PayPal Billing",
    "+ 1 Cloud App Build / Month Quota (Branded Android APK)",
    "+ Standard Documentation & Installation Guides"
];
$p1ItemY = $p1Y + 175;
foreach ($p1Items as $item) {
    imagettftext($canvas, 12.5, 0, $p1X + 30, $p1ItemY, $offWhite, $fontReg, $item);
    $p1ItemY += 36;
}

// Plan 2: Enterprise Bundle ($119)
$p2X = $cxStart2 + $pCardW + $cg2; $p2Y = 1960;
imagefilledrectangle($canvas, $p2X, $p2Y, $p2X + $pCardW, $p2Y + $pCardH, imagecolorallocatealpha($canvas, 20, 30, 60, 60));
imagerectangle($canvas, $p2X, $p2Y, $p2X + $pCardW, $p2Y + $pCardH, $gold);

// Golden Recommended Badge
imagefilledrectangle($canvas, $p2X + $pCardW - 200, $p2Y + 20, $p2X + $pCardW - 25, $p2Y + 52, $gold);
imagettftext($canvas, 11, 0, $p2X + $pCardW - 190, $p2Y + 42, imagecolorallocate($canvas, 15, 23, 42), $fontBold, "* BEST FOR SAAS");

imagettftext($canvas, 14, 0, $p2X + 30, $p2Y + 45, $gold, $fontBold, "SAAS FOUNDER & AGENCY BUNDLE");
imagettftext($canvas, 32, 0, $p2X + 30, $p2Y + 95, $gold, $fontBold, "$119");
imagettftext($canvas, 14, 0, $p2X + 130, $p2Y + 95, $muted, $fontReg, "One-Time Payment - 100% Unencrypted");
imagettftext($canvas, 16, 0, $p2X + 30, $p2Y + 135, $green, $fontBold, "All-In-One Enterprise Bundle (10x Quota)");

$p2Items = [
    "* EVERYTHING IN CORE POS PLAN INCLUDED",
    "* 10 Cloud App Builds / Month Quota (10X Mobile Generation!)",
    "* Google Play Ready AAB (.aab) + Release APK for Direct Sideload",
    "* Windows Desktop Native (.exe) Client Package",
    "* Full White-Label Branding (Name, Package ID, Logo & Hex Colors)",
    "* ALL 4 VERTICAL ADDON MODULES INCLUDED ($200+ Value):",
    "   - Lead Management CRM (Multi-stage Kanban Pipeline & Scoring)",
    "   - Pharmacy POS with Drug Batch Tracking, Expiry & Prescriptions",
    "   - Salon & Spa System (Stylist Calendar & Commission Tracking)",
    "   - Repair Service Workbench (Device Intake, Diagnosis & Parts)",
    "* VIP Deployment Support & Server Setup Assistance",
    "* Database Tuning, SSL Setup & Background Cron Configuration",
    "* Priority WhatsApp & Email Developer Troubleshooting Assistance"
];
$p2ItemY = $p2Y + 175;
foreach ($p2Items as $item) {
    $colItem = strpos($item, "*") !== false ? $gold : $offWhite;
    imagettftext($canvas, 12.5, 0, $p2X + 30, $p2ItemY, $colItem, $fontReg, $item);
    $p2ItemY += 36;
}


// =========================================================================
// SECTION 3: CORE POS & CASHIER WORKFLOW (Y: 2720 - 3600)
// =========================================================================
echo "Rendering Core POS Section...\n";
drawSectionHdr($canvas, 2740, "03", "POINT OF SALE ENGINE", "High-Velocity Cashier POS, Weigh Scales & Touch Checkout", "Engineered for speed, accuracy, and smooth cashier operations under heavy retail rush hours.", $green, $fontBold, $fontReg, $white, $muted);

// Embed Retail POS Mockup
embedImage($canvas, "public/assets/images/retail-pos-mockup.png", 2920, 1100, 420);

// 6 POS Feature Cards (Y: 3370 to 3580)
$cw3 = 580; $ch3 = 110; $cg3 = 30;
$cxStart3 = (int)(($tw - (3 * $cw3 + 2 * $cg3)) / 2);

$posFeats = [
    ["title" => "Barcode Scanner Input", "tag" => "HARDWARE", "tagCol" => $cyan, "desc" => "Native support for USB/Bluetooth handheld barcode scanners (HID), camera scanning, and label printing."],
    ["title" => "Weighing-Scale Barcodes", "tag" => "DECIMAL WEIGHT", "tagCol" => $gold, "desc" => "Directly decodes embedded price and weight barcodes from electronic supermarket and butcher scales."],
    ["title" => "Held Orders & Split Tenders", "tag" => "CHECKOUT", "tagCol" => $green, "desc" => "Park unfinished customer orders and resume anytime. Accept payments split across Cash, Card, and Credit."],
    ["title" => "Customer Credit & Ledgers", "tag" => "ACCOUNTS RECEIVABLE", "tagCol" => $purple, "desc" => "Sell on credit with custom credit limits. Track outstanding balances and log partial settlements."],
    ["title" => "Loyalty Reward Points", "tag" => "RETENTION", "tagCol" => $cyan, "desc" => "Configurable reward points accrued per dollar spent, redeemable for instant discounts at checkout."],
    ["title" => "Cash Shift & Z-Reports", "tag" => "TILL AUDITING", "tagCol" => $gold, "desc" => "Opening float denomination breakdown, petty cash logs, blind close counts, and discrepancy audits."]
];

foreach ($posFeats as $idx => $pf) {
    $col = $idx % 3;
    $row = (int)floor($idx / 3);
    $cx = $cxStart3 + ($col * ($cw3 + $cg3));
    $cy = 3360 + ($row * ($ch3 + 15));
    drawFeatureCard($canvas, $cx, $cy, $cw3, $ch3, $pf["tag"], $pf["tagCol"], $pf["title"], $pf["desc"], $fontBold, $fontReg, $white, $muted, $cardBorder);
}


// =========================================================================
// SECTION 4: THE 5 INDUSTRY VERTICALS (Y: 3620 - 5200)
// =========================================================================
echo "Rendering Industry Verticals Section...\n";
drawSectionHdr($canvas, 3640, "04", "INDUSTRY VERTICALS", "5 Purpose-Built Industry Solutions in One Unified Platform", "Target supermarkets, restaurants, pharmacies, beauty salons, and repair workshops with dedicated workflows.", $purple, $fontBold, $fontReg, $white, $muted);

$verticals = [
    [
        "title" => "1. Restaurant, Caf  , Bar & QSR",
        "tag" => "FOOD & BEVERAGE",
        "tagCol" => $gold,
        "img" => "public/assets/images/restaurant-pos-mockup.png",
        "points" => [
            "- Visual interactive floor plan with live table occupancy (Vacant, Seated, Billed)",
            "- Kitchen Display System (KDS): Live touch screen for kitchen preparation timers",
            "- Kitchen Order Tickets (KOT): Auto-routed to kitchen & bar thermal printers",
            "- Contactless QR Table Ordering: Guests scan table stands to order from their phones"
        ]
    ],
    [
        "title" => "2. Pharmacy & Healthcare POS",
        "tag" => "HEALTHCARE",
        "tagCol" => $green,
        "img" => "public/assets/images/pharmacy-pos-mockup.png",
        "points" => [
            "- Drug Batch & Lot Number Tracking from supplier receipt to checkout",
            "- Automated Expiry Date Alerts: System prevents dispensing expired medications",
            "- Prescription Intake: Capture & attach doctor prescription photos to patient records",
            "- Generic Drug Directory: Search by active salt to suggest affordable alternatives"
        ]
    ],
    [
        "title" => "3. Salon, Spa & Wellness System",
        "tag" => "APPOINTMENTS",
        "tagCol" => $purple,
        "img" => "public/assets/images/salon-pos-mockup.png",
        "points" => [
            "- Visual Appointment Calendar: Book services by day, week, or stylist duration blocks",
            "- Stylist & Chair Scheduling: Manage staff rosters, shifts, and availability",
            "- Automatic Commission Tracking: Calculate commissions per service & retail product",
            "- Hybrid Checkout: Combine hair styling and beauty product purchases on one bill"
        ]
    ],
    [
        "title" => "4. Repair Service Workbench",
        "tag" => "WORKSHOP & GADGETS",
        "tagCol" => $cyan,
        "img" => "public/assets/images/repair-pos-mockup.png",
        "points" => [
            "- Device Intake Tickets: Record model, serial number/IMEI, condition, and intake photos",
            "- Diagnostic Checklists: Standardized testing templates per device category",
            "- Parts & Labor Allocation: Deduct repair parts from inventory and track labor fees",
            "- Public Tracking Portal: Customers check live repair status online without calling"
        ]
    ],
    [
        "title" => "5. Lead Management & Sales CRM",
        "tag" => "B2B SALES",
        "tagCol" => $coral,
        "img" => "public/assets/images/lead-crm-mockup.png",
        "points" => [
            "- Visual Kanban Deal Pipeline: Drag-and-drop deals across customized sales stages",
            "- Activity History: Log phone calls, meeting notes, emails, and follow-up reminders",
            "- Lead Scoring & Source Attribution: Track conversion ROI from Google, ads, walk-ins",
            "- 1-Click Conversion: Turn won deals directly into formal quotations or POS invoices"
        ]
    ]
];

$vertY = 3820;
foreach ($verticals as $v) {
    imagefilledrectangle($canvas, 100, $vertY, 1900, $vertY + 250, imagecolorallocatealpha($canvas, 15, 23, 42, 50));
    imagerectangle($canvas, 100, $vertY, 1900, $vertY + 250, $cardBorder);
    
    // Embed thumbnail left
    embedImageAt($canvas, $v["img"], 130, $vertY + 25, 240, 200);
    
    // Header
    imagettftext($canvas, 18, 0, 400, $vertY + 55, $white, $fontBold, $v["title"]);
    imagettftext($canvas, 11, 0, 400, $vertY + 85, $v["tagCol"], $fontBold, $v["tag"]);
    
    // Bullets
    $bY = $vertY + 120;
    foreach ($v["points"] as $pt) {
        imagettftext($canvas, 13, 0, 400, $bY, $offWhite, $fontReg, $pt);
        $bY += 28;
    }
    
    $vertY += 275;
}


// =========================================================================
// SECTION 5: TRUE OFFLINE-FIRST SQLITE ARCHITECTURE (Y: 5240 - 6040)
// =========================================================================
echo "Rendering Offline Architecture Section...\n";
drawSectionHdr($canvas, 5260, "05", "OFFLINE RESILIENCE", "True Offline-First SQLite Architecture     Never Stop Selling", "When the internet goes down, your cashiers keep ringing up sales and printing receipts without interruption.", $cyan, $fontBold, $fontReg, $white, $muted);

$offFeats = [
    ["title" => "Local SQLite Cache", "tag" => "ZERO DOWNTIME", "tagCol" => $cyan, "desc" => "Encrypted local SQLite database caches products, prices, tax tables, customer records, and active shift state directly on the device."],
    ["title" => "Uninterrupted Selling", "tag" => "OFFLINE SALES", "tagCol" => $green, "desc" => "Cashiers scan barcodes, apply discounts, accept cash, and print thermal receipts even during total internet blackouts."],
    ["title" => "Local UUID Queue", "tag" => "SECURE STORAGE", "tagCol" => $purple, "desc" => "Every offline sale is assigned a cryptographically unique UUID and queued locally on device storage without data loss."],
    ["title" => "Automatic Cloud Sync", "tag" => "BI-DIRECTIONAL", "tagCol" => $gold, "desc" => "The second Wi-Fi or cellular returns, background workers push sales, reconcile stock, update ledgers, and resolve conflicts."]
];

foreach ($offFeats as $idx => $of) {
    $col = $idx % 2;
    $row = (int)floor($idx / 2);
    $cx = $cxStart2 + ($col * ($cw2 + $cg2));
    $cy = 5440 + ($row * ($ch2 + 20));
    drawFeatureCard($canvas, $cx, $cy, $cw2, $ch2, $of["tag"], $of["tagCol"], $of["title"], $of["desc"], $fontBold, $fontReg, $white, $muted, $cardBorderCyan);
}

// Sync Pipeline Strip (Y: 5780 to 5980)
imagefilledrectangle($canvas, 100, 5780, 1900, 5980, imagecolorallocatealpha($canvas, 15, 23, 42, 50));
imagerectangle($canvas, 100, 5780, 1900, 5980, $cardBorderGreen);

imagettftext($canvas, 16, 0, 140, 5825, $white, $fontBold, "     How the Offline-First Synchronization Engine Works:");
$syncSteps = [
    ["1. Device Online", "Caches master catalog & rules into SQLite", $cyan],
    ["2. Internet Drops", "Cashier continues scanning & printing", $gold],
    ["3. Local Queue", "Transactions assigned UUID & queued securely", $purple],
    ["4. Cloud Sync", "Auto reconciles inventory & financial ledgers", $green]
];
$sX = 140;
foreach ($syncSteps as $st) {
    imagefilledrectangle($canvas, $sX, 5850, $sX + $stepW, 5950, imagecolorallocatealpha($canvas, 30, 41, 59, 80));
    imagerectangle($canvas, $sX, 5850, $sX + $stepW, 5950, $st[2]);
    imagettftext($canvas, 13, 0, $sX + 20, 5885, $st[2], $fontBold, $st[0]);
    imagettftext($canvas, 11, 0, $sX + 20, 5920, $offWhite, $fontReg, $st[1]);
    $sX += $stepW + $stepGap;
}


// =========================================================================
// SECTION 6: HARDWARE & THERMAL PRINTING (Y: 6040 - 6840)
// =========================================================================
echo "Rendering Hardware & Printing Section...\n";
drawSectionHdr($canvas, 6060, "06", "HARDWARE & PRINTING", "Universal ESC/POS Direct Thermal Printing & Cash Drawers", "Direct hardware communication via raw bytecode. No clunky browser print dialogs.", $gold, $fontBold, $fontReg, $white, $muted);

$hwFeats = [
    ["title" => "Universal ESC/POS Command Engine", "tag" => "BYTECODE PRINT", "tagCol" => $gold, "desc" => "Sends raw bytecode commands directly to thermal printers. Bypasses browser print windows for instant, silent ticket generation."],
    ["title" => "USB, Bluetooth & LAN Sockets", "tag" => "CONNECTIVITY", "tagCol" => $cyan, "desc" => "Support for USB Direct (OTG/COM), Bluetooth SPP/BLE pairing for mobile belts, and TCP/IP Ethernet LAN sockets for kitchen printers."],
    ["title" => "58mm & 80mm Roll Widths", "tag" => "PAPER SIZES", "tagCol" => $green, "desc" => "Flawless responsive layouts for standard 2-inch (58mm) and 3-inch (80mm) thermal rolls with automated line wrapping and cuts."],
    ["title" => "Cash Drawer Kick & Barcode Labels", "tag" => "PERIPHERALS", "tagCol" => $purple, "desc" => "Sends RJ11 pulse to open cash drawers. Built-in designer for Code128, EAN-13, and QR sticker labels for products and shelves."]
];

foreach ($hwFeats as $idx => $hf) {
    $col = $idx % 2;
    $row = (int)floor($idx / 2);
    $cx = $cxStart2 + ($col * ($cw2 + $cg2));
    $cy = 6240 + ($row * ($ch2 + 20));
    drawFeatureCard($canvas, $cx, $cy, $cw2, $ch2, $hf["tag"], $hf["tagCol"], $hf["title"], $hf["desc"], $fontBold, $fontReg, $white, $muted, $cardBorderGold);
}

// Hardware Badges Strip (Y: 6580 to 6720)
imagefilledrectangle($canvas, 100, 6580, 1900, 6720, imagecolorallocatealpha($canvas, 15, 23, 42, 50));
imagerectangle($canvas, 100, 6580, 1900, 6720, $cardBorder);
imagettftext($canvas, 14, 0, 140, 6635, $white, $fontBold, "[HARDWARE] Compatible Point-of-Sale Hardware:");
imagettftext($canvas, 12, 0, 140, 6680, $cyan, $fontBold, "- Epson, Star, Xprinter & Sunmi Thermal Printers");
imagettftext($canvas, 12, 0, 620, 6680, $green, $fontBold, "- Standard 12V/24V RJ11/RJ12 Cash Drawers");
imagettftext($canvas, 12, 0, 1100, 6680, $gold, $fontBold, "- USB/Bluetooth Laser & 2D Barcode Scanners");
imagettftext($canvas, 12, 0, 1580, 6680, $purple, $fontBold, "- Weighing Scale Barcodes");


// =========================================================================
// SECTION 7: ONLINE STOREFRONT & ECOMMERCE (Y: 6840 - 7640)
// =========================================================================
echo "Rendering Storefront Section...\n";
drawSectionHdr($canvas, 6860, "07", "ONLINE STOREFRONT & ECOMMERCE", "Turnkey E-Commerce Storefront with Custom Apex Domain Mapping", "Empower every tenant to sell online 24/7 with seamless real-time inventory synchronization.", $purple, $fontBold, $fontReg, $white, $muted);

$storeFeats = [
    ["title" => "Instant Web Storefront", "tag" => "ZERO-CODE STORE", "tagCol" => $cyan, "desc" => "Activated with one click. Map custom apex domains (shop.client.com) with automated SSL certificates and mobile-responsive layout."],
    ["title" => "Responsive Catalog & Cart", "tag" => "CONVERSIONS", "tagCol" => $purple, "desc" => "Mobile-optimized product catalog, categories, search, cart drawer, customer wishlist, and multi-step checkout with delivery address books."],
    ["title" => "Order Tracking & Fulfillment", "tag" => "SELF-SERVICE", "tagCol" => $green, "desc" => "Public shareable order tracking portal with real-time status updates. Supports in-store pickup (BOPIS) and local home delivery."],
    ["title" => "Coupons, Reviews & FAQs", "tag" => "MARKETING", "tagCol" => $gold, "desc" => "Percentage/fixed amount discount coupons with usage rules, verified customer star ratings with admin moderation, and searchable FAQ accordion."]
];

foreach ($storeFeats as $idx => $sf) {
    $col = $idx % 2;
    $row = (int)floor($idx / 2);
    $cx = $cxStart2 + ($col * ($cw2 + $cg2));
    $cy = 7040 + ($row * ($ch2 + 20));
    drawFeatureCard($canvas, $cx, $cy, $cw2, $ch2, $sf["tag"], $sf["tagCol"], $sf["title"], $sf["desc"], $fontBold, $fontReg, $white, $muted, $cardBorder);
}

// Storefront Features Strip (Y: 7380 to 7520)
imagefilledrectangle($canvas, 100, 7380, 1900, 7520, imagecolorallocatealpha($canvas, 15, 23, 42, 50));
imagerectangle($canvas, 100, 7380, 1900, 7520, $cardBorderCyan);
imagettftext($canvas, 14, 0, 140, 7435, $white, $fontBold, "[STORE] Turnkey Storefront Engine:");
imagettftext($canvas, 12, 0, 140, 7480, $green, $fontBold, "- Custom Apex Domains (SSL)");
imagettftext($canvas, 12, 0, 520, 7480, $cyan, $fontBold, "- Live Order Tracking Link");
imagettftext($canvas, 12, 0, 880, 7480, $gold, $fontBold, "- BOPIS & Home Delivery");
imagettftext($canvas, 12, 0, 1240, 7480, $purple, $fontBold, "- Customer Account & Wishlist");
imagettftext($canvas, 12, 0, 1640, 7480, $offWhite, $fontBold, "- SEO OpenGraph Meta");


// =========================================================================
// SECTION 8: OMNICHANNEL NOTIFICATIONS (Y: 7640 - 8440)
// =========================================================================
echo "Rendering Omnichannel Notifications Section...\n";
drawSectionHdr($canvas, 7660, "08", "COMMUNICATIONS ENGINE", "WhatsApp Cloud API, SMS Gateways & Transactional Email", "Automate digital receipts, order status updates, and payment reminders across all major messaging channels.", $green, $fontBold, $fontReg, $white, $muted);

$commFeats = [
    ["title" => "WhatsApp Cloud API", "tag" => "META OFFICIAL", "tagCol" => $green, "desc" => "Official Meta WhatsApp Cloud API integration. Send digital thermal receipts, order confirmations, and repair updates straight to customer WhatsApp."],
    ["title" => "Multi-Gateway SMS", "tag" => "INSTANT SMS", "tagCol" => $cyan, "desc" => "Plug-and-play integrations with Twilio, Vonage, MSG91, and custom HTTP SMS gateways for OTP phone logins, alerts, and notifications."],
    ["title" => "Branded SMTP Email", "tag" => "PDF INVOICES", "tagCol" => $purple, "desc" => "Configure custom tenant SMTP credentials (SendGrid, Mailgun, Amazon SES). Send PDF invoices, password resets, and subscription digests."],
    ["title" => "Scheduled Event Crons", "tag" => "SMART TRIGGERS", "tagCol" => $gold, "desc" => "Automate daily morning low-stock warnings to managers, overdue receivable notices every 3 days, and end-of-day sales summaries."]
];

foreach ($commFeats as $idx => $cf) {
    $col = $idx % 2;
    $row = (int)floor($idx / 2);
    $cx = $cxStart2 + ($col * ($cw2 + $cg2));
    $cy = 7840 + ($row * ($ch2 + 20));
    drawFeatureCard($canvas, $cx, $cy, $cw2, $ch2, $cf["tag"], $cf["tagCol"], $cf["title"], $cf["desc"], $fontBold, $fontReg, $white, $muted, $cardBorderGreen);
}

// Channels Pill Strip (Y: 8180 to 8320)
imagefilledrectangle($canvas, 100, 8180, 1900, 8320, imagecolorallocatealpha($canvas, 15, 23, 42, 50));
imagerectangle($canvas, 100, 8180, 1900, 8320, $cardBorderGreen);
imagettftext($canvas, 13, 0, 140, 8255, $green, $fontBold, "[WHATSAPP] WhatsApp Business Cloud API");
imagettftext($canvas, 13, 0, 540, 8255, $cyan, $fontBold, "[MOBILE] Twilio & Generic HTTP SMS");
imagettftext($canvas, 13, 0, 960, 8255, $purple, $fontBold, "[EMAIL] Custom SMTP Email / SES");
imagettftext($canvas, 13, 0, 1400, 8255, $gold, $fontBold, "[CRON] Automated Background Crons");


// =========================================================================
// SECTION 9: ACCOUNTING, FINANCE & TAXES (Y: 8440 - 9240)
// =========================================================================
echo "Rendering Finance & Taxes Section...\n";
drawSectionHdr($canvas, 8460, "09", "FINANCIAL ENGINE", "Complete Business Accounting, Ledgers & Dual-Tax Engines", "Maintain total financial control with real-time P&L, accounts receivable, and fiscal tax compliance.", $gold, $fontBold, $fontReg, $white, $muted);

$finFeats = [
    ["title" => "Accounts Receivable & Debtor Ledger", "tag" => "CREDIT SALES", "tagCol" => $cyan, "desc" => "Track customer credit sales, 30/60/90-day overdue aging, credit limits, and record partial balance settlements automatically."],
    ["title" => "Accounts Payable & Vendor Bills", "tag" => "PURCHASES", "tagCol" => $purple, "desc" => "Record supplier purchase invoices, schedule due dates, track goods receipt notes (GRN), and manage cash outflows."],
    ["title" => "Real-Time Profit & Loss (P&L)", "tag" => "BUSINESS HEALTH", "tagCol" => $green, "desc" => "Automated COGS calculation, revenue minus expenses, tax liabilities, and store-by-store net margin tracking."],
    ["title" => "Flexible Dual-Tax Engines", "tag" => "TAX COMPLIANCE", "tagCol" => $gold, "desc" => "GST, VAT, compound tax rules, inclusive/exclusive pricing, and regional electronic fiscal QR codes (ZATCA, GST)."]
];

foreach ($finFeats as $idx => $ff) {
    $col = $idx % 2;
    $row = (int)floor($idx / 2);
    $cx = $cxStart2 + ($col * ($cw2 + $cg2));
    $cy = 8640 + ($row * ($ch2 + 20));
    drawFeatureCard($canvas, $cx, $cy, $cw2, $ch2, $ff["tag"], $ff["tagCol"], $ff["title"], $ff["desc"], $fontBold, $fontReg, $white, $muted, $cardBorder);
}

// Payment Strip (Y: 8980 to 9120)
imagefilledrectangle($canvas, 100, 8980, 1900, 9120, imagecolorallocatealpha($canvas, 15, 23, 42, 50));
imagerectangle($canvas, 100, 8980, 1900, 9120, $cardBorder);
$pBadges = ["[CARD] Stripe Card Billing", "[PAYPAL] PayPal Smart Buttons", "[FAST] Razorpay Suite", "IN: UPI & QR Pay", "BR: Pix Instant", "[BANK] Bank Transfer & Cheque"];
$pX = 140;
foreach ($pBadges as $pb) {
    imagettftext($canvas, 12.5, 0, $pX, 9055, $offWhite, $fontBold, $pb);
    $pX += 290;
}


// =========================================================================
// SECTION 10: SUPERADMIN SAAS CONTROL & INSTALLER (Y: 9240 - 10100)
// =========================================================================
echo "Rendering SuperAdmin & Architecture Section...\n";
drawSectionHdr($canvas, 9260, "10", "SAAS SUPERADMIN", "Automated Subscription Billing, Multi-Tenancy & 1-Click Installer", "Complete command over your SaaS platform, subscription revenue, and tenant workspaces.", $cyan, $fontBold, $fontReg, $white, $muted);

$saasFeats = [
    ["title" => "Automated Subscription Engine", "tag" => "RECURRING MRR", "tagCol" => $gold, "desc" => "Create custom subscription tiers (Free, Starter, Pro, Enterprise). Invoices tenants automatically via Stripe, PayPal, or Razorpay."],
    ["title" => "1-Click Tenant Impersonation", "tag" => "SUPPORT TOOL", "tagCol" => $cyan, "desc" => "Platform admins can log in directly to any merchant account with one click to diagnose issues without requesting client passwords."],
    ["title" => "1-Click Web Installer (/install)", "tag" => "ZERO-CODE SETUP", "tagCol" => $green, "desc" => "Guided browser installer checks PHP extensions, sets up database tables, runs migrations, and creates SuperAdmin in under 3 minutes."],
    ["title" => "100% Unencrypted Source Code", "tag" => "OPEN FREEDOM", "tagCol" => $purple, "desc" => "No ionCube loaders, no domain locks, no phone-home license checks. You own the code forever and keep 100% of merchant subscription fees."]
];

foreach ($saasFeats as $idx => $sf) {
    $col = $idx % 2;
    $row = (int)floor($idx / 2);
    $cx = $cxStart2 + ($col * ($cw2 + $cg2));
    $cy = 9440 + ($row * ($ch2 + 20));
    drawFeatureCard($canvas, $cx, $cy, $cw2, $ch2, $sf["tag"], $sf["tagCol"], $sf["title"], $sf["desc"], $fontBold, $fontReg, $white, $muted, $cardBorderCyan);
}

// Specs Strip (Y: 9780 to 9920)
imagefilledrectangle($canvas, 100, 9780, 1900, 9920, imagecolorallocatealpha($canvas, 15, 23, 42, 50));
imagerectangle($canvas, 100, 9780, 1900, 9920, $cardBorderGreen);
imagettftext($canvas, 13, 0, 140, 9845, $white, $fontBold, "[TECH] Backend: Laravel 11.x - Livewire 3 - PHP 8.2+ / 8.3+ - MySQL 8.0+ / MariaDB");
imagettftext($canvas, 13, 0, 1050, 9845, $cyan, $fontBold, "[MOBILE] Mobile: Flutter 3.x (Android APK & Windows EXE)");
imagettftext($canvas, 13, 0, 1640, 9845, $gold, $fontBold, ">>> Runs on cPanel, VPS & AWS");


// =========================================================================
// SECTION 11: FOOTER CALL TO ACTION (Y: 10100 - 10700)
// =========================================================================
echo "Rendering Footer CTA Section...\n";
imagefilledrectangle($canvas, 0, 10100, $tw, $th, imagecolorallocatealpha($canvas, 10, 16, 36, 10));
imageline($canvas, 0, 10100, $tw, 10100, imagecolorallocatealpha($canvas, 99, 102, 241, 100));

$ctaH1 = "Launch Your Multi-Tenant SaaS Business Today";
$ctaBox = imagettfbbox(32, 0, $fontBold, $ctaH1);
$ctaX = (int)(($tw - ($ctaBox[2] - $ctaBox[0])) / 2);
imagettftext($canvas, 32, 0, $ctaX, 10220, $white, $fontBold, $ctaH1);

$ctaSub = "Start your own white-label POS empire. Keep 100% of profits with zero monthly fees to us.";
$ctaSubBox = imagettfbbox(16, 0, $fontReg, $ctaSub);
$ctaSubX = (int)(($tw - ($ctaSubBox[2] - $ctaSubBox[0])) / 2);
imagettftext($canvas, 16, 0, $ctaSubX, 10270, $muted, $fontReg, $ctaSub);

// Big Glowing Button
$btnW = 600; $btnH = 70;
$btnX = (int)(($tw - $btnW) / 2); $btnY = 10320;
imagefilledrectangle($canvas, $btnX, $btnY, $btnX + $btnW, $btnY + $btnH, $gold);
$btnText = "GET INSTANT ACCESS & FULL SOURCE CODE";
$btnTBox = imagettfbbox(16, 0, $fontBold, $btnText);
$btnTX = (int)(($tw - ($btnTBox[2] - $btnTBox[0])) / 2);
imagettftext($canvas, 16, 0, $btnTX, $btnY + 44, imagecolorallocate($canvas, 15, 23, 42), $fontBold, $btnText);

imagettftext($canvas, 12, 0, 580, 10450, $darkMuted, $fontReg, "Instant Download - 100% Open Source - Regular Updates - Active Support");

// Save outputs
$destPaths = [
    "public/assets/images/promotional-banner-2000x10000.jpg",
    "public/documentation/images/promotional-banner-2000x10000.jpg",
    "public/assets/images/marketplace-feature-banner.jpg",
    "public/marketplace-feature-banner.jpg"
];

echo "Compressing and saving JPEG outputs...\n";
imagejpeg($canvas, $destPaths[0], 88);

foreach (array_slice($destPaths, 1) as $dp) {
    @copy($destPaths[0], $dp);
}

imagedestroy($canvas);
echo "Banner generation complete! Saved to:\n";
foreach ($destPaths as $dp) {
    echo " - $dp (" . filesize($dp) . " bytes)\n";
}
