<?php
ini_set('memory_limit', '2048M');
set_time_limit(300);

echo "Starting 2000x10000 mega banner generation...\n";

$tw = 2000;
$th = 10000;

$canvas = imagecreatetruecolor($tw, $th);

// Base background gradient: deep obsidian navy #070c1b to #0a1128
for ($y = 0; $y < $th; $y++) {
    $ratio = ($y % 2000) / 2000.0;
    // Subtle breathing gradient through sections
    $r = (int)(7 + 5 * sin($y / 600.0));
    $g = (int)(12 + 6 * cos($y / 800.0));
    $b = (int)(27 + 10 * sin($y / 700.0));
    $col = imagecolorallocate($canvas, max(4, $r), max(8, $g), max(20, $b));
    imageline($canvas, 0, $y, $tw, $y, $col);
}

$fontBold = "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf";
$fontReg  = "/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf";

// Color palette
$white       = imagecolorallocate($canvas, 255, 255, 255);
$pureWhite   = imagecolorallocate($canvas, 255, 255, 255);
$offWhite    = imagecolorallocate($canvas, 241, 245, 249);
$muted       = imagecolorallocate($canvas, 148, 163, 184);
$darkMuted   = imagecolorallocate($canvas, 100, 116, 139);
$cyan        = imagecolorallocate($canvas, 56, 189, 248);
$brightCyan  = imagecolorallocate($canvas, 14, 165, 233);
$purple      = imagecolorallocate($canvas, 168, 85, 247);
$brightPurple= imagecolorallocate($canvas, 192, 132, 252);
$gold        = imagecolorallocate($canvas, 251, 191, 36);
$green       = imagecolorallocate($canvas, 52, 211, 153);
$emerald     = imagecolorallocate($canvas, 16, 185, 129);
$cardBg      = imagecolorallocatealpha($canvas, 15, 23, 42, 40);
$cardBorder  = imagecolorallocatealpha($canvas, 99, 102, 241, 80);
$cardBorderCyan = imagecolorallocatealpha($canvas, 56, 189, 248, 80);
$cardBorderGreen= imagecolorallocatealpha($canvas, 16, 185, 129, 80);

// Helper function: Draw Section Header
function drawSectionHdr($canvas, $y, $stepNum, $tag, $title, $sub, $accentCol, $fontBold, $fontReg, $white, $muted) {
    global $tw;
    
    // Glowing accent pill
    $pillText = "  " . strtoupper($stepNum . " • " . $tag) . "  ";
    $bbox = imagettfbbox(13, 0, $fontBold, $pillText);
    $pw = $bbox[2] - $bbox[0] + 30;
    $px = (int)(($tw - $pw) / 2);
    
    imagefilledrectangle($canvas, $px, $y, $px + $pw, $y + 36, imagecolorallocatealpha($canvas, 15, 23, 42, 30));
    imagerectangle($canvas, $px, $y, $px + $pw, $y + 36, $accentCol);
    imagettftext($canvas, 12, 0, $px + 15, $y + 24, $accentCol, $fontBold, $pillText);
    
    // Main Section Title (Center aligned)
    $tBox = imagettfbbox(32, 0, $fontBold, $title);
    $tx = (int)(($tw - ($tBox[2] - $tBox[0])) / 2);
    imagettftext($canvas, 32, 0, $tx, $y + 90, $white, $fontBold, $title);
    
    // Sub-title
    $sBox = imagettfbbox(16, 0, $fontReg, $sub);
    $sx = (int)(($tw - ($sBox[2] - $sBox[0])) / 2);
    imagettftext($canvas, 16, 0, $sx, $y + 130, $muted, $fontReg, $sub);
    
    // Decorative divider line
    $lineW = 600;
    $lx = (int)(($tw - $lineW) / 2);
    imageline($canvas, $lx, $y + 155, $lx + $lineW, $y + 155, imagecolorallocatealpha($canvas, 99, 102, 241, 100));
}

// Helper function: Draw Feature Card
function drawFeatureCard($canvas, $x, $y, $w, $h, $tag, $tagCol, $title, $desc, $fontBold, $fontReg, $white, $muted, $borderCol) {
    global $cardBg;
    imagefilledrectangle($canvas, $x, $y, $x + $w, $y + $h, $cardBg);
    imagerectangle($canvas, $x, $y, $x + $w, $y + $h, $borderCol);
    
    // Tag pill top right
    $tBox = imagettfbbox(10, 0, $fontBold, $tag);
    $tw = $tBox[2] - $tBox[0];
    imagettftext($canvas, 10, 0, $x + $w - $tw - 20, $y + 30, $tagCol, $fontBold, $tag);
    
    // Title
    imagettftext($canvas, 16, 0, $x + 24, $y + 34, $white, $fontBold, $title);
    
    // Desc
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
    
    $lineY = $y + 68;
    foreach ($lines as $line) {
        imagettftext($canvas, 12, 0, $x + 24, $lineY, $muted, $fontReg, $line);
        $lineY += 24;
    }
}

// Helper: Safely embed & center image
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
    imagefilledrectangle($canvas, $dx - 6, $targetY - 6, $dx + $dw + 6, $targetY + $dh + 6, imagecolorallocatealpha($canvas, 99, 102, 241, 100));
    imagerectangle($canvas, $dx - 6, $targetY - 6, $dx + $dw + 6, $targetY + $dh + 6, imagecolorallocatealpha($canvas, 56, 189, 248, 60));
    
    imagecopyresampled($canvas, $src, $dx, $targetY, 0, 0, $dw, $dh, $sw, $sh);
    imagedestroy($src);
}

// =========================================================================
// SECTION 1: HERO SECTION (0 - 850)
// =========================================================================
echo "Rendering Hero Section...\n";
// Top Banner Bar
imagefilledrectangle($canvas, 0, 0, $tw, 70, imagecolorallocatealpha($canvas, 15, 23, 42, 20));
imageline($canvas, 0, 70, $tw, 70, imagecolorallocatealpha($canvas, 99, 102, 241, 70));

