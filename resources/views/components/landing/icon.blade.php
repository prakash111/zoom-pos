@props(['name' => 'shield'])
<svg {{ $attributes->merge(['width' => '24', 'height' => '24', 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.7', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true']) }}>
    @switch($name)
        @case('cart')
            <path d="M2 3h3l2.5 12h11L21 6H6M8 19h.01M18 19h.01"/><circle cx="8" cy="19" r="1"/><circle cx="18" cy="19" r="1"/>
            @break
        @case('restaurant')
            <path d="M4 3v6a3 3 0 0 0 6 0V3M7 3v18M18 3c-3 3-3 7 0 9h2V3h-2Zm2 9v9"/>
            @break
        @case('box')
            <path d="m12 3 9 5v9l-9 5-9-5V8l9-5Zm0 10 9-5M12 13 3 8m9 5v9M7.5 5.5l9 5"/>
            @break
        @case('chart')
            <path d="M4 3v18h17M8 16v-5m5 5V8m5 8V5"/>
            @break
        @case('users')
            <circle cx="9" cy="7" r="3"/><path d="M2 21v-2a7 7 0 0 1 14 0v2M16 4a3 3 0 0 1 0 6m3 11v-2a7 7 0 0 0-3-5"/>
            @break
        @case('cloud')
            <path d="M6 18a5 5 0 0 1-1-10 7 7 0 0 1 13-1 5.5 5.5 0 0 1 0 11H6Z"/><path d="m9 13 3-3 3 3m-3-3v7"/>
            @break
        @case('settings')
            <path d="m10 3-1 3-3 1-3 3 2 2-1 3 3 3 3-1 2 3 4-1 1-3 3-1 1-4-3-2-1-3-4-1Z"/><circle cx="12" cy="12" r="3"/>
            @break
        @case('headphones')
            <path d="M3 14v-2a9 9 0 0 1 18 0v2M3 13h3v8H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 1-2Zm18 0h-3v8h2a2 2 0 0 0 2-2v-4a2 2 0 0 0-1-2Z"/>
            @break
        @case('star')
            <path d="m12 2 3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1 3-6Z"/>
            @break
        @case('globe')
            <circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a18 18 0 0 1 0 18 18 18 0 0 1 0-18Z"/>
            @break
        @case('bolt')
            <path d="m13 2-10 12h7l-1 8 10-12h-7l1-8Z"/>
            @break
        @case('arrow')
            <path d="M4 12h16m-6-6 6 6-6 6"/>
            @break
        @case('play')
            <circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4V8Z" fill="currentColor" stroke="none"/>
            @break
        @case('user-plus')
            <circle cx="9" cy="7" r="3"/><path d="M2 21v-2a7 7 0 0 1 14 0v2m3-13v6m-3-3h6"/>
            @break
        @case('rocket')
            <path d="M14 4a11 11 0 0 1 7-1 11 11 0 0 1-1 7l-7 7-6-6 7-7Zm-7 7-4 1-1 5 5-1m6 1-1 4 5 1 1-5M3 21l3-3"/><circle cx="16" cy="8" r="2"/>
            @break
        @default
            <path d="M12 3a12 12 0 0 0 9 3c1 8-3 13-9 15C6 19 2 14 3 6a12 12 0 0 0 9-3Z"/><path d="m8 12 3 3 5-6"/>
    @endswitch
</svg>
