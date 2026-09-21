@php
    $currentDarkBg = $themeSettings['landing_dark_bg'] ?? setting('landing_dark_bg', '#0b0f19');
    $currentPalette = get_landing_sections_palette();
    $currentTokens = get_landing_theme_tokens();
@endphp

<div class="superadmin-themes-wrapper space-y-6">
    @include('superadmin.settings.theme_customizer', [
        'themeSettings' => $themeSettings ?? ['landing_dark_bg' => $currentDarkBg],
        'palette' => $currentPalette,
        'tokens' => $currentTokens,
    ])
</div>
