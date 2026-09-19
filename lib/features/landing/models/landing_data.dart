class LandingData {
  const LandingData({
    required this.theme,
    required this.branding,
    required this.hero,
    required this.hardware,
    required this.solutions,
    required this.features,
    required this.stats,
    required this.plans,
    required this.testimonials,
    required this.faqs,
    required this.downloads,
    required this.contact,
  });

  final String theme;
  final LandingBranding branding;
  final LandingHero hero;
  final List<LandingHardwareItem> hardware;
  final List<LandingSolutionItem> solutions;
  final List<LandingFeatureItem> features;
  final List<LandingStatItem> stats;
  final List<LandingPlanItem> plans;
  final List<LandingTestimonialItem> testimonials;
  final List<LandingFaqItem> faqs;
  final LandingDownloads downloads;
  final LandingContact contact;

  factory LandingData.fromJson(Map<String, dynamic> json) {
    final brandingMap = json['branding'] is Map<String, dynamic>
        ? json['branding'] as Map<String, dynamic>
        : <String, dynamic>{};
    final heroMap = json['hero'] is Map<String, dynamic>
        ? json['hero'] as Map<String, dynamic>
        : <String, dynamic>{};
    final downloadsMap = json['downloads'] is Map<String, dynamic>
        ? json['downloads'] as Map<String, dynamic>
        : <String, dynamic>{};
    final contactMap = json['contact'] is Map<String, dynamic>
        ? json['contact'] as Map<String, dynamic>
        : <String, dynamic>{};

    return LandingData(
      theme: json['theme']?.toString() ?? 'theme_fast',
      branding: LandingBranding.fromJson(brandingMap),
      hero: LandingHero.fromJson(heroMap),
      hardware: (json['hardware'] as List<dynamic>?)
              ?.whereType<Map<String, dynamic>>()
              .map(LandingHardwareItem.fromJson)
              .toList() ??
          [],
      solutions: (json['solutions'] as List<dynamic>?)
              ?.whereType<Map<String, dynamic>>()
              .map(LandingSolutionItem.fromJson)
              .toList() ??
          [],
      features: (json['features'] as List<dynamic>?)
              ?.whereType<Map<String, dynamic>>()
              .map(LandingFeatureItem.fromJson)
              .toList() ??
          [],
      stats: (json['stats'] as List<dynamic>?)
              ?.whereType<Map<String, dynamic>>()
              .map(LandingStatItem.fromJson)
              .toList() ??
          [],
      plans: (json['plans'] as List<dynamic>?)
              ?.whereType<Map<String, dynamic>>()
              .map(LandingPlanItem.fromJson)
              .toList() ??
          [],
      testimonials: (json['testimonials'] as List<dynamic>?)
              ?.whereType<Map<String, dynamic>>()
              .map(LandingTestimonialItem.fromJson)
              .toList() ??
          [],
      faqs: (json['faqs'] as List<dynamic>?)
              ?.whereType<Map<String, dynamic>>()
              .map(LandingFaqItem.fromJson)
              .toList() ??
          [],
      downloads: LandingDownloads.fromJson(downloadsMap),
      contact: LandingContact.fromJson(contactMap),
    );
  }

  static LandingData fallback() => LandingData(
        theme: 'theme_fast',
        branding: LandingBranding.fallback(),
        hero: LandingHero.fallback(),
        hardware: [
          const LandingHardwareItem(
            icon: '🖨️',
            title: 'Thermal Printers',
            desc: '58mm / 80mm ESC/POS via USB, Bluetooth & WiFi',
            tag: 'Plug & Play',
          ),
          const LandingHardwareItem(
            icon: '📦',
            title: 'Barcode Scanners',
            desc: '1D/2D QR handheld & fixed continuous scanners',
            tag: 'Zero Setup',
          ),
          const LandingHardwareItem(
            icon: '💵',
            title: 'Cash Drawers',
            desc: 'Auto-kick RJ11 electronic cash drawers',
            tag: 'Instant Trigger',
          ),
          const LandingHardwareItem(
            icon: '🍳',
            title: 'Kitchen Displays',
            desc: 'Live KOT order routing & chef dispatch station',
            tag: 'Live Sync',
          ),
        ],
        solutions: [
          const LandingSolutionItem(
            icon: '🛒',
            title: 'Retail & Supermarkets',
            desc:
                'High-speed barcode scanning, variant matrix, stock audits, customer loyalty, and multi-counter cash drawers.',
          ),
          const LandingSolutionItem(
            icon: '🍽️',
            title: 'Restaurants & Cafés',
            desc:
                'Interactive table floor plans, split bills, kitchen order tickets (KOT), recipe modifiers, and food delivery sync.',
          ),
          const LandingSolutionItem(
            icon: '💊',
            title: 'Pharmacies & Healthcare',
            desc:
                'Batch number and expiry tracking, doctor prescription records, schedule drug audits, and automated stock reordering.',
          ),
          const LandingSolutionItem(
            icon: '✂️',
            title: 'Salons & Spas',
            desc:
                'Staff commissions, appointment booking, chair management, package memberships, and SMS appointment reminders.',
          ),
        ],
        features: [
          const LandingFeatureItem(
            icon: '⚡',
            title: 'Ultra-Fast Counter Checkout',
            body:
                'Complete sales in seconds with lightning-fast barcode lookup, split payments, cash change calculation, and instant receipt printing.',
          ),
          const LandingFeatureItem(
            icon: '🔌',
            title: '100% Offline-First Architecture',
            body:
                'Never lose a sale during internet outages. Counters operate seamlessly offline with local caching and automatic background synchronization.',
          ),
          const LandingFeatureItem(
            icon: '🌐',
            title: 'eCommerce Storefront & WhatsApp Orders',
            body:
                'Publish an interactive digital storefront in one click. Allow customers to browse inventory and send orders directly to your WhatsApp.',
          ),
          const LandingFeatureItem(
            icon: '📊',
            title: 'Live Real-Time Inventory Tracking',
            body:
                'Track stock levels across all branches, receive low-stock alerts, manage inter-branch transfers, and audit discrepancies with ease.',
          ),
          const LandingFeatureItem(
            icon: '📱',
            title: 'Native Android & Windows Apps',
            body:
                'Run on any device — counter PCs, Windows POS terminals, tablets, and Android handhelds with direct ESC/POS hardware support.',
          ),
          const LandingFeatureItem(
            icon: '🧾',
            title: 'Compliant Tax Invoices & Reports',
            body:
                'Generate GST/VAT compliant invoices, export daily Z-reports, view profit margins, and track cash drawer balances effortlessly.',
          ),
        ],
        stats: [
          const LandingStatItem(value: '130+', label: 'Happy Businesses'),
          const LandingStatItem(value: '4.9/5', label: 'Customer Rating'),
          const LandingStatItem(value: '99.9%', label: 'Uptime Guarantee'),
          const LandingStatItem(value: '24/7', label: 'Customer Support'),
        ],
        plans: [
          const LandingPlanItem(
            id: 1,
            name: 'Starter',
            price: 19.0,
            billingPeriod: 'monthly',
            description: 'Essential tools for single-counter stores',
            features: [
              '1 Store Location',
              'Up to 1,000 Products',
              'Offline-Ready POS',
              'Receipt Printing',
              'Standard Email Support',
            ],
            isFeatured: false,
          ),
          const LandingPlanItem(
            id: 2,
            name: 'Professional',
            price: 49.0,
            billingPeriod: 'monthly',
            description: 'Best for growing multi-counter retail & restaurants',
            features: [
              'Multi-Location Support',
              'Unlimited Products & Invoices',
              'eCommerce Digital Storefront',
              'Table Floor Management & KOT',
              'Staff Permissions & Audit Logs',
              'Priority WhatsApp Support',
            ],
            isFeatured: true,
          ),
          const LandingPlanItem(
            id: 3,
            name: 'Enterprise',
            price: 99.0,
            billingPeriod: 'monthly',
            description: 'Full custom deployment with white-label branding',
            features: [
              'Unlimited Branches & Warehouses',
              'Dedicated Database & Domain',
              'White-Label Custom Branding',
              'Automated Cloud Backups',
              'Dedicated Account Manager',
            ],
            isFeatured: false,
          ),
        ],
        testimonials: [
          const LandingTestimonialItem(
            quote:
                'Switching to this platform doubled our online order throughput while cutting counter checkout times in half. The live inventory sync between web and store prevented overselling completely.',
            name: 'Marcus Vance',
            role: 'Founder & CEO · Urban Horizon Omnichannel',
          ),
          const LandingTestimonialItem(
            quote:
                'During Black Friday rush, our fiber internet dropped for nearly 3 hours. The offline engine kept our counters ringing up sales without skipping a beat. It saved us thousands in lost revenue.',
            name: 'Sophia Sterling',
            role: 'Head of Operations · Sterling Luxury Retail',
          ),
          const LandingTestimonialItem(
            quote:
                'We run 6 restaurant outlets. Having table QR ordering, instant KOT kitchen routing, and automated WhatsApp receipts in one unified system transformed our bottom line.',
            name: 'David Al-Mansoor',
            role: 'Managing Director · Artisan Dine Group',
          ),
        ],
        faqs: [
          const LandingFaqItem(
            question: 'Does the POS continue working when the internet drops?',
            answer:
                'Yes, 100%. Product lookup, barcode scanning, cart calculations, and checkout continue running locally on your device without pause. When internet connection returns, offline sales synchronize automatically in the background with zero data loss.',
          ),
          const LandingFaqItem(
            question: 'Which hardware devices and printers are supported?',
            answer:
                'Any standard USB or Bluetooth barcode scanner, 80mm and 58mm thermal receipt printers, auto-kick cash drawers, EMV/NFC card terminals, and kitchen display monitors. If it connects to Windows, Android, or browser, it works out of the box.',
          ),
          const LandingFaqItem(
            question:
                'Can I manage an online storefront and multiple physical branches?',
            answer:
                'Yes. A single workspace lets you manage unlimited physical branches, warehouses, and online catalogs with live synchronized stock, inter-branch transfers with receiving audits, and unified executive analytics.',
          ),
          const LandingFaqItem(
            question: 'Is there a free trial without credit card?',
            answer:
                'You can launch your store workspace and test all features with zero risk. No credit card is required, no setup fees, and no long-term contracts. Upgrade or cancel anytime directly from your dashboard.',
          ),
        ],
        downloads: const LandingDownloads(
          playstoreEnabled: true,
          playstoreUrl: 'https://saas.zoomnearby.com/zoom-pos-v1.0.2.apk',
          windowsEnabled: true,
          windowsUrl:
              'https://saas.zoomnearby.com/zoom-sales-crm-software-1.0.2.exe',
        ),
        contact: const LandingContact(
          supportPhone: '+918535075196',
          supportWhatsapp: '+918535075196',
          supportEmail: 'support@zoomnearby.com',
          pageTitle: 'Get in Touch',
          pageSubtitle:
              'Questions before you sign up or need a tailored enterprise setup? Our specialists are ready to help.',
        ),
      );
}

