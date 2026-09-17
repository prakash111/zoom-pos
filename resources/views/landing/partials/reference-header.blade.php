@php
    $referenceNavigation = $publicHeaderMenu ?: [
        ['title' => __('Home'), 'url' => '#showcase'],
        ['title' => __('Features'), 'url' => '#features'],
        ['title' => __('Pricing'), 'url' => '#pricing'],
        ['title' => __('About'), 'url' => '#about'],
        ['title' => __('Contact'), 'url' => '#contact'],
    ];
@endphp
<header class="reference-header" x-data="{ mobileOpen: false }" x-on:keydown.escape.window="mobileOpen = false">
    <div class="reference-container reference-header-inner">
        <a href="{{ url('/') }}" class="reference-logo" aria-label="{{ $publicBranding->platform_name }} — {{ __('Home') }}">
            @include('landing.partials.reference-brand')
        </a>
        <nav class="reference-nav" aria-label="{{ __('Main navigation') }}">
            @foreach ($referenceNavigation as $item)
                <a href="{{ $item['url'] }}" target="{{ $item['target'] ?? '_self' }}" @if(($item['target'] ?? '_self') === '_blank') rel="noopener noreferrer" @endif
                   @class(['reference-nav-link', 'is-active' => in_array($item['url'], ['#', '#showcase', url('/')], true)])>{{ $item['title'] }}</a>
            @endforeach
        </nav>
        <div class="reference-header-actions">
            @if ($publicLanguages->isNotEmpty())
                <div class="reference-language" x-data="{ open: false }" x-on:keydown.escape="open = false">
                    <button type="button" x-on:click="open = !open" x-on:click.outside="open = false" :aria-expanded="open.toString()" aria-controls="reference-languages" title="{{ __('Switch Language') }}">
                        <x-landing.icon name="globe" width="20" height="20" />
                        <span class="uppercase">{{ $publicActiveLang?->code ?? 'EN' }}</span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div id="reference-languages" class="reference-language-menu" x-show="open" x-cloak>
                        @foreach ($publicLanguages as $language)
                            <a href="{{ route('locale.switch', $language->code) }}" data-no-spa="true">{{ $language->native_name ?: $language->name }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
            <a href="{{ route('tenant.login') }}" class="reference-button reference-button--login">{{ __('Login') }}</a>
            <a href="{{ route('tenant.register') }}" class="reference-button reference-button--primary reference-register"><x-landing.icon name="user-plus" width="18" height="18" /> {{ __('Register Free') }}</a>
            <button type="button" class="reference-menu-toggle" x-on:click="mobileOpen = !mobileOpen" :aria-expanded="mobileOpen.toString()" aria-controls="reference-mobile-nav" title="{{ __('Menu') }}">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path x-show="!mobileOpen" d="M4 6h16M4 12h16M4 18h16"/><path x-show="mobileOpen" x-cloak d="m6 6 12 12M6 18 18 6"/></svg>
            </button>
        </div>
    </div>
    <nav id="reference-mobile-nav" class="reference-mobile-nav" aria-label="{{ __('Mobile navigation') }}" x-show="mobileOpen" x-cloak x-on:click.outside="mobileOpen = false">
        @foreach ($referenceNavigation as $item)
            <a href="{{ $item['url'] }}" target="{{ $item['target'] ?? '_self' }}" x-on:click="mobileOpen = false">{{ $item['title'] }}</a>
        @endforeach
        <a href="{{ route('tenant.register') }}" class="reference-button reference-button--primary">{{ __('Start Your Free Trial') }}</a>
    </nav>
</header>