imagettftext($canvas, 14, 0, 80, 44, $cyan, $fontBold, "★ ZooM Sales CRM & Inventory");
imagettftext($canvas, 12, 0, 520, 44, $white, $fontBold, "MULTI-TENANT B2B SAAS PLATFORM");
imagettftext($canvas, 12, 0, 920, 44, $gold, $fontBold, "LARAVEL 11 + FLUTTER 3 APPS");
imagettftext($canvas, 12, 0, 1340, 44, $green, $fontBold, "100% OFFLINE SQLITE SYNC");
imagettftext($canvas, 12, 0, 1680, 44, $offWhite, $fontBold, "1-CLICK INSTALLER");

// Massive Hero Title
$heroH1 = "Launch Your Own Multi-Tenant POS & ERP SaaS Business";
$h1Box = imagettfbbox(38, 0, $fontBold, $heroH1);
$h1X = (int)(($tw - ($h1Box[2] - $h1Box[0])) / 2);
imagettftext($canvas, 38, 0, $h1X, 150, $white, $fontBold, $heroH1);

$heroSub = "The complete white-label solution to launch your own Square, Toast, or Clover alternative. Charge $29–$99/month recurring revenue per store.";
$hSubBox = imagettfbbox(17, 0, $fontReg, $heroSub);
$hSubX = (int)(($tw - ($hSubBox[2] - $hSubBox[0])) / 2);
imagettftext($canvas, 17, 0, $hSubX, 195, $muted, $fontReg, $heroSub);

// Hero Badges
$badges = [
    ["💰 $29–$99/mo Recurring MRR", $gold],
    ["⚡ 1-Click Guided Web Installer", $green],
    ["📱 Android APK & Windows Desktop EXE", $cyan],
    ["🔒 100% Unencrypted Source Code", $purple]
];
$totalBW = 0;
$bBoxes = [];
foreach ($badges as $b) {
    $box = imagettfbbox(12, 0, $fontBold, "  " . $b[0] . "  ");
    $w = $box[2] - $box[0] + 20;
    $bBoxes[] = $w;
    $totalBW += $w + 20;
}
$curBX = (int)(($tw - $totalBW) / 2);
foreach ($badges as $idx => $b) {
    $bw = $bBoxes[$idx];
    imagefilledrectangle($canvas, $curBX, 225, $curBX + $bw, 265, imagecolorallocatealpha($canvas, 15, 23, 42, 40));
    imagerectangle($canvas, $curBX, 225, $curBX + $bw, 265, $b[1]);
    imagettftext($canvas, 11.5, 0, $curBX + 10, 251, $b[1], $fontBold, "  " . $b[0]);
    $curBX += $bw + 20;
}

// Hero Workstation Mockup (Y: 290 to 820)
embedImage($canvas, "public/product-image.jpg", 295, 1720, 520);


// =========================================================================
// SECTION 2: PRODUCT OVERVIEW & ECOSYSTEM (850 - 1500)
// =========================================================================
echo "Rendering Product Overview Section...\n";
drawSectionHdr($canvas, 870, "01", "PRODUCT ECOSYSTEM", "Three Connected Surfaces, One Unified Cloud Platform", "Every business operates seamlessly across web, shop-floor native apps, and customer storefronts.", $cyan, $fontBold, $fontReg, $white, $muted);

$surfaces = [
    [
        "title" => "👑 Web Management Dashboard",
        "tag" => "CENTRAL BACK-OFFICE",
        "tagCol" => $purple,
        "desc" => "Complete business administration from any desktop browser. Configure unlimited branches, product master catalogs, tax rules, payment gateways, staff roles, and executive financial reports."
    ],
    [
        "title" => "📱 Flutter Mobile & Desktop App",
        "tag" => "SHOP-FLOOR POS",
        "tagCol" => $cyan,
        "desc" => "Day-to-day operations client compiled natively for Android tablets/phones and Windows PCs. High-speed cashier checkout, ESC/POS receipt printing, and 100% offline-first SQLite sync."
    ],
    [
        "title" => "🏪 Built-in Online Storefront",
        "tag" => "E-COMMERCE ENGINE",
        "tagCol" => $green,
        "desc" => "Instant web store for every business tenant. Custom apex domain mapping (shop.client.com), full product catalog, shopping cart, discount coupons, and live order tracking links."
    ]
];

$cw = 580; $ch = 180; $cy = 1060; $cg = 30;
$cxStart = (int)(($tw - (3 * $cw + 2 * $cg)) / 2);
foreach ($surfaces as $idx => $s) {
    $cx = $cxStart + ($idx * ($cw + $cg));
    drawFeatureCard($canvas, $cx, $cy, $cw, $ch, $s["tag"], $s["tagCol"], $s["title"], $s["desc"], $fontBold, $fontReg, $white, $muted, $cardBorder);
}

// Subdomain & Tenant Architecture Callout Card (Y: 1270 to 1470)
imagefilledrectangle($canvas, 100, 1270, 1900, 1470, imagecolorallocatealpha($canvas, 15, 23, 42, 50));
imagerectangle($canvas, 100, 1270, 1900, 1470, $cardBorderCyan);

imagettftext($canvas, 18, 0, 140, 1315, $white, $fontBold, "🏢 True Multi-Tenancy: Automatic Tenant Subdomains & Apex Domain Mapping");
imagettftext($canvas, 13, 0, 140, 1350, $muted, $fontReg, "Every subscribing store receives an isolated workspace (e.g. bakery.yourdomain.com) with separate staff, sales, inventory, and branding. Tenants can also map their own custom domain (e.g. pos.mybakery.com) with automatic SSL certificates.");

