<?php

namespace AppConsole\Commands;

use AppModelsPlatformBranding;
use IlluminateConsoleCommand;

class SeedLandingContentCommand extends Command
{
    protected $signature = 'landing:seed-content {--force : Replace existing landing copy}';
    protected $description = 'Fill the public landing page with ready-to-use POS and inventory marketing content.';

    public function handle(): int
    {
        $branding = PlatformBranding::current();
        if (! $this->option('force') && filled($branding->landing_content)) {
            $this->warn('Landing content already exists. Re-run with --force to replace it.');
            return self::FAILURE;
        }

        $branding->update([
            'landing_page_enabled' => true,
            'landing_hero_badge' => 'All-in-one POS, inventory and business management',
            'landing_hero_title' => 'Sell faster. Control stock. Grow with confidence.',
            'landing_hero_subtitle' => 'A complete cloud POS for retail stores, restaurants and growing businesses — checkout, inventory, purchasing, invoicing, payments and reports in one simple workspace.',
            'landing_hero_cta_primary_text' => 'Start your free trial',
            'landing_hero_cta_secondary_text' => 'Explore features',
            'landing_features' => [
                ['icon'=>'📦','title'=>'Inventory that stays accurate','body'=>'Track stock by branch, warehouse, batch and expiry. Get low-stock alerts before a popular item runs out.'],
                ['icon'=>'🛒','title'=>'Fast retail checkout','body'=>'Scan barcodes, split payments, apply discounts and print or send receipts in seconds.'],
                ['icon'=>'🍽️','title'=>'Restaurant and café POS','body'=>'Manage tables, KOTs, modifiers, kitchen screens, bill splits and takeaway orders from one screen.'],
                ['icon'=>'🧾','title'=>'Invoices, GST and ledgers','body'=>'Create compliant invoices, monitor receivables and payables, and keep every transaction audit-ready.'],
                ['icon'=>'📶','title'=>'Works when the internet drops','body'=>'Continue selling offline. Transactions queue securely and sync automatically when the connection returns.'],
                ['icon'=>'👥','title'=>'Customers, credit and loyalty','body'=>'Maintain customer statements, credit limits, payment history and loyalty rewards to increase repeat business.'],
            ],
            'landing_faqs' => [
                ['q'=>'Can I use it for both retail and restaurants?','a'=>'Yes. Enable retail checkout, restaurant tables, KOT and kitchen display workflows in the same workspace.'],
                ['q'=>'Does the POS work offline?','a'=>'Yes. Checkout and product search continue offline, then synchronize safely when connectivity returns.'],
                ['q'=>'Can I manage multiple branches?','a'=>'Yes. Share one catalogue while tracking stock, pricing, staff and reports separately for each location.'],
                ['q'=>'Can customers receive invoices digitally?','a'=>'Yes. Send receipts and invoices by email or WhatsApp, or print them on compatible thermal printers.'],
            ],
            'landing_testimonials' => [
                ['quote'=>'Our cashiers learned the system in one afternoon, and stock adjustments are finally visible across every outlet.','name'=>'Riya Mehta','role'=>'Operations Manager · Horizon Retail'],
                ['quote'=>'The offline checkout and kitchen workflow keep our café moving even during network interruptions.','name'=>'Arjun Nair','role'=>'Owner · Bean & Basket Café'],
            ],
            'landing_content' => [
                'hero' => ['highlights' => ['Barcode checkout','Live stock alerts','GST-ready invoices','Offline-first sync'], 'dashboard_title' => 'Live POS workspace', 'dashboard_status' => 'Connected', 'total_label' => 'Today\'s sales', 'payment_label' => 'All payment methods', 'total_amount' => '₹41,600', 'products' => [['☕ Coffee roast 1kg','₹1,450','In Stock','emerald'],['🫒 Truffle oil 500ml','₹1,820','In Stock','emerald'],['🌾 Almond flour 1kg','₹890','Low Stock','amber']]],
                'trust' => ['hardware' => [['Barcode scanners','Instant scan'],['Thermal printers','58mm / 80mm'],['Card terminals','EMI & NFC'],['Cash drawers','Auto open'],['Kitchen displays','Live KDS']]],
                'stats' => [['2.5M+','Transactions processed'],['1,200+','Business outlets'],['99.99%','Platform uptime'],['<20ms','Checkout latency']],
                'solutions' => ['items' => [['⚡','Fast and reliable','Keep selling through busy periods and unreliable internet.'],['📊','Clear business control','See sales, stock, cash and profit in real time.'],['🏢','Ready to scale','Add branches, staff and warehouses without changing systems.']]],
            ],
        ]);

        $this->info('Landing content seeded. Open SuperAdmin → Settings → White-label & Branding to refine it.');
        return self::SUCCESS;
    }
}