class LandingBranding {
  const LandingBranding({
    required this.platformName,
    this.logoUrl,
    this.faviconUrl,
    this.primaryColorHex = '#4f46e5',
    this.secondaryColorHex = '#0F172A',
    this.accentColorHex = '#d7f24e',
    this.darkBgHex = '#0b0f19',
    this.supportPhone = '+918535075196',
    this.supportWhatsapp = '+918535075196',
    this.supportEmail = 'support@zoomnearby.com',
    this.authBannerImageUrl,
    this.showAuthBanner = false,
  });

  final String platformName;
  final String? logoUrl;
  final String? faviconUrl;
  final String primaryColorHex;
  final String secondaryColorHex;
  final String accentColorHex;
  final String darkBgHex;
  final String supportPhone;
  final String supportWhatsapp;
  final String supportEmail;
  final String? authBannerImageUrl;
  final bool showAuthBanner;

  factory LandingBranding.fromJson(Map<String, dynamic> json) {
    return LandingBranding(
      platformName:
          json['platform_name']?.toString() ?? 'Smart Inventory & Sales',
      logoUrl: json['logo_url']?.toString(),
      faviconUrl: json['favicon_url']?.toString(),
      primaryColorHex: json['primary_color']?.toString() ?? '#4f46e5',
      secondaryColorHex: json['secondary_color']?.toString() ?? '#0F172A',
      accentColorHex: json['accent_color']?.toString() ?? '#d7f24e',
      darkBgHex: json['landing_dark_bg']?.toString() ?? '#0b0f19',
      supportPhone: json['support_phone']?.toString() ?? '+918535075196',
      supportWhatsapp: json['support_whatsapp']?.toString() ?? '+918535075196',
      supportEmail: json['support_email']?.toString() ?? 'support@zoomnearby.com',
      authBannerImageUrl: json['auth_banner_image_url']?.toString(),
      showAuthBanner: json['show_auth_banner'] == true,
    );
  }