// 4 Tenant Domain Pills
$tPills = ["🏪 retail-outlet.yourdomain.com", "🍽️ bistro-downtown.yourdomain.com", "💊 pharmacy-health.yourdomain.com", "🌐 pos.customdomain.com"];
$txP = 140;
foreach ($tPills as $tp) {
    imagefilledrectangle($canvas, $txP, 1380, $txP + 390, 1430, imagecolorallocatealpha($canvas, 99, 102, 241, 80));
    imagerectangle($canvas, $txP, 1380, $txP + 390, 1430, $cyan);
    imagettftext($canvas, 11, 0, $txP + 20, 1412, $offWhite, $fontBold, $tp);
    $txP += 420;
}


// =========================================================================
// SECTION 3: RETAIL & RESTAURANT POS (1500 - 2350)
// =========================================================================
echo "Rendering Retail & Restaurant POS Section...\n";
drawSectionHdr($canvas, 1520, "02", "RETAIL & RESTAURANT POINT OF SALE", "High-Speed Cashier Checkout, Hardware Automation & Kitchen Dispatch", "Engineered for rapid transactions under heavy retail and restaurant foot traffic.", $green, $fontBold, $fontReg, $white, $muted);

// Embed Real POS Screenshot
embedImage($canvas, "storage/app/public/branding/cNB5eoDX25w35xiGoDEZpUP15Vfy3ixrDOJnM5fi.png", 1700, 1720, 360);

// 6 POS Feature Cards (Y: 2090 to 2320, 2 rows of 3 cols)
$posFeats = [
    ["title" => "Barcode & Scanner Support", "tag" => "HARDWARE", "tagCol" => $cyan, "desc" => "Native support for USB/Bluetooth handheld barcode scanners (HID), built-in camera scanning, and label printing."],
    ["title" => "Weighing-Scale Barcodes", "tag" => "DECIMAL WEIGHT", "tagCol" => $gold, "desc" => "Directly decodes embedded price and weight barcodes from electronic supermarket and butcher weighing scales."],
    ["title" => "Held Orders & Split Tenders", "tag" => "CHECKOUT", "tagCol" => $green, "desc" => "Park unfinished customer orders and resume anytime. Accept payments split across Cash, Card, Credit, and Bank Transfer."],
    ["title" => "Restaurant Tables & Floor Plan", "tag" => "DINE-IN", "tagCol" => $purple, "desc" => "Interactive visual floor layout with real-time color-coded table occupancy (Free, Seated, Bill Requested, Cleaning)."],
    ["title" => "Kitchen Display System (KDS)", "tag" => "LIVE KITCHEN", "tagCol" => $cyan, "desc" => "Live kitchen terminal showing order tickets, preparation stages, and color-coded elapsed time alerts."],
    ["title" => "Kitchen Order Tickets (KOT)", "tag" => "THERMAL KOT", "tagCol" => $gold, "desc" => "Direct 80mm & 58mm thermal ticket routing to bar/kitchen printers with auto cash-drawer kick port."]
];

$cw3 = 580; $ch3 = 100;
foreach ($posFeats as $idx => $pf) {
    $col = $idx % 3;
    $row = (int)floor($idx / 3);
    $cx = $cxStart + ($col * ($cw3 + $cg));
    $cy = 2090 + ($row * ($ch3 + 18));
    drawFeatureCard($canvas, $cx, $cy, $cw3, $ch3, $pf["tag"], $pf["tagCol"], $pf["title"], $pf["desc"], $fontBold, $fontReg, $white, $muted, $cardBorder);
}


// =========================================================================
// SECTION 4: BUILT-IN ONLINE STOREFRONT (2350 - 3150)
// =========================================================================
echo "Rendering Online Storefront Section...\n";
drawSectionHdr($canvas, 2370, "03", "ONLINE STOREFRONT & E-COMMERCE", "Turn-Key E-Commerce Storefront with Custom Apex Domain", "Empower every tenant to sell online 24/7 with seamless inventory synchronization.", $purple, $fontBold, $fontReg, $white, $muted);

// Embed Storefront UI Mockup
embedImage($canvas, "/home/zoomnearby-saas/.gemini/antigravity-cli/brain/daef70a3-7ba7-46d3-981d-4449453f303f/online_storefront_mockup_1790202805212.jpg", 2550, 1720, 360);

// 6 Storefront Features Cards (Y: 2940 to 3120)
$storeFeats = [
    ["title" => "Live Store & Custom Domain", "tag" => "CUSTOM DOMAIN", "tagCol" => $cyan, "desc" => "Map custom apex domains (shop.mybrand.com) with automated SSL encryption and responsive mobile layout."],
    ["title" => "Menus & CMS Pages", "tag" => "CMS BUILDER", "tagCol" => $purple, "desc" => "Visual navigation menu builder, drag-and-drop custom pages, policies, about us, and promo landing pages."],
    ["title" => "Cart, Wishlist & Checkout", "tag" => "CONVERSIONS", "tagCol" => $green, "desc" => "Frictionless shopping cart drawer, customer wishlists, and multi-step checkout with delivery address books."],
    ["title" => "Coupons & Discounts", "tag" => "PROMOTIONS", "tagCol" => $gold, "desc" => "Percentage and fixed amount promo coupon codes with start/expiration dates and per-customer usage limits."],
    ["title" => "Live Order Tracking Links", "tag" => "SELF-SERVICE", "tagCol" => $cyan, "desc" => "Public shareable order tracking portal with real-time status updates from placed to out-for-delivery."],
    ["title" => "Ratings, Reviews & FAQs", "tag" => "SOCIAL PROOF", "tagCol" => $purple, "desc" => "Customer product reviews with star ratings and admin approval moderation, plus searchable FAQ accordion."]
];

foreach ($storeFeats as $idx => $sf) {
    $col = $idx % 3;
    $row = (int)floor($idx / 3);
    $cx = $cxStart + ($col * ($cw3 + $cg));
    $cy = 2940 + ($row * ($ch3 + 18));
    drawFeatureCard($canvas, $cx, $cy, $cw3, $ch3, $sf["tag"], $sf["tagCol"], $sf["title"], $sf["desc"], $fontBold, $fontReg, $white, $muted, $cardBorder);
}


