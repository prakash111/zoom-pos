@php
    $referenceNavigation = $publicHeaderMenu ?: [
        ['title' => __('Platform'), 'url' => '#showcase'],
        ['title' => __('Products'), 'url' => '#features'],
        ['title' => __('Solutions'), 'url' => '#solutions'],
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
                        <div class="px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            {{ __('Select Language') }}
                        </div>
                        @foreach ($publicLanguages as $language)
                            <a href="{{ route('locale.switch', $language->code) }}" data-no-spa="true">{{ $language->native_name ?: $language->name }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
            <button type="button" x-on:click="dark = !dark" class="reference-button reference-button--outline !px-2.5 !py-2" title="{{ __('Toggle Theme') }}">
                <span x-show="!dark">🌙</span>
                <span x-show="dark">☀️</span>
            </button>
            @if (auth('platform_web')->check())
                <a href="{{ url('/superadmin') }}" class="reference-button reference-button--primary reference-register"><x-landing.icon name="shield" width="18" height="18" /> {{ __('SuperAdmin') }}</a>
            @elseif (auth('web')->check())
                <a href="{{ url('/tenant') }}" class="reference-button reference-button--primary reference-register"><x-landing.icon name="dashboard" width="18" height="18" /> {{ __('Go to Dashboard') }}</a>
            @else
                <a href="{{ route('tenant.login') }}" class="reference-button reference-button--login">{{ __('Sign in') }}</a>
                <a href="{{ route('tenant.register') }}" class="reference-button reference-button--primary reference-register"><x-landing.icon name="user-plus" width="18" height="18" /> {{ __('Start Free Trial') }}</a>
            @endif
            <button type="button" class="reference-menu-toggle" x-on:click="mobileOpen = !mobileOpen" :aria-expanded="mobileOpen.toString()" aria-controls="reference-mobile-nav" title="{{ __('Menu') }}">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path x-show="!mobileOpen" d="M4 6h16M4 12h16M4 18h16"/><path x-show="mobileOpen" x-cloak d="m6 6 12 12M6 18 18 6"/></svg>
            </button>
        </div>
    </div>
    <nav id="reference-mobile-nav" class="reference-mobile-nav" aria-label="{{ __('Mobile navigation') }}" x-show="mobileOpen" x-cloak x-on:click.outside="mobileOpen = false">
        @foreach ($referenceNavigation as $item)
            <a href="{{ $item['url'] }}" target="{{ $item['target'] ?? '_self' }}" x-on:click="mobileOpen = false">{{ $item['title'] }}</a>
        @endforeach
        @if (auth('platform_web')->check())
            <a href="{{ url('/superadmin') }}" class="reference-button reference-button--primary">{{ __('SuperAdmin') }}</a>
        @elseif (auth('web')->check())
            <a href="{{ url('/tenant') }}" class="reference-button reference-button--primary">{{ __('Go to Dashboard') }}</a>
        @else
            <a href="{{ route('tenant.register') }}" class="reference-button reference-button--primary">{{ __('Start Free Trial') }}</a>
        @endif
    </nav>
</header>