  factory LandingBranding.fallback() => const LandingBranding(
        platformName: 'Smart Inventory & Sales',
      );
}

class LandingHero {
  const LandingHero({
    required this.badge,
    required this.title,
    required this.subtitle,
    required this.ctaPrimaryText,
    required this.ctaPrimaryUrl,
    required this.ctaSecondaryText,
    required this.ctaSecondaryUrl,
    this.bannerImageUrl,
  });

  final String badge;
  final String title;
  final String subtitle;
  final String ctaPrimaryText;
  final String ctaPrimaryUrl;
  final String ctaSecondaryText;
  final String ctaSecondaryUrl;
  final String? bannerImageUrl;

  factory LandingHero.fromJson(Map<String, dynamic> json) {
    return LandingHero(
      badge: json['badge']?.toString() ?? '✨ Next-Gen Omnichannel POS',
      title: json['title']?.toString() ??
          'Sell Everywhere. Track Everything. Grow Faster.',
      subtitle: json['subtitle']?.toString() ??
          'Cloud flexibility with 100% offline-first reliability. Built for modern retail, restaurants, and growing enterprises.',
      ctaPrimaryText: json['cta_primary_text']?.toString() ?? 'Start Free Trial',
      ctaPrimaryUrl: json['cta_primary_url']?.toString() ?? '/register',
      ctaSecondaryText: json['cta_secondary_text']?.toString() ?? 'Live Demo',
      ctaSecondaryUrl: json['cta_secondary_url']?.toString() ?? '/login',
      bannerImageUrl: json['banner_image_url']?.toString(),
    );
  }

