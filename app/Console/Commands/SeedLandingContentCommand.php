<?php

namespace App\Console\Commands;

use App\Models\PlatformBranding;
use Illuminate\Console\Command;

class SeedLandingContentCommand extends Command
{
    protected $signature = 'landing:seed-content {--force : Replace existing landing copy}';
    protected $description = 'Fill the public landing page with high-converting, production-ready omnichannel POS, online commerce, and inventory marketing copy.';

    public function handle(): int
    {
        $branding = PlatformBranding::current();
        if (! $this->option('force') && filled($branding->landing_content)) {
            $this->warn('Landing content already exists. Re-run with --force to replace it.');
            return self::FAILURE;
        }

        $branding->update([
            'landing_page_enabled' => true,
            'landing_hero_badge' => '⚡ The #1 Omnichannel POS & Cloud Commerce Engine',
            'landing_hero_title' => 'Scale Your Store Sales Online & In-Person with Zero Downtime',
            'landing_hero_subtitle' => 'Unify your online storefront, barcode checkout, multi-warehouse stock, and WhatsApp invoicing into one lightning-fast cloud POS. Built to turn internet visitors into repeat buyers and keep counters ringing up sales even offline.',
            'landing_hero_cta_primary_text' => 'Start Free Trial — Instant Access',
            'landing_hero_cta_secondary_text' => 'Explore Features',
            'landing_hero_cta_primary_url' => route('tenant.register'),
            'landing_hero_cta_secondary_url' => '#features',

            'landing_features' => [
                [
                    'icon' => '🌐',
                    'title' => 'Online Store & Digital Catalog',
                    'body' => "Instant mobile-friendly digital storefront with category & brand filtering\nOne-click WhatsApp product link & cart sharing for direct social commerce\nDirect QR code ordering with instant payment gateway integration\nBenefit: Launch eCommerce in minutes, capture internet buyers with zero marketplace fees, and sync orders automatically.",
                    'mockup' => 'pos',
                ],
                [
                    'icon' => '🛒',
                    'title' => 'Sub-Second Barcode POS Checkout',
                    'body' => "Millisecond barcode scanning with quick-access visual favorites & held carts\nMulti-tender split payments (Cash, Card, UPI, Wallets, Customer Store Credit)\nHigh-speed thermal receipt printing with automated cash drawer kick pulse\nBenefit: Eliminates counter checkout bottlenecks, handles peak holiday crowds effortlessly, and rings up sales 3x faster.",
                    'mockup' => 'pos',
                ],
                [
                    'icon' => '📦',
                    'title' => 'Omnichannel Inventory & Warehouse Sync',
                    'body' => "Real-time stock synchronization across online store, physical shops, and central warehouses\nBatch, lot, and expiry date tracking with automatic low-stock reorder thresholds\nInter-branch stock consignments and transfers with dispatch/receiving audit trails\nBenefit: Prevents overselling on the web, eliminates stockouts, and stops capital from locking up in excess inventory.",
                    'mockup' => 'inventory',
                ],
                [
                    'icon' => '⚡',
                    'title' => 'Sub-Second 100% Offline-First POS Engine',
                    'body' => "Full counter operations, barcode search, cart calculations, and receipt printing without internet\nAutomatic background synchronization on reconnect with tamper-proof duplicate prevention\nContinuous local data caching so tills never freeze during network cuts\nBenefit: Zero downtime and zero lost sales when internet drops during peak shopping hours.",
                    'mockup' => 'pos',
                ],
                [
                    'icon' => '🧾',
                    'title' => 'Automated Tax Invoicing (GST/VAT) & WhatsApp Delivery',
                    'body' => "Compliant tax invoices generated automatically with itemized tax breakdowns and HSN/SAC codes\nOne-tap instant dispatch to customer WhatsApp, SMS, and Email with branded PDF\nThermal receipts (58mm/80mm) alongside enterprise formatted A4 PDF tax invoices\nBenefit: 100% tax and audit compliance, zero paper waste, and 98% WhatsApp receipt open rates for customer re-engagement.",
                    'mockup' => 'finance',
                ],
                [
                    'icon' => '💼',
                    'title' => 'Quotations, Estimates & Proforma Invoicing',
                    'body' => "Professional quotation builder with customizable discounts, terms, and validity dates\nOne-click automated conversion from Quote to confirmed Sale and Invoice\nBranded PDF downloads and direct customer sharing via email or messaging\nBenefit: Speeds up B2B and wholesale deal closures, eliminates duplicate manual data entry, and accelerates cash flow.",
                    'mockup' => 'finance',
                ],
                [
                    'icon' => '💵',
                    'title' => 'Cash Register Audit & Blind Shift Reconciliation',
                    'body' => "Opening cash float registration, paid-in/paid-out petty cash vouchers, and blind counts\nAutomated mid-shift X-Reports and end-of-day Z-Reports with per-cashier accountability\nReal-time cash variance detection that isolates discrepancies per drawer\nBenefit: Stops drawer shrinkage, prevents employee theft, and slashes daily register closing time from hours to minutes.",
                    'mockup' => 'finance',
                ],
                [
                    'icon' => '📊',
                    'title' => 'Accounts Receivable (AR), Payables (AP) & Ledgers',
                    'body' => "Customer credit limits, balance statements, and aged receivables tracking\nSupplier purchase bills, payment schedules, and outstanding ledger balances\nComprehensive transaction history and automated debit/credit balancing\nBenefit: Maximizes working capital visibility, reduces bad debts, and maintains strong supplier trade terms.",
                    'mockup' => 'finance',
                ],
                [
                    'icon' => '🧑‍🤝‍🧑',
                    'title' => 'Customer CRM, Lead Pipeline & Loyalty Points',
                    'body' => "360-degree customer purchasing profiles, contact directories, and buying habits\nSales lead management pipeline with activity logging, follow-up reminders, and stage tracking\nAutomated customer loyalty reward points that accumulate and redeem at checkout\nBenefit: Boosts customer lifetime value (LTV) and average order value (AOV) by 25% through personalized loyalty perks.",
                ],
                [
                    'icon' => '🍽️',
                    'title' => 'Restaurant Floor, Table QR & Kitchen KDS',
                    'body' => "Visual table floor plans with live occupied, dining, and billing status\nContactless Table QR menu ordering — guests scan, browse, and order from phones\nKitchen Order Tickets (KOT) routed directly to live Kitchen Display System (KDS) screens\nBenefit: Accelerates table turns by 35%, eliminates kitchen order errors, and lowers waitstaff overhead.",
                    'mockup' => 'restaurant',
                ],
                [
                    'icon' => '💊',
                    'title' => 'Pharmacy Drug Batch & Expiration Management',
                    'body' => "Pharmaceutical drug batch and lot tracking with strict expiration date monitoring\nPrescription record management, doctor attribution, and patient dosage instructions\nFlexible unit conversions (box, strip, tablet, bottle) with batch-level costing\nBenefit: Total health regulatory compliance, zero expired medicine dispensed, and minimized shrinkage.",
                ],
                [
                    'icon' => '🔧',
                    'title' => 'Repair Workshop & Service Ticket Management',
                    'body' => "Complete repair lifecycle (Received -> Diagnosing -> Parts Ordered -> Ready -> Delivered)\nDevice serial number/IMEI tracking, intake diagnostic notes, and warranty logs\nIntegrated spare parts inventory deduction and technician labor invoicing\nBenefit: Unlocks high-margin repair service revenue for electronics, computer, and bike shops with total transparency.",
                ],
                [
                    'icon' => '✂️',
                    'title' => 'Salon, Spa & Appointment Scheduling Calendar',
                    'body' => "Visual appointment calendar with stylist/therapist scheduling and room assignment\nService catalog with custom durations, add-on treatments, and pricing tiers\nAutomatic stylist commission calculation based on completed services and retail product upsells\nBenefit: Eliminates appointment conflicts, optimizes chair utilization, and motivates staff with accurate commission payouts.",
                ],
                [
                    'icon' => '📈',
                    'title' => 'Sales Targets, Executive Analytics & Real-Time P&L',
                    'body' => "Branch, cashier, and staff sales target monitoring with real-time achievement progress\nLive Profit & Loss (P&L) statements, gross margins, and cost-of-goods-sold (COGS) analytics\nTop-selling products, category contribution, and dead-stock identification\nBenefit: Gives business owners 100% financial clarity to cut underperforming lines and maximize net profitability.",
                    'mockup' => 'finance',
                ],
                [
                    'icon' => '🏢',
                    'title' => 'Multi-Branch Franchise & Centralized Control',
                    'body' => "Centralized catalog management with branch-specific pricing and localized tax rates\nInter-branch stock transfer requests with transit tracking and receiving audits\nConsolidated corporate reports with isolated tenant workspace security\nBenefit: Scale effortlessly from one neighborhood shop to hundreds of franchise locations nationwide.",
                ],
                [
                    'icon' => '🔐',
                    'title' => 'Role-Based Permissions & Tamper-Proof Audit Logs',
                    'body' => "Granular per-module permissions (cashiers, store managers, stock clerks, accountants)\nDevice authorization and terminal registration to prevent unauthorized logins\nTamper-proof audit trails for every price override, discount, held cart, and refund\nBenefit: Guards profit margins against cashier discount abuse and keeps operations strictly compliant.",
                ],
                [
                    'icon' => '📱',
                    'title' => 'Native Cross-Platform Apps (Web, Android, Windows)',
                    'body' => "Dedicated native Android APK and Windows desktop app for full-screen counter immersion\nDirect ESC/POS thermal printer communication via USB, Bluetooth, and LAN/Ethernet\nRuns on existing hardware — tablets, POS all-in-one terminals, laptops, or mobile phones\nBenefit: No expensive proprietary hardware locks — saves thousands in initial setup and maintenance costs.",
                ],
            ],

            'landing_faqs' => [
                [
                    'q' => 'How does this platform help my online and retail store get more sales?',
                    'a' => 'It seamlessly connects your physical counter and internet shoppers. You can publish an interactive digital catalog, share product links directly to customer WhatsApp, take contactless QR & card payments, and automatically capture customer contact details with digital receipts to drive high-converting repeat sales.',
                ],
                [
                    'q' => 'Does the POS continue working when the internet drops?',
                    'a' => 'Yes, 100%. Product lookup, barcode scanning, cart calculations, and checkout continue running locally on your device without pause. When internet connection returns, offline sales synchronize automatically in the background with zero data loss and zero duplicate entries.',
                ],
                [
                    'q' => 'Can I manage both an online store and multiple physical store branches?',
                    'a' => 'Yes. A single workspace lets you manage unlimited physical branches, warehouses, and online catalogs with live synchronized stock, inter-branch transfers with receiving audits, branch-specific pricing, and unified executive analytics.',
                ],
                [
                    'q' => 'Which hardware devices and printers are supported?',
                    'a' => 'Any standard USB or Bluetooth barcode scanner, 80mm and 58mm thermal receipt printers, auto-kick cash drawers, EMV/NFC card terminals, and kitchen display monitors. If it connects to Windows, Android, or browser, it works out of the box — no expensive proprietary hardware to buy.',
                ],
                [
                    'q' => 'Are tax invoices and receipts compliant with GST / VAT?',
                    'a' => 'Yes. Tax invoices (GST, VAT, HSN/SAC) are generated automatically with itemized tax breakdowns, sequential numbering, thermal receipt formatting, and branded A4 PDF exports that can be sent straight to customers over WhatsApp or email.',
                ],
                [
                    'q' => 'Can I migrate my existing products and customer data?',
                    'a' => 'Yes. With our built-in bulk CSV import tool, you can upload your full product catalog, SKUs, barcodes, prices, stock levels, and customer records in minutes without typing them manually.',
                ],
                [
                    'q' => 'Can I use this for restaurants, cafés, and bakeries too?',
                    'a' => 'Yes. Simply toggle on restaurant mode to get interactive table floor plans, Kitchen Order Tickets (KOT) sent to kitchen screens, table QR code ordering, food modifiers, and one-tap bill splitting.',
                ],
                [
                    'q' => 'Can I white-label this platform with my own brand and custom domain?',
                    'a' => 'Yes. Customize your platform name, logo, favicon, accent colors, and custom domain to run a completely branded SaaS experience for your stores or clients.',
                ],
                [
                    'q' => 'Is there a free trial, and do I need to enter credit card details?',
                    'a' => 'You can launch your store workspace and test all features with zero risk. No credit card is required, no setup fees, and no long-term contracts. Upgrade or cancel anytime directly from your dashboard.',
                ],
            ],

            'landing_testimonials' => [
                [
                    'quote' => 'Switching to this platform doubled our online order throughput while cutting counter checkout times in half. The live inventory sync between our web store and physical shops prevented overselling completely.',
                    'name' => 'Marcus Vance',
                    'role' => 'Founder & CEO · Urban Horizon Omnichannel',
                ],
                [
                    'quote' => 'During our Black Friday holiday rush, our fiber internet went down for nearly three hours. The offline engine kept our counters ringing up sales without skipping a beat. It saved us thousands in lost sales.',
                    'name' => 'Sophia Sterling',
                    'role' => 'Head of Operations · Sterling Luxury Retail',
                ],
                [
                    'quote' => 'We run 6 restaurant and bakery outlets. Having table QR ordering, instant KOT kitchen display routing, and automated WhatsApp receipts in one system transformed our bottom line.',
                    'name' => 'David Al-Mansoor',
                    'role' => 'Managing Director · Artisan Dine Group',
                ],
            ],

            'landing_content' => [
                'homepage_mode' => 'modular',
                'hero' => [
                    'highlights' => [
                        'Instant Online Store & WhatsApp Orders',
                        'Sub-Second Barcode Checkout (100% Offline-Ready)',
                        'Live Multi-Store Inventory Sync',
                        'Automated GST & VAT Tax Invoicing',
                    ],
                    'dashboard_title' => 'Omnichannel Commerce Engine',
                    'dashboard_status' => 'All Channels Connected',
                    'total_label' => "Today's Revenue",
                    'payment_label' => 'Split Online / Card / Cash',
                    'total_amount' => '$1,840.50',
                    'receipt_title' => 'Online Order #2048 · Web Store',
                    'receipt_sub' => 'Express Delivery · Riya M.',
                    'receipt_badge' => 'Order Synced',
                    'products' => [
                        ['☕ Single-Origin Ethiopian Roast 1kg', '$28.50', 'In Stock', 'emerald'],
                        ['🎧 Wireless Noise-Canceling Pro', '$149.00', 'In Stock', 'emerald'],
                        ['🫒 Organic Cold-Pressed Olive Oil 500ml', '$18.90', 'Low Stock (3 left)', 'amber'],
                        ['🌾 Stone-Ground Organic Flour 1kg', '$8.50', 'In Stock', 'emerald'],
                    ],
                ],
                'trust' => [
                    'hardware' => [
                        ['Barcode & QR Scanners', 'Instant Zero-Latency Read', 'barcode'],
                        ['Thermal Receipt Printers', '58mm & 80mm ESC/POS', 'printer'],
                        ['Card Readers & QR Displays', 'UPI, EMV, Contactless & NFC', 'card'],
                        ['Smart Cash Drawers', 'Automated Kick-Open Pulse', 'drawer'],
                        ['Kitchen & Packing Screens', 'Live KDS & Dispatch Screen', 'display'],
                    ],
                ],
                'stats' => [
                    ['2,500,000+', 'Online & In-Store Orders Processed'],
                    ['1,200+', 'Thriving Store Outlets'],
                    ['99.99%', 'Enterprise Cloud Uptime SLA'],
                    ['< 20ms', 'Sub-Second Checkout Latency'],
                ],
                'solutions' => [
                    'items' => [
                        ['🚀', 'Drive Online & Social Commerce Sales', 'Publish a sleek digital storefront in minutes. Share products and order links directly over WhatsApp, Instagram, and SMS. Capture customer details at checkout to run automated re-engagement promotions.'],
                        ['⚡', 'Sub-Second Speed & 100% Offline Resilience', 'Never lose a sale when internet drops. Barcode scanning, cart totals, and thermal receipt printing work continuously offline, syncing automatically upon reconnect.'],
                        ['📦', 'Live Multi-Channel Stock Sync — Zero Overselling', 'Keep online store inventory and physical shop stock aligned in real time. Central stock updates instantly when an order arrives online or at the counter.'],
                        ['🧾', 'Automated Tax Invoicing & Instant WhatsApp Receipts', 'Generate compliant GST/VAT tax invoices with itemized tax breakdowns. Dispatch digital receipts directly to customer WhatsApp or email in 1 click, building a high-converting repeat sales channel.'],
                    ],
                ],
                'cta' => [
                    'primary_text' => 'Start Free Trial — Instant Access',
                    'primary_url' => route('tenant.register'),
                    'secondary_text' => 'Sign In to Dashboard',
                    'secondary_url' => route('tenant.login'),
                ],
            ],

            'landing_section_meta' => [
                'hero' => [
                    'badge' => '⚡ The #1 Omnichannel POS & Cloud Commerce Engine',
                    'title' => 'Scale Your Store Sales Online & In-Person with Zero Downtime',
                    'subtitle' => 'Unify your online storefront, barcode checkout, multi-warehouse stock, and WhatsApp invoicing into one lightning-fast cloud POS. Built to turn internet visitors into repeat buyers and keep counters ringing up sales even offline.',
                ],
                'trust_bar' => [
                    'title' => 'Works out of the box with your existing retail & dining hardware',
                ],
                'features' => [
                    'badge' => 'Unified Operations Suite',
                    'title' => 'Everything Your Business Needs to Maximize Internet & In-Store Sales',
                    'subtitle' => 'From online catalogs and WhatsApp orders to barcode checkout, multi-store stock, and automated tax invoicing — one synchronized cloud platform.',
                ],
                'solutions' => [
                    'badge' => 'Engineered For Maximum Conversion',
                    'title' => 'Why Modern Online Stores & Retailers Choose Our Platform',
                    'subtitle' => 'Designed from the ground up to boost online revenue, eliminate inventory discrepancies, and keep counter checkouts flying during peak rushes.',
                ],
                'downloads' => [
                    'badge' => 'Native Mobile & Desktop Apps',
                    'title' => 'Take Your Counter and Store Anywhere',
                    'subtitle' => 'Install our high-performance native Android or Windows apps for sub-second offline speed, instant USB/Bluetooth thermal printer integration, and a dedicated full-screen till experience.',
                ],
                'stats' => [
                    'title' => 'Proven At Enterprise Scale',
                    'subtitle' => 'Powering high-velocity merchants, multi-branch franchises, and fast-growing online brands.',
                ],
                'about' => [
                    'badge' => 'Our Mission',
                    'title' => 'Built to Turn Every Online Store & Counter into a High-Revenue Machine',
                    'subtitle' => 'We exist to empower online merchants, retailers, and food businesses with enterprise-grade commerce infrastructure without enterprise complexity or exorbitant fees.',
                    'body' => 'We exist to empower online merchants, retailers, and food businesses with enterprise-grade commerce infrastructure without enterprise complexity or exorbitant fees. By uniting your online storefront, front-counter barcode checkout, multi-warehouse inventory, and automated tax invoicing into one synchronized engine, we remove software friction so you can focus on what matters: acquiring customers, expanding your catalog, and scaling your profit.',
                ],
                'testimonials' => [
                    'badge' => 'Proven Results',
                    'title' => 'Trusted by Leading Online Brands & Retailers Worldwide',
                    'subtitle' => 'See how omnichannel businesses use our cloud commerce engine to drive revenue and save hours every single day.',
                ],
                'pricing' => [
                    'badge' => 'Predictable Investment',
                    'title' => 'Simple, Transparent Pricing with Guaranteed ROI',
                    'subtitle' => 'Launch your omnichannel store workspace in minutes with zero setup fees and zero hidden charges.',
                ],
                'faq' => [
                    'badge' => 'Answers & Clarity',
                    'title' => 'Frequently Asked Questions',
                    'subtitle' => 'Everything you need to know about scaling online sales, hardware setup, and offline POS reliability.',
                ],
                'contact' => [
                    'badge' => 'Get In Touch',
                    'title' => 'Speak with an Omnichannel POS Specialist',
                    'subtitle' => 'Need a tailored setup for multi-location retail, restaurant chains, or online catalog migration? Our solutions engineering team replies within 24 hours.',
                ],
                'cta' => [
                    'badge' => 'Instant Store Provisioning',
                    'title' => 'Ready to Scale Your Online & In-Store Sales?',
                    'subtitle' => 'Launch your omnichannel store workspace in 60 seconds. Sell online, ring up counter checkouts offline, and sync stock across branches. No credit card required.',
                ],
            ],
        ]);

        $this->info('Landing content seeded with production sales copy. Open SuperAdmin → Settings → White-label & Branding to refine it.');
        return self::SUCCESS;
    }
}