// =========================================================================
// SECTION 5: PAYMENTS & FINANCIAL MANAGEMENT (3150 - 3900)
// =========================================================================
echo "Rendering Payments & Financial Management Section...\n";
drawSectionHdr($canvas, 3170, "04", "PAYMENTS & FINANCIAL MANAGEMENT", "Global Payment Gateways, Cash Registers & Customer Credit Ledgers", "Complete financial reconciliation, split payments, customer credit accounts, and supplier bills.", $gold, $fontBold, $fontReg, $white, $muted);

$payFeats = [
    ["title" => "Global Payment Gateways", "tag" => "STRIPE / PAYPAL", "tagCol" => $cyan, "desc" => "Pre-integrated with Stripe, PayPal, Razorpay, UPI, Pix, and direct Bank Transfer with automated recurring billing."],
    ["title" => "Customer Credit & Ledgers", "tag" => "ACCOUNTS RECEIVABLE", "tagCol" => $gold, "desc" => "Sell on credit with custom credit limits. Track outstanding balances and log partial settlements automatically."],
    ["title" => "Cash Shift & Z-Reports", "tag" => "TILL AUDITING", "tagCol" => $green, "desc" => "Opening float denomination breakdown, petty cash in/out tracking, and end-of-shift cash drawer Z-reports."],
    ["title" => "Automated Receivables", "tag" => "SCHEDULED REMINDERS", "tagCol" => $purple, "desc" => "Automatically nudge customers with overdue invoices via scheduled WhatsApp, SMS, and Email payment links."],
    ["title" => "Vendor Bills & Payables", "tag" => "ACCOUNTS PAYABLE", "tagCol" => $cyan, "desc" => "Record supplier purchases, schedule payment due dates, and track outgoing business cash flow in real time."],
    ["title" => "Tax Rules & Fiscal QR Codes", "tag" => "TAX COMPLIANCE", "tagCol" => $gold, "desc" => "Multi-rate country tax engines, inclusive/exclusive calculations, and electronic fiscal QR invoice generator."]
];

$cw2 = 880; $ch2 = 110; $cg2 = 40;
$cxStart2 = (int)(($tw - (2 * $cw2 + $cg2)) / 2);
foreach ($payFeats as $idx => $pf) {
    $col = $idx % 2;
    $row = (int)floor($idx / 2);
    $cx = $cxStart2 + ($col * ($cw2 + $cg2));
    $cy = 3360 + ($row * ($ch2 + 20));
    drawFeatureCard($canvas, $cx, $cy, $cw2, $ch2, $pf["tag"], $pf["tagCol"], $pf["title"], $pf["desc"], $fontBold, $fontReg, $white, $muted, $cardBorder);
}

// Payment Logo Strip (Y: 3770 to 3860)
imagefilledrectangle($canvas, 100, 3770, 1900, 3860, imagecolorallocatealpha($canvas, 15, 23, 42, 50));
imagerectangle($canvas, 100, 3770, 1900, 3860, $cardBorder);
$pBadges = ["💳 Stripe Credit & Debit", "🅿️ PayPal Smart Checkout", "⚡ Razorpay Payment Suite", "🇮🇳 UPI & QR Pay", "🇧🇷 Pix Instant Transfer", "🏦 Bank Transfer & Cheque"];
$pX = 140;
foreach ($pBadges as $pb) {
    imagettftext($canvas, 13, 0, $pX, 3822, $offWhite, $fontBold, $pb);
    $pX += 290;
}


// =========================================================================
// SECTION 6: COMMUNICATION ENGINE (3900 - 4650)
// =========================================================================
echo "Rendering Communication Engine Section...\n";
drawSectionHdr($canvas, 3920, "05", "COMMUNICATION & NOTIFICATIONS", "WhatsApp Business Cloud API, SMS Gateways & Custom SMTP Email", "Automate customer follow-ups, invoice delivery, and staff notifications across all channels.", $green, $fontBold, $fontReg, $white, $muted);

$commFeats = [
    ["title" => "WhatsApp Cloud API", "tag" => "META OFFICIAL", "tagCol" => $green, "desc" => "Official WhatsApp Business API integration. Send digital thermal receipts, order confirmations, appointment reminders, and repair updates straight to customer WhatsApp."],
    ["title" => "Multi-Gateway SMS", "tag" => "INSTANT SMS", "tagCol" => $cyan, "desc" => "Plug-and-play integrations with Twilio, Vonage, MSG91, and custom HTTP SMS gateways for OTP phone logins, transaction alerts, and promotional announcements."],
    ["title" => "Custom SMTP Email", "tag" => "BRANDED EMAIL", "tagCol" => $purple, "desc" => "Configure custom tenant SMTP credentials (SendGrid, Mailgun, Amazon SES). Send beautifully formatted PDF invoices, password resets, and monthly subscription summaries."],
    ["title" => "Automated Event Rules", "tag" => "SMART TRIGGERS", "tagCol" => $gold, "desc" => "Set up event-based automation rules: instant notification on new orders, automated receivable reminders every 3 days, and low stock alerts dispatched to managers."]
];

foreach ($commFeats as $idx => $cf) {
    $col = $idx % 2;
    $row = (int)floor($idx / 2);
    $cx = $cxStart2 + ($col * ($cw2 + $cg2));
    $cy = 4110 + ($row * (170 + 20));
    drawFeatureCard($canvas, $cx, $cy, $cw2, 170, $cf["tag"], $cf["tagCol"], $cf["title"], $cf["desc"], $fontBold, $fontReg, $white, $muted, $cardBorder);
}

// Channels Pill Strip (Y: 4520 to 4610)
imagefilledrectangle($canvas, 100, 4520, 1900, 4610, imagecolorallocatealpha($canvas, 15, 23, 42, 50));
imagerectangle($canvas, 100, 4520, 1900, 4610, $cardBorderGreen);
imagettftext($canvas, 13, 0, 140, 4572, $green, $fontBold, "💬 WhatsApp Cloud API");
imagettftext($canvas, 13, 0, 480, 4572, $cyan, $fontBold, "📱 Twilio & Vonage SMS");
imagettftext($canvas, 13, 0, 840, 4572, $purple, $fontBold, "📧 Custom SMTP / SendGrid");
imagettftext($canvas, 13, 0, 1260, 4572, $gold, $fontBold, "🔔 Firebase Web & Mobile Push");
imagettftext($canvas, 13, 0, 1680, 4572, $white, $fontBold, "⚡ Webhook Alerts");