  factory LandingHero.fallback() => const LandingHero(
        badge: '✨ Next-Gen Omnichannel POS',
        title: 'Sell Everywhere. Track Everything. Grow Faster.',
        subtitle:
            'Cloud flexibility with 100% offline-first reliability. Built for modern retail, restaurants, and growing enterprises.',
        ctaPrimaryText: 'Start Free Trial',
        ctaPrimaryUrl: '/register',
        ctaSecondaryText: 'Sign In / Demo',
        ctaSecondaryUrl: '/login',
      );
}

class LandingHardwareItem {
  const LandingHardwareItem({
    required this.icon,
    required this.title,
    required this.desc,
    required this.tag,
  });

  final String icon;
  final String title;
  final String desc;
  final String tag;

  factory LandingHardwareItem.fromJson(Map<String, dynamic> json) {
    return LandingHardwareItem(
      icon: json['icon']?.toString() ?? '🔌',
      title: json['title']?.toString() ?? '',
      desc: json['desc']?.toString() ?? '',
      tag: json['tag']?.toString() ?? '',
    );
  }
}

class LandingSolutionItem {
  const LandingSolutionItem({
    required this.icon,
    required this.title,
    required this.desc,
  });

  final String icon;
  final String title;
  final String desc;

  factory LandingSolutionItem.fromJson(Map<String, dynamic> json) {
    return LandingSolutionItem(
      icon: json['icon']?.toString() ?? '🏢',
      title: json['title']?.toString() ?? '',
      desc: json['desc']?.toString() ?? '',
    );
  }
}

class LandingFeatureItem {
  const LandingFeatureItem({
    required this.icon,
    required this.title,
    required this.body,
  });

  final String icon;
  final String title;
  final String body;

  factory LandingFeatureItem.fromJson(Map<String, dynamic> json) {
    return LandingFeatureItem(
      icon: json['icon']?.toString() ?? '✨',
      title: json['title']?.toString() ?? '',
      body: json['body']?.toString() ?? '',
    );
  }
}

