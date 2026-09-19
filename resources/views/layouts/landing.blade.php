@php
    $themeConfig = \Illuminate\Support\Facades\Cache::rememberForever('landing_page_theme_config', function() {
        if (\Illuminate\Support\Facades\Schema::hasTable('system_settings')) {
            $raw = \Illuminate\Support\Facades\DB::table('system_settings')->where('key', 'superadmin_theme_customization')->value('value');
            if ($raw) return json_decode($raw, true) ?: [];
        }
        $val = setting('landing_dark_bg');
        return $val ? ['landing_dark_bg' => $val] : [];
    });
    $landingDarkBg = $themeConfig['landing_dark_bg'] ?? setting('landing_dark_bg', '#0b0f19');

    $palette = \Illuminate\Support\Facades\Cache::rememberForever('landing_sections_theme_palette', function () {
        if (\Illuminate\Support\Facades\Schema::hasTable('system_settings')) {
            $raw = \Illuminate\Support\Facades\DB::table('system_settings')->where('key', 'landing_sections_theme_palette')->value('value');
            if ($raw) return json_decode($raw, true) ?: [];
        }
        $val = setting('landing_sections_theme_palette');
        return $val ? (is_array($val) ? $val : json_decode($val, true)) : [];
    });
    $defaults = default_landing_sections_palette();
    $palette = array_replace_recursive($defaults, is_array($palette) ? $palette : []);
@endphp

