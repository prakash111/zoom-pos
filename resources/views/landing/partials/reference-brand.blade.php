@php($referenceLogo = \App\Models\DynamicSetting::get('platform_logo_url') ?: $publicBranding->getLogoPublicUrl())
<span class="reference-brand">
    @if ($referenceLogo)
        <img src="{{ $referenceLogo }}" alt="{{ $publicBranding->platform_name }}" width="280" height="52" decoding="async">
    @else
        <svg width="64" height="56" viewBox="0 0 58 54" fill="none" aria-hidden="true">
            <path d="M4 13h11" stroke="#16cf83" stroke-width="4" stroke-linecap="round"/><path d="M8 22h10" stroke="#ffb52c" stroke-width="4" stroke-linecap="round"/><path d="M12 31h8" stroke="#f43e8e" stroke-width="4" stroke-linecap="round"/>
            <path d="M20 6h5l5 29h19" stroke="#2688ff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/><path d="M27 10h25a3 3 0 0 1 3 4l-4 15H30L27 10Z" fill="#236aff"/><path d="m33 12 20 1-4 15H35l-2-16Z" fill="#6938ff"/><circle cx="33" cy="44" r="5" fill="#2789ff"/><circle cx="47" cy="44" r="5" fill="#6938ff"/>
        </svg>
        <span><strong>{{ $publicBranding->platform_name }}</strong><small>{{ __('POS & Inventory Management') }}</small></span>
    @endif
</span>