// =========================================================================
// SECTION 7: AI STUDIO & VISION (4650 - 5400)
// =========================================================================
echo "Rendering AI Studio & Vision Section...\n";
drawSectionHdr($canvas, 4670, "06", "AI STUDIO & COMPUTER VISION", "AI-Powered Product Photography Studio & Smart Camera Vision", "Revolutionize catalog creation with generative studio imagery and barcode computer vision.", $purple, $fontBold, $fontReg, $white, $muted);

// Embed AI Studio UI Mockup
embedImage($canvas, "/home/zoomnearby-saas/.gemini/antigravity-cli/brain/daef70a3-7ba7-46d3-981d-4449453f303f/ai_studio_vision_mockup_1790202819883.jpg", 4850, 1720, 360);

// 4 AI Cards (Y: 5240 to 5380)
$aiFeats = [
    ["title" => "AI Product Photography Studio", "tag" => "IMAGE GENERATION", "tagCol" => $purple, "desc" => "Generate studio-quality product photos from text prompts without hiring professional photographers."],
    ["title" => "Studio Lighting & Backgrounds", "tag" => "AUTO-ENHANCE", "tagCol" => $cyan, "desc" => "Pre-configured lighting presets (Minimalist, Studio Contrast, Clean E-Commerce) with automated background cleanup."],
    ["title" => "AI Computer Vision Scanner", "tag" => "CAMERA VISION", "tagCol" => $green, "desc" => "Point mobile device camera at physical products to detect barcodes, item attributes, and auto-populate catalog SKUs."],
    ["title" => "Catalog Batch Enrichment", "tag" => "BULK PIPELINE", "tagCol" => $gold, "desc" => "Queued background processing generates images and SEO descriptions for thousands of imported inventory items."]
];

$cw4 = 430; $cg4 = 23;
$cxStart4 = (int)(($tw - (4 * $cw4 + 3 * $cg4)) / 2);
foreach ($aiFeats as $idx => $af) {
    $cx = $cxStart4 + ($idx * ($cw4 + $cg4));
    drawFeatureCard($canvas, $cx, 5240, $cw4, 140, $af["tag"], $af["tagCol"], $af["title"], $af["desc"], $fontBold, $fontReg, $white, $muted, $cardBorder);
}


// =========================================================================
// SECTION 8: WEBHOOKS & COMMERCE INTEGRATIONS (5400 - 6150)
// =========================================================================
echo "Rendering Webhooks & E-Commerce Integrations Section...\n";
drawSectionHdr($canvas, 5420, "07", "ECOMMERCE & WEBHOOK INTEGRATIONS", "Two-Way Synchronization with Shopify, WooCommerce & Custom Webhooks", "Bridge physical in-store POS sales with external e-commerce channels in real time.", $cyan, $fontBold, $fontReg, $white, $muted);

$integFeats = [
    ["title" => "Shopify Two-Way Sync", "tag" => "SHOPIFY INTEGRATION", "tagCol" => $green, "desc" => "Sync Shopify orders into the central POS pipeline and automatically update inventory quantities when items sell in-store, preventing overselling."],
    ["title" => "WooCommerce Connector", "tag" => "WOOCOMMERCE", "tagCol" => $purple, "desc" => "Direct REST API integration with WordPress WooCommerce. Centralize catalog items, sync customer profiles, and process unified fulfillment reports."],
    ["title" => "Custom Outbound Webhooks", "tag" => "REAL-TIME EVENTS", "tagCol" => $cyan, "desc" => "Trigger instant HTTP POST webhooks for critical events: order.created, order.completed, stock.low, customer.new, and invoice.paid."],
    ["title" => "Custom Headless API", "tag" => "HEADLESS ARCHITECTURE", "tagCol" => $gold, "desc" => "Build custom kiosk interfaces, loyalty mobile apps, or integrate with existing ERP systems using the fully documented Headless JSON API."]
];

foreach ($integFeats as $idx => $inf) {
    $col = $idx % 2;
    $row = (int)floor($idx / 2);
    $cx = $cxStart2 + ($col * ($cw2 + $cg2));
    $cy = 5610 + ($row * (170 + 20));
    drawFeatureCard($canvas, $cx, $cy, $cw2, 170, $inf["tag"], $inf["tagCol"], $inf["title"], $inf["desc"], $fontBold, $fontReg, $white, $muted, $cardBorder);
}

// Integration Architecture Strip (Y: 6020 to 6110)
imagefilledrectangle($canvas, 100, 6020, 1900, 6110, imagecolorallocatealpha($canvas, 15, 23, 42, 50));
imagerectangle($canvas, 100, 6020, 1900, 6110, $cardBorderCyan);
imagettftext($canvas, 13, 0, 140, 6072, $green, $fontBold, "🟢 Shopify App Bridge");
imagettftext($canvas, 13, 0, 520, 6072, $purple, $fontBold, "🟣 WooCommerce REST API");
imagettftext($canvas, 13, 0, 920, 6072, $cyan, $fontBold, "⚡ Event-Driven Webhooks");
imagettftext($canvas, 13, 0, 1320, 6072, $gold, $fontBold, "🔄 Automatic Stock Deduplication");
imagettftext($canvas, 13, 0, 1700, 6072, $white, $fontBold, "📦 Headless JSON");


// =========================================================================
// SECTION 9: DEVELOPER HUB & REST API (6150 - 6900)
// =========================================================================
echo "Rendering Developer Hub & REST API Section...\n";
drawSectionHdr($canvas, 6170, "08", "DEVELOPER HUB & REST API", "Full RESTful Endpoints, Scoped Bearer Tokens & HMAC Signatures", "A clean developer experience with comprehensive API token controls and cryptographic verification.", $cyan, $fontBold, $fontReg, $white, $muted);

