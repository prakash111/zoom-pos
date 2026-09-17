@php
    $type = $type ?? 'login';
    $authBannerUrl = \App\Models\DynamicSetting::get('auth_banner_image_url');
    $defaultBanner = ($type === 'register')
        ? asset('assets/images/register-banner-cropped.png')
        : asset('assets/images/login-banner-cropped.png');
    $bannerSrc = !empty($authBannerUrl) ? $authBannerUrl : $defaultBanner;
@endphp

<aside class="auth-showcase auth-showcase--{{ $type }}" aria-label="{{ __('POS & Inventory Management') }}">
    <img src="{{ $bannerSrc }}"
         alt="{{ $type === 'register' ? __('Everything you need to run your business: sales, inventory, customers, invoicing and reports in one secure platform.') : __('One login for your entire business: manage sales, inventory, customers, invoicing and reports across your laptop, phone and point of sale.') }}"
         width="{{ $type === 'register' ? 900 : 1080 }}" height="784"
         fetchpriority="high" decoding="async" draggable="false">
</aside>