<style>
  /* Light Theme CSS Variables */
  :root {
    --landing-dark-bg: {{ $landingDarkBg }};
    @foreach(['hero', 'features', 'mission', 'pricing', 'faq', 'cta', 'contact'] as $sec)
      --landing-{{ $sec }}-bg: {{ $palette[$sec]['light_bg'] ?? '#ffffff' }};
      --landing-{{ $sec }}-text: {{ $palette[$sec]['light_text'] ?? '#0f172a' }};
      --landing-{{ $sec }}-muted: {{ $palette[$sec]['light_muted'] ?? '#64748b' }};
    @endforeach
    --landing-contact-card-bg: {{ $palette['contact']['light_card_bg'] ?? '#ffffff' }};
    --landing-about-bg: var(--landing-mission-bg);
    --landing-about-text: var(--landing-mission-text);
    --landing-about-muted: var(--landing-mission-muted);
    --landing-trust-bg: var(--landing-hero-bg);
    --landing-trust-text: var(--landing-hero-text);
    --landing-trust-muted: var(--landing-hero-muted);
    --landing-stats-bg: var(--landing-features-bg);
    --landing-stats-text: var(--landing-features-text);
    --landing-stats-muted: var(--landing-features-muted);
    --landing-solutions-bg: var(--landing-features-bg);
    --landing-solutions-text: var(--landing-features-text);
    --landing-solutions-muted: var(--landing-features-muted);
    --landing-downloads-bg: var(--landing-features-bg);
    --landing-downloads-text: var(--landing-features-text);
    --landing-downloads-muted: var(--landing-features-muted);
    --landing-testimonials-bg: var(--landing-features-bg);
    --landing-testimonials-text: var(--landing-features-text);
    --landing-testimonials-muted: var(--landing-features-muted);
  }

  /* Dark Theme CSS Variables (applied when html.dark or data-theme="dark" is active) */
  html.dark,
  [data-theme="dark"] {
    @foreach(['hero', 'features', 'mission', 'pricing', 'faq', 'cta', 'contact'] as $sec)
      --landing-{{ $sec }}-bg: {{ $palette[$sec]['dark_bg'] ?? '#0b0f19' }};
      --landing-{{ $sec }}-text: {{ $palette[$sec]['dark_text'] ?? '#f8fafc' }};
      --landing-{{ $sec }}-muted: {{ $palette[$sec]['dark_muted'] ?? '#94a3b8' }};
    @endforeach
    --landing-contact-dark-bg: {{ $palette['contact']['dark_bg'] ?? '#0b0f19' }};
    --landing-contact-card-dark-bg: {{ $palette['contact']['dark_card_bg'] ?? '#131e29' }};
    --landing-about-bg: var(--landing-mission-bg);
    --landing-about-text: var(--landing-mission-text);
    --landing-about-muted: var(--landing-mission-muted);
    --landing-trust-bg: var(--landing-hero-bg);
    --landing-trust-text: var(--landing-hero-text);
    --landing-trust-muted: var(--landing-hero-muted);
    --landing-stats-bg: var(--landing-features-bg);
    --landing-stats-text: var(--landing-features-text);
    --landing-stats-muted: var(--landing-features-muted);
    --landing-solutions-bg: var(--landing-features-bg);
    --landing-solutions-text: var(--landing-features-text);
    --landing-solutions-muted: var(--landing-features-muted);
    --landing-downloads-bg: var(--landing-features-bg);
    --landing-downloads-text: var(--landing-features-text);
    --landing-downloads-muted: var(--landing-features-muted);
    --landing-testimonials-bg: var(--landing-features-bg);
    --landing-testimonials-text: var(--landing-features-text);
    --landing-testimonials-muted: var(--landing-features-muted);
  }

  /* Target all dark sections and mission/pricing wrappers in DARK mode ONLY */
  html.dark .landing-dark-section,
  [data-theme="dark"] .landing-dark-section,
  html.dark .bg-dark-hero,
  [data-theme="dark"] .bg-dark-hero,
  html.dark section[class*="bg-slate-900"],
  [data-theme="dark"] section[class*="bg-slate-900"],
  html.dark section[class*="bg-gray-900"],
  [data-theme="dark"] section[class*="bg-gray-900"],
  html.dark section[class*="bg-black"],
  [data-theme="dark"] section[class*="bg-black"] {
      background-color: var(--landing-dark-bg) !important;
  }
  html.dark .landing-dark-section h1,
  html.dark .landing-dark-section h2,
  html.dark .landing-dark-section h3,
  [data-theme="dark"] .landing-dark-section h1,
  [data-theme="dark"] .landing-dark-section h2,
  [data-theme="dark"] .landing-dark-section h3 {
      color: #ffffff !important;
  }
  html.dark .landing-dark-section p,
  [data-theme="dark"] .landing-dark-section p {
      color: #e2e8f0 !important;
  }

  /* Semantic Section Classes */
  .landing-sec-hero { background-color: var(--landing-hero-bg) !important; background-image: none !important; color: var(--landing-hero-text) !important; }
  .landing-sec-trust { background-color: var(--landing-trust-bg) !important; background-image: none !important; color: var(--landing-trust-text) !important; }
  .landing-sec-features { background-color: var(--landing-features-bg) !important; background-image: none !important; color: var(--landing-features-text) !important; }
  .landing-sec-solutions { background-color: var(--landing-solutions-bg) !important; background-image: none !important; color: var(--landing-solutions-text) !important; }
  .landing-sec-downloads { background-color: var(--landing-downloads-bg) !important; background-image: none !important; color: var(--landing-downloads-text) !important; }
  .landing-sec-stats { background-color: var(--landing-stats-bg) !important; background-image: none !important; color: var(--landing-stats-text) !important; }
  .landing-sec-mission, .landing-sec-about { background-color: var(--landing-mission-bg) !important; background-image: none !important; color: var(--landing-mission-text) !important; }
  .landing-sec-testimonials { background-color: var(--landing-testimonials-bg) !important; background-image: none !important; color: var(--landing-testimonials-text) !important; }
  .landing-sec-pricing { background-color: var(--landing-pricing-bg) !important; background-image: none !important; color: var(--landing-pricing-text) !important; }
  .landing-sec-faq { background-color: var(--landing-faq-bg) !important; background-image: none !important; color: var(--landing-faq-text) !important; }
  .landing-sec-cta { background-color: var(--landing-cta-bg) !important; background-image: none !important; color: var(--landing-cta-text) !important; }
  .landing-sec-contact { background-color: var(--landing-contact-bg, #ffffff) !important; background-image: none !important; color: var(--landing-contact-text, #0f172a) !important; }

  .contact-form-card { background-color: var(--landing-contact-card-bg, #ffffff) !important; border-color: #e2e8f0 !important; }
  .contact-form-input {
      background-color: #ffffff !important;
      color: #0f172a !important; /* Visible crisp slate in light theme */
      border-color: #cbd5e1 !important;
  }
  .contact-form-input::placeholder {
      color: #94a3b8 !important;
  }

  html.dark .landing-sec-contact,
  [data-theme="dark"] .landing-sec-contact {
      background-color: var(--landing-contact-dark-bg, #0b0f19) !important;
      color: var(--landing-contact-text, #f8fafc) !important;
  }
  html.dark .contact-form-card,
  [data-theme="dark"] .contact-form-card {
      background-color: var(--landing-contact-card-dark-bg, #131e29) !important;
      border-color: #334155 !important;
  }
  html.dark .contact-form-input,
  [data-theme="dark"] .contact-form-input {
      background-color: #0f172a !important;
      color: #ffffff !important;
      border-color: #334155 !important;
  }
  html.dark .contact-form-input::placeholder,
  [data-theme="dark"] .contact-form-input::placeholder {
      color: #64748b !important;
  }

  .landing-sec-hero h1, .landing-sec-hero h2, .landing-sec-hero h3,
  .landing-sec-trust h1, .landing-sec-trust h2, .landing-sec-trust h3,
  .landing-sec-features h1, .landing-sec-features h2, .landing-sec-features h3,
  .landing-sec-solutions h1, .landing-sec-solutions h2, .landing-sec-solutions h3,
  .landing-sec-downloads h1, .landing-sec-downloads h2, .landing-sec-downloads h3,
  .landing-sec-stats h1, .landing-sec-stats h2, .landing-sec-stats h3,
  .landing-sec-mission h1, .landing-sec-mission h2, .landing-sec-mission h3,
  .landing-sec-about h1, .landing-sec-about h2, .landing-sec-about h3,
  .landing-sec-testimonials h1, .landing-sec-testimonials h2, .landing-sec-testimonials h3,
  .landing-sec-pricing h1, .landing-sec-pricing h2, .landing-sec-pricing h3,
  .landing-sec-faq h1, .landing-sec-faq h2, .landing-sec-faq h3, .landing-sec-faq summary,
  .landing-sec-cta h1, .landing-sec-cta h2, .landing-sec-cta h3,
  .landing-sec-contact h1, .landing-sec-contact h2, .landing-sec-contact h3 {
      color: inherit !important;
  }

  .landing-sec-hero p, .landing-sec-hero .landing-muted-text { color: var(--landing-hero-muted) !important; }
  .landing-sec-trust p, .landing-sec-trust .landing-muted-text { color: var(--landing-trust-muted) !important; }
  .landing-sec-features p, .landing-sec-features .landing-muted-text { color: var(--landing-features-muted) !important; }
  .landing-sec-solutions p, .landing-sec-solutions .landing-muted-text { color: var(--landing-solutions-muted) !important; }
  .landing-sec-downloads p, .landing-sec-downloads .landing-muted-text { color: var(--landing-downloads-muted) !important; }
  .landing-sec-stats p, .landing-sec-stats .landing-muted-text { color: var(--landing-stats-muted) !important; }
  .landing-sec-mission p, .landing-sec-about p, .landing-sec-mission .landing-muted-text, .landing-sec-about .landing-muted-text { color: var(--landing-mission-muted) !important; }
  .landing-sec-testimonials p, .landing-sec-testimonials .landing-muted-text { color: var(--landing-testimonials-muted) !important; }
  .landing-sec-pricing p, .landing-sec-pricing .landing-muted-text { color: var(--landing-pricing-muted) !important; }
  .landing-sec-faq p, .landing-sec-faq .landing-muted-text { color: var(--landing-faq-muted) !important; }
  .landing-sec-cta p, .landing-sec-cta .landing-muted-text { color: var(--landing-cta-muted) !important; }
  .landing-sec-contact p, .landing-sec-contact .landing-muted-text { color: var(--landing-contact-muted) !important; }
</style>