// Embed Developer API Mockup
embedImage($canvas, "/home/zoomnearby-saas/.gemini/antigravity-cli/brain/daef70a3-7ba7-46d3-981d-4449453f303f/developer_api_webhooks_mockup_1790202835176.jpg", 6350, 1720, 360);

// 4 API Feature Cards (Y: 6740 to 6880)
$apiFeats = [
    ["title" => "Bearer Token Authentication", "tag" => "SECURITY", "tagCol" => $cyan, "desc" => "Secure OAuth2 / Sanctum Bearer token authentication with customizable expiry and revocation."],
    ["title" => "Granular Token Scopes", "tag" => "SCOPES & ROLES", "tagCol" => $purple, "desc" => "Limit external integrations to specific permissions: products:read, orders:write, customers:manage."],
    ["title" => "HMAC-SHA256 Signatures", "tag" => "TAMPER-PROOF", "tagCol" => $green, "desc" => "Every outgoing webhook includes a cryptographic signature header ensuring data integrity."],
    ["title" => "Interactive Docs & cURL", "tag" => "DOCUMENTATION", "tagCol" => $gold, "desc" => "Complete Postman collections and cURL request/response samples for rapid third-party development."]
];

foreach ($apiFeats as $idx => $apf) {
    $cx = $cxStart4 + ($idx * ($cw4 + $cg4));
    drawFeatureCard($canvas, $cx, 6740, $cw4, 140, $apf["tag"], $apf["tagCol"], $af["title"], $apf["desc"], $fontBold, $fontReg, $white, $muted, $cardBorder);
}


// =========================================================================
// SECTION 10: ANALYTICS & BUSINESS INTELLIGENCE (6900 - 7650)
// =========================================================================
echo "Rendering Analytics & Reports Section...\n";
drawSectionHdr($canvas, 6920, "09", "ANALYTICS & BUSINESS INTELLIGENCE", "Real-Time Sales Metrics, P&L Statements & Staff Commissions", "Executive business oversight with deep drill-down analytics and instant CSV/PDF exports.", $purple, $fontBold, $fontReg, $white, $muted);

$repFeats = [
    ["title" => "Executive KPI Dashboard", "tag" => "REAL-TIME", "tagCol" => $cyan, "desc" => "Track gross revenue, net sales, profit margins, average order value (AOV), and customer retention in real time."],
    ["title" => "Profit & Loss (P&L) Reports", "tag" => "FINANCIAL HEALTH", "tagCol" => $green, "desc" => "Automatic COGS calculation, revenue minus expenses, tax liabilities, and net margin tracking per store."],
    ["title" => "Staff Sales Commissions", "tag" => "PERFORMANCE", "tagCol" => $gold, "desc" => "Set period sales targets, assign commission percentages to staff cashiers, and generate payout balance sheets."],
    ["title" => "Payment Channel Ledgers", "tag" => "RECONCILIATION", "tagCol" => $purple, "desc" => "Detailed breakdown of sales volume across Cash, Card, UPI, Stripe, and Customer Credit with CSV exports."],
    ["title" => "Till Closing Z-Report Audit", "tag" => "CASH REGISTER", "tagCol" => $cyan, "desc" => "Complete historical audit trail of all cashier shifts, counted cash versus expected totals, and variance logs."],
    ["title" => "Inventory Valuation & Aging", "tag" => "STOCK CONTROL", "tagCol" => $gold, "desc" => "Current inventory asset value, low stock warnings, dead inventory analysis, and batch expiry schedules."]
];

foreach ($repFeats as $idx => $rf) {
    $col = $idx % 3;
    $row = (int)floor($idx / 3);
    $cx = $cxStart + ($col * ($cw3 + $cg));
    $cy = 7110 + ($row * ($ch3 + 18));
    drawFeatureCard($canvas, $cx, $cy, $cw3, $ch3, $rf["tag"], $rf["tagCol"], $rf["title"], $rf["desc"], $fontBold, $fontReg, $white, $muted, $cardBorder);
}

// Export Badge Strip (Y: 7490 to 7580)
imagefilledrectangle($canvas, 100, 7490, 1900, 7580, imagecolorallocatealpha($canvas, 15, 23, 42, 50));
imagerectangle($canvas, 100, 7490, 1900, 7580, $cardBorder);
imagettftext($canvas, 13, 0, 140, 7542, $white, $fontBold, "📊 Export Formats: Excel (.xlsx), CSV, Formatted PDF, ESC/POS Print");
imagettftext($canvas, 13, 0, 880, 7542, $green, $fontBold, "📈 Filter by: Custom Date Range, Branch, Cashier, Category, Payment Method");
imagettftext($canvas, 13, 0, 1620, 7542, $gold, $fontBold, "⚡ Instant Live Queries");


// =========================================================================
// SECTION 11: ADMINISTRATION & STAFF MANAGEMENT (7650 - 8400)
// =========================================================================
echo "Rendering Administration & Staff Management Section...\n";
drawSectionHdr($canvas, 7670, "10", "ADMINISTRATION & ACCESS CONTROL", "Multi-Branch Management, Granular Roles & Active Device Oversight", "Comprehensive security controls designed for multi-location teams and strict permission policies.", $cyan, $fontBold, $fontReg, $white, $muted);