class LandingStatItem {
  const LandingStatItem({
    required this.value,
    required this.label,
  });

  final String value;
  final String label;

  factory LandingStatItem.fromJson(Map<String, dynamic> json) {
    return LandingStatItem(
      value: json['value']?.toString() ?? '',
      label: json['label']?.toString() ?? '',
    );
  }
}

class LandingPlanItem {
  const LandingPlanItem({
    this.id,
    required this.name,
    required this.price,
    required this.billingPeriod,
    this.description,
    required this.features,
    required this.isFeatured,
  });

  final dynamic id;
  final String name;
  final double price;
  final String billingPeriod;
  final String? description;
  final List<String> features;
  final bool isFeatured;

  factory LandingPlanItem.fromJson(Map<String, dynamic> json) {
    final rawFeatures = json['features'];
    final List<String> featureList = [];
    if (rawFeatures is List) {
      for (final f in rawFeatures) {
        if (f != null) featureList.add(f.toString());
      }
    } else if (rawFeatures is Map) {
      rawFeatures.forEach((key, val) {
        if (val == true) {
          featureList.add(key.toString().replaceAll('_', ' '));
        } else if (val is String && val.isNotEmpty) {
          featureList.add('$key: $val');
        }
      });
    }

    return LandingPlanItem(
      id: json['id'],
      name: json['name']?.toString() ?? 'Standard',
      price: (json['price'] as num?)?.toDouble() ?? 0.0,
      billingPeriod: json['billing_period']?.toString() ?? 'monthly',
      description: json['description']?.toString(),
      features: featureList,
      isFeatured: json['is_featured'] == true,
    );
  }
}

class LandingTestimonialItem {
  const LandingTestimonialItem({
    required this.quote,
    required this.name,
    required this.role,
  });

  final String quote;
  final String name;
  final String role;

  factory LandingTestimonialItem.fromJson(Map<String, dynamic> json) {
    return LandingTestimonialItem(
      quote: json['quote']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      role: json['role']?.toString() ?? '',
    );
  }
}

class LandingFaqItem {
  const LandingFaqItem({
    required this.question,
    required this.answer,
  });

  final String question;
  final String answer;

  factory LandingFaqItem.fromJson(Map<String, dynamic> json) {
    return LandingFaqItem(
      question: (json['q'] ?? json['question'])?.toString() ?? '',
      answer: (json['a'] ?? json['answer'])?.toString() ?? '',
    );
  }
}

class LandingDownloads {
  const LandingDownloads({
    required this.playstoreEnabled,
    this.playstoreUrl,
    required this.windowsEnabled,
    this.windowsUrl,
  });

  final bool playstoreEnabled;
  final String? playstoreUrl;
  final bool windowsEnabled;
  final String? windowsUrl;

  factory LandingDownloads.fromJson(Map<String, dynamic> json) {
    return LandingDownloads(
      playstoreEnabled: json['playstore_enabled'] == true,
      playstoreUrl: json['playstore_url']?.toString(),
      windowsEnabled: json['windows_enabled'] == true,
      windowsUrl: json['windows_url']?.toString(),
    );
  }
}

class LandingContact {
  const LandingContact({
    required this.supportPhone,
    required this.supportWhatsapp,
    required this.supportEmail,
    required this.pageTitle,
    required this.pageSubtitle,
  });

  final String supportPhone;
  final String supportWhatsapp;
  final String supportEmail;
  final String pageTitle;
  final String pageSubtitle;

  factory LandingContact.fromJson(Map<String, dynamic> json) {
    final settings = json['settings'] is Map<String, dynamic>
        ? json['settings'] as Map<String, dynamic>
        : <String, dynamic>{};

    return LandingContact(
      supportPhone: json['support_phone']?.toString() ?? '+918535075196',
      supportWhatsapp: json['support_whatsapp']?.toString() ?? '+918535075196',
      supportEmail: json['support_email']?.toString() ?? 'support@zoomnearby.com',
      pageTitle: settings['page_title']?.toString() ?? 'Get in Touch',
      pageSubtitle: settings['page_subtitle']?.toString() ??
          'Questions before you sign up or need a tailored enterprise setup? Our specialists are ready to help.',
    );
  }
}