$adminFeats = [
    ["title" => "Multi-Branch Store Switcher", "tag" => "MULTI-LOCATION", "tagCol" => $cyan, "desc" => "Manage unlimited physical branches, warehouses, or kiosks. Cashiers switch active store location with one click, attributing inventory and sales correctly."],
    ["title" => "Granular Permission Presets", "tag" => "ROLE-BASED ACCESS", "tagCol" => $purple, "desc" => "Define exact permissions per role (Cashier, Store Manager, Waiter, Technician). Disallow price overrides, restrict financial report views, and control stock edits."],
    ["title" => "Active Device Session Oversight", "tag" => "SECURITY", "tagCol" => $green, "desc" => "View all active signed-in phones, POS tablets, and desktop terminals. Instantly revoke compromised or lost employee device sessions with one click."],
    ["title" => "Admin Impersonation ('Switch to User')", "tag" => "DIAGNOSTICS", "tagCol" => $gold, "desc" => "Authorized platform administrators can temporarily view the system through a specific staff member's permissions to diagnose and resolve reported issues."]
];

foreach ($adminFeats as $idx => $amf) {
    $col = $idx % 2;
    $row = (int)floor($idx / 2);
    $cx = $cxStart2 + ($col * ($cw2 + $cg2));
    $cy = 7860 + ($row * (170 + 20));
    drawFeatureCard($canvas, $cx, $cy, $cw2, 170, $amf["tag"], $amf["tagCol"], $amf["title"], $amf["desc"], $fontBold, $fontReg, $white, $muted, $cardBorder);
}

// Admin Safeguards Strip (Y: 8270 to 8360)
imagefilledrectangle($canvas, 100, 8270, 1900, 8360, imagecolorallocatealpha($canvas, 15, 23, 42, 50));
imagerectangle($canvas, 100, 8270, 1900, 8360, $cardBorderCyan);
imagettftext($canvas, 13, 0, 140, 8322, $cyan, $fontBold, "🛡️ Primary Branch Safeguard");
imagettftext($canvas, 13, 0, 560, 8322, $green, $fontBold, "🔒 Two-Factor / Email OTP Auth");
imagettftext($canvas, 13, 0, 980, 8322, $purple, $fontBold, "📋 Detailed System Activity Audit Logs");
imagettftext($canvas, 13, 0, 1480, 8322, $gold, $fontBold, "🧹 1-Click Sample Data Cleanup");


// =========================================================================
// SECTION 12: SECURITY & INSTALLER (8400 - 9150)
// =========================================================================
echo "Rendering Security & 1-Click Web Installer Section...\n";
drawSectionHdr($canvas, 8420, "11", "ENTERPRISE ARCHITECTURE & DEPLOYMENT", "100% Data Isolation, Offline SQLite Engine & 1-Click Web Installer", "Deploy production-grade SaaS infrastructure with zero complex command-line headaches.", $green, $fontBold, $fontReg, $white, $muted);

$archFeats = [
    ["title" => "1-Click Guided Web Installer (/install)", "tag" => "ZERO-CODE SETUP", "tagCol" => $green, "desc" => "Point browser to your domain/install. Guided GUI wizard verifies PHP requirements, configures DB, runs migrations, and creates SuperAdmin in under 3 minutes."],
    ["title" => "100% Offline SQLite Sync Engine", "tag" => "OFFLINE RESILIENCE", "tagCol" => $cyan, "desc" => "Cashiers ring up sales locally when internet drops. Outgoing transactions are safely queued in local SQLite and auto-synced with conflict resolution upon reconnect."],
    ["title" => "Complete Tenant Data Partitioning", "tag" => "DATA PRIVACY", "tagCol" => $purple, "desc" => "Strict multi-tenant scoping ensures every tenant's catalog, sales, staff, and customer data remains completely isolated and protected."],
    ["title" => "1-Click System Backup Packages", "tag" => "DISASTER RECOVERY", "tagCol" => $gold, "desc" => "Generate downloadable compressed snapshot archives containing complete database schemas, tenant assets, and configurations on demand."]
];

foreach ($archFeats as $idx => $arf) {
    $col = $idx % 2;
    $row = (int)floor($idx / 2);
    $cx = $cxStart2 + ($col * ($cw2 + $cg2));
    $cy = 8610 + ($row * (170 + 20));
    drawFeatureCard($canvas, $cx, $cy, $cw2, 170, $arf["tag"], $arf["tagCol"], $arf["title"], $arf["desc"], $fontBold, $fontReg, $white, $muted, $cardBorder);
}

// Server Specs Strip (Y: 9020 to 9110)
imagefilledrectangle($canvas, 100, 9020, 1900, 9110, imagecolorallocatealpha($canvas, 15, 23, 42, 50));
imagerectangle($canvas, 100, 9020, 1900, 9110, $cardBorderGreen);
imagettftext($canvas, 13, 0, 140, 9072, $white, $fontBold, "⚙️ Backend: Laravel 11.x • PHP 8.2+ / 8.3+ • MySQL 8.0+ / MariaDB 10.4+");
imagettftext($canvas, 13, 0, 980, 9072, $cyan, $fontBold, "📱 Client: Flutter 3.x (Android APK & Windows Desktop EXE)");
imagettftext($canvas, 13, 0, 1620, 9072, $gold, $fontBold, "🚀 Runs on cPanel, VPS & AWS");


// =========================================================================
// SECTION 13: COMPLETE FEATURE MATRIX (9150 - 9650)
// =========================================================================
echo "Rendering Complete Feature Matrix Section...\n";
drawSectionHdr($canvas, 9170, "12", "COMPREHENSIVE FEATURE MATRIX", "Everything You Need to Run & Scale a Profitable SaaS Business", "A complete birds-eye checklist of modules, integrations, and capabilities included.", $gold, $fontBold, $fontReg, $white, $muted);

$cols = [
    [
        "title" => "Multi-Tenant SaaS Core",
        "color" => $purple,
        "items" => [
            "Tenant Subdomains & Domains",
            "Subscription Plans & Pricing",
            "Stripe & PayPal Auto-Billing",
            "Multi-Currency & Local Taxes",
            "1-Click Web Installer (/install)",
            "System Backup & Restore",
            "Landing Page CMS & Marketing"
        ]
    ],
    [
        "title" => "Point of Sale & Retail",
        "color" => $cyan,
        "items" => [
            "High-Speed Touch POS Grid",
            "Barcode & Scale Barcodes",
            "Held Orders & Split Tenders",
            "80mm & 58mm Thermal Receipts",
            "Cash Drawer & Z-Reports",
            "Customer Credit & Receivables",
            "Multi-Store Inventory Transfers"
        ]
    ],
    [
        "title" => "5 Industry Verticals",
        "color" => $green,
        "items" => [
            "Retail POS & Barcodes",
            "Restaurant Floor & Tables",
            "Kitchen Display System (KDS)",
            "Pharmacy Batches & Expiry",
            "Salon Stylist Calendar & Slots",
            "Repair Device Intake Tickets",
            "Lead CRM Kanban Pipeline"
        ]
    ],
    [
        "title" => "Flutter Apps & APIs",
        "color" => $gold,
        "items" => [
            "Android Mobile App (APK)",
            "Windows Desktop App (.exe)",
            "100% Offline SQLite Sync",
            "REST API & Bearer Tokens",
            "HMAC-SHA256 Webhooks",
            "WhatsApp Business Cloud API",
            "Shopify & WooCommerce Sync"
        ]
    ]
];

$mcW = 430; $mcG = 23; $mcY = 9360;
foreach ($cols as $idx => $c) {
    $mcX = $cxStart4 + ($idx * ($mcW + $mcG));
    imagefilledrectangle($canvas, $mcX, $mcY, $mcX + $mcW, $mcY + 260, $cardBg);
    imagerectangle($canvas, $mcX, $mcY, $mcX + $mcW, $mcY + 260, $c["color"]);
    
    imagettftext($canvas, 15, 0, $mcX + 20, $mcY + 36, $c["color"], $fontBold, $c["title"]);
    
    $itemY = $mcY + 70;
    foreach ($c["items"] as $it) {
        imagettftext($canvas, 11.5, 0, $mcX + 20, $itemY, $offWhite, $fontReg, "✔ " . $it);
        $itemY += 26;
    }
}


// =========================================================================
// SECTION 14: FINAL BUY NOW CTA (9650 - 10000)
// =========================================================================
echo "Rendering Final Call to Action Section...\n";
imagefilledrectangle($canvas, 0, 9660, $tw, $th, imagecolorallocatealpha($canvas, 10, 15, 35, 10));
imageline($canvas, 0, 9660, $tw, 9660, imagecolorallocatealpha($canvas, 99, 102, 241, 100));

// Big Callout
$ctaH1 = "Start Your Own Multi-Tenant POS SaaS Business Today";
$cBox = imagettfbbox(32, 0, $fontBold, $ctaH1);
$cx = (int)(($tw - ($cBox[2] - $cBox[0])) / 2);
imagettftext($canvas, 32, 0, $cx, 9740, $white, $fontBold, $ctaH1);

$ctaSub = "Deploy on your own server • Keep 100% of subscription revenue • 100% unencrypted source code • No monthly royalties";
$csBox = imagettfbbox(15, 0, $fontReg, $ctaSub);
$csx = (int)(($tw - ($csBox[2] - $csBox[0])) / 2);
imagettftext($canvas, 15, 0, $csx, 9785, $gold, $fontBold, $ctaSub);

// Final Guarantee Badges
$finalBadges = [
    ["✔ 100% Full Source Code", $green],
    ["✔ Lifetime Free Updates", $cyan],
    ["✔ 6 Months Developer Support", $purple],
    ["✔ WhatsApp: +91 85350 75196", $gold]
];
$totalFBW = 0; $fBoxes = [];
foreach ($finalBadges as $fb) {
    $box = imagettfbbox(12, 0, $fontBold, "  " . $fb[0] . "  ");
    $w = $box[2] - $box[0] + 24;
    $fBoxes[] = $w;
    $totalFBW += $w + 20;
}
$curFBX = (int)(($tw - $totalFBW) / 2);
foreach ($finalBadges as $idx => $fb) {
    $bw = $fBoxes[$idx];
    imagefilledrectangle($canvas, $curFBX, 9825, $curFBX + $bw, 9865, imagecolorallocatealpha($canvas, 15, 23, 42, 50));
    imagerectangle($canvas, $curFBX, 9825, $curFBX + $bw, 9865, $fb[1]);
    imagettftext($canvas, 11, 0, $curFBX + 12, 9851, $fb[1], $fontBold, "  " . $fb[0]);
    $curFBX += $bw + 20;
}

// Live Demo & Marketplace Footer Link
$footText = "Live Interactive Demos: SuperAdmin (https://saas.zoomnearby.com/login) • Documentation (https://saas.zoomnearby.com/documentation) • CodeCanyon Ready";
$ftBox = imagettfbbox(12, 0, $fontReg, $footText);
$ftx = (int)(($tw - ($ftBox[2] - $ftBox[0])) / 2);
imagettftext($canvas, 12, 0, $ftx, 9920, $muted, $fontReg, $footText);

$copy = "© ZooM Sales CRM & Inventory — All Rights Reserved. Built with Laravel 11 & Flutter 3.";
$cpBox = imagettfbbox(11, 0, $fontReg, $copy);
$cpx = (int)(($tw - ($cpBox[2] - $cpBox[0])) / 2);
imagettftext($canvas, 11, 0, $cpx, 9960, $darkMuted, $fontReg, $copy);

// Save the 2000x10000 image
echo "Saving 2000x10000 image...\n";
imagejpeg($canvas, "public/promotional-banner-2000x10000.jpg", 92);
imagejpeg($canvas, "public/zoom-sales-crm-banner-2000x10000.jpg", 92);
imagejpeg($canvas, "public/assets/images/promotional-banner-2000x10000.jpg", 92);
imagejpeg($canvas, "public/documentation/images/promotional-banner-2000x10000.jpg", 92);
imagejpeg($canvas, "/home/zoomnearby-saas/.gemini/antigravity-cli/brain/daef70a3-7ba7-46d3-981d-4449453f303f/promotional-banner-2000x10000.jpg", 92);

imagedestroy($canvas);
echo "Successfully generated 2000x10000 promotional banner!\n";
