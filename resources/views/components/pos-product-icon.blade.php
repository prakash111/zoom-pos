@props([
    'name' => '',
    'category' => '',
    'size' => 'md', // 'xs', 'sm', 'md', 'lg'
])

@php
    $n = mb_strtolower(trim($name));
    $c = mb_strtolower(trim($category));
    
    // Size classes
    $sizeClasses = match($size) {
        'xs' => 'w-7 h-7',
        'sm' => 'w-10 h-10',
        'lg' => 'w-24 h-24',
        default => 'w-16 h-16',
    };
    
    // Identify icon type
    $type = 'default';
    $bgGlow = 'rgba(59, 130, 246, 0.18)';

    if (str_contains($n, 'terong') || str_contains($n, 'eggplant') || str_contains($n, 'aubergine') || str_contains($n, 'brinjal')) {
        $type = 'eggplant';
        $bgGlow = 'rgba(168, 85, 247, 0.3)';
    } elseif (str_contains($n, 'melon') || str_contains($n, 'honeydew') || str_contains($n, 'cantaloupe')) {
        $type = 'melon';
        $bgGlow = 'rgba(163, 230, 53, 0.35)';
    } elseif (str_contains($n, 'apel') || str_contains($n, 'apple') || str_contains($n, 'manzana')) {
        $type = 'apple';
        $bgGlow = 'rgba(244, 63, 94, 0.35)';
    } elseif (str_contains($n, 'semangka') || str_contains($n, 'watermelon') || str_contains($n, 'sandia')) {
        $type = 'watermelon';
        $bgGlow = 'rgba(244, 63, 94, 0.3)';
    } elseif (str_contains($n, 'jeruk') || str_contains($n, 'orange') || str_contains($n, 'naranja') || str_contains($n, 'citrus') || str_contains($n, 'mandarin')) {
        $type = 'orange';
        $bgGlow = 'rgba(245, 158, 11, 0.35)';
    } elseif (str_contains($n, 'strawberry') || str_contains($n, 'stroberi') || str_contains($n, 'berry') || str_contains($n, 'fresa')) {
        $type = 'strawberry';
        $bgGlow = 'rgba(236, 72, 153, 0.35)';
    } elseif (str_contains($n, 'pisang') || str_contains($n, 'banana') || str_contains($n, 'platano')) {
        $type = 'banana';
        $bgGlow = 'rgba(234, 179, 8, 0.35)';
    } elseif (str_contains($n, 'lemon') || str_contains($n, 'limon') || str_contains($n, 'lime')) {
        $type = 'lemon';
        $bgGlow = 'rgba(250, 204, 21, 0.4)';
    } elseif (str_contains($n, 'tomat') || str_contains($n, 'tomato')) {
        $type = 'tomato';
        $bgGlow = 'rgba(239, 68, 68, 0.35)';
    } elseif (str_contains($n, 'wortel') || str_contains($n, 'carrot')) {
        $type = 'carrot';
        $bgGlow = 'rgba(249, 115, 22, 0.35)';
    } elseif (str_contains($n, 'roti') || str_contains($n, 'bread') || str_contains($c, 'carbohydrate') || str_contains($c, 'karbohidrat') || str_contains($c, 'bakery')) {
        $type = 'bread';
        $bgGlow = 'rgba(217, 119, 6, 0.25)';
    } elseif (str_contains($n, 'susu') || str_contains($n, 'milk') || str_contains($n, 'kopi') || str_contains($n, 'coffee') || str_contains($c, 'beverage') || str_contains($c, 'minuman')) {
        $type = 'drink';
        $bgGlow = 'rgba(6, 182, 212, 0.3)';
    } elseif (str_contains($c, 'vegetable') || str_contains($c, 'sayur')) {
        $type = 'vegetables';
        $bgGlow = 'rgba(16, 185, 129, 0.3)';
    } elseif (str_contains($c, 'fruit') || str_contains($c, 'buah')) {
        $type = 'apple';
        $bgGlow = 'rgba(244, 63, 94, 0.3)';
    }
@endphp

<div class="relative flex items-center justify-center {{ $sizeClasses }} select-none pointer-events-none">
    <!-- Ambient Radial Glow -->
    <div class="absolute inset-0 rounded-full blur-md opacity-80 transform -translate-y-0.5" style="background: radial-gradient(circle, {{ $bgGlow }} 0%, rgba(255,255,255,0) 75%);"></div>

    @if ($type === 'eggplant')
        <!-- Terong / Eggplant -->
        <svg viewBox="0 0 100 100" class="w-full h-full drop-shadow-sm z-10" fill="none" xmlns="http://www.w3.org/2000/svg">
            <ellipse cx="56" cy="56" rx="26" ry="32" transform="rotate(-30 56 56)" fill="url(#eggplant_grad_{{ $size }})" />
            <path d="M30 32 C 34 26, 40 22, 48 20 C 44 26, 42 32, 45 38 C 40 35, 35 36, 30 32 Z" fill="#58a13a" />
            <path d="M46 22 C 48 14, 43 10, 38 8 C 39 12, 42 16, 46 22 Z" fill="#43842c" />
            <path d="M48 24 C 54 22, 60 26, 62 33 C 57 32, 53 30, 48 24 Z" fill="#58a13a" />
            <path d="M46 44 C 56 47, 63 56, 63 66" stroke="white" stroke-width="4" stroke-linecap="round" opacity="0.35" />
            <defs>
                <linearGradient id="eggplant_grad_{{ $size }}" x1="20" y1="20" x2="85" y2="85" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#a855f7" />
                    <stop offset="0.6" stop-color="#7e22ce" />
                    <stop offset="1" stop-color="#581c87" />
                </linearGradient>
            </defs>
        </svg>
    @elseif ($type === 'melon')
        <!-- Melon / Honeydew -->
        <svg viewBox="0 0 100 100" class="w-full h-full drop-shadow-sm z-10" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="50" cy="52" r="34" fill="url(#melon_body_{{ $size }})" />
            <path d="M50 20 C 66 32, 70 65, 54 84 C 50 64, 46 36, 50 20 Z" fill="#fcd34d" />
            <path d="M52 28 C 62 38, 64 60, 53 76 C 50 60, 48 40, 52 28 Z" fill="#fef08a" />
            <path d="M48 18 C 46 12, 48 8, 56 6" stroke="#65a30d" stroke-width="4" stroke-linecap="round" fill="none" />
            <ellipse cx="36" cy="42" rx="6" ry="12" transform="rotate(-20 36 42)" fill="white" opacity="0.3" />
            <defs>
                <linearGradient id="melon_body_{{ $size }}" x1="25" y1="20" x2="75" y2="85" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#bef264" />
                    <stop offset="0.7" stop-color="#84cc16" />
                    <stop offset="1" stop-color="#65a30d" />
                </linearGradient>
            </defs>
        </svg>
    @elseif ($type === 'apple')
        <!-- Apple -->
        <svg viewBox="0 0 100 100" class="w-full h-full drop-shadow-sm z-10" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M50 34 C 44 26, 30 26, 24 36 C 16 48, 20 74, 38 84 C 45 88, 48 86, 50 86 C 52 86, 55 88, 62 84 C 80 74, 84 48, 76 36 C 70 26, 56 26, 50 34 Z" fill="url(#apple_grad_{{ $size }})" />
            <path d="M50 34 C 50 24, 54 18, 58 14" stroke="#78350f" stroke-width="4" stroke-linecap="round" fill="none" />
            <path d="M55 24 C 64 18, 74 20, 78 28 C 70 32, 60 28, 55 24 Z" fill="#4ade80" />
            <ellipse cx="36" cy="46" rx="6" ry="14" transform="rotate(-25 36 46)" fill="white" opacity="0.35" />
            <defs>
                <linearGradient id="apple_grad_{{ $size }}" x1="30" y1="25" x2="75" y2="85" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#fb7185" />
                    <stop offset="0.5" stop-color="#ef4444" />
                    <stop offset="1" stop-color="#b91c1c" />
                </linearGradient>
            </defs>
        </svg>
    @elseif ($type === 'watermelon')
        <!-- Semangka / Watermelon Slice -->
        <svg viewBox="0 0 100 100" class="w-full h-full drop-shadow-sm z-10" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M22 68 C 36 82, 64 82, 78 68 L 74 62 C 62 74, 38 74, 26 62 Z" fill="#22c55e" />
            <path d="M26 62 C 38 74, 62 74, 74 62 L 72 58 C 61 69, 39 69, 28 58 Z" fill="#bbf7d0" />
            <path d="M28 58 C 39 69, 61 69, 72 58 L 50 22 Z" fill="url(#watermelon_flesh_{{ $size }})" />
            <ellipse cx="44" cy="46" rx="2" ry="3.5" transform="rotate(15 44 46)" fill="#1e293b" />
            <ellipse cx="56" cy="46" rx="2" ry="3.5" transform="rotate(-15 56 46)" fill="#1e293b" />
            <ellipse cx="50" cy="54" rx="2" ry="3.5" fill="#1e293b" />
            <ellipse cx="38" cy="56" rx="1.8" ry="3" transform="rotate(25 38 56)" fill="#1e293b" />
            <ellipse cx="62" cy="56" rx="1.8" ry="3" transform="rotate(-25 62 56)" fill="#1e293b" />
            <defs>
                <linearGradient id="watermelon_flesh_{{ $size }}" x1="50" y1="22" x2="50" y2="65" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#fb7185" />
                    <stop offset="0.6" stop-color="#f43f5e" />
                    <stop offset="1" stop-color="#e11d48" />
                </linearGradient>
            </defs>
        </svg>
    @elseif ($type === 'orange')
        <!-- Jeruk / Orange -->
        <svg viewBox="0 0 100 100" class="w-full h-full drop-shadow-sm z-10" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="50" cy="54" r="32" fill="url(#orange_grad_{{ $size }})" />
            <path d="M50 24 C 50 18, 52 14, 54 12" stroke="#78350f" stroke-width="3.5" stroke-linecap="round" fill="none" />
            <path d="M52 22 C 60 16, 70 18, 72 26 C 64 28, 56 26, 52 22 Z" fill="#22c55e" />
            <ellipse cx="40" cy="44" rx="6" ry="10" transform="rotate(-30 40 44)" fill="white" opacity="0.35" />
            <defs>
                <linearGradient id="orange_grad_{{ $size }}" x1="28" y1="28" x2="75" y2="85" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#fb923c" />
                    <stop offset="0.6" stop-color="#f97316" />
                    <stop offset="1" stop-color="#ea580c" />
                </linearGradient>
            </defs>
        </svg>
    @elseif ($type === 'strawberry')
        <!-- Strawberry -->
        <svg viewBox="0 0 100 100" class="w-full h-full drop-shadow-sm z-10" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M50 86 C 36 78, 22 58, 26 40 C 28 32, 40 28, 50 30 C 60 28, 72 32, 74 40 C 78 58, 64 78, 50 86 Z" fill="url(#straw_grad_{{ $size }})" />
            <path d="M32 30 C 38 34, 42 36, 50 34 C 58 36, 62 34, 68 30 C 64 24, 58 24, 50 26 C 42 24, 36 24, 32 30 Z" fill="#22c55e" />
            <path d="M50 26 C 50 18, 53 14, 56 12" stroke="#15803d" stroke-width="3" stroke-linecap="round" fill="none" />
            <circle cx="40" cy="42" r="1.8" fill="#fef08a" />
            <circle cx="52" cy="42" r="1.8" fill="#fef08a" />
            <circle cx="62" cy="44" r="1.8" fill="#fef08a" />
            <circle cx="34" cy="52" r="1.8" fill="#fef08a" />
            <circle cx="46" cy="52" r="1.8" fill="#fef08a" />
            <circle cx="58" cy="54" r="1.8" fill="#fef08a" />
            <circle cx="40" cy="64" r="1.8" fill="#fef08a" />
            <circle cx="52" cy="64" r="1.8" fill="#fef08a" />
            <circle cx="48" cy="74" r="1.8" fill="#fef08a" />
            <defs>
                <linearGradient id="straw_grad_{{ $size }}" x1="30" y1="28" x2="70" y2="86" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#fb7185" />
                    <stop offset="0.5" stop-color="#f43f5e" />
                    <stop offset="1" stop-color="#be123c" />
                </linearGradient>
            </defs>
        </svg>
    @elseif ($type === 'banana')
        <!-- Pisang / Banana -->
        <svg viewBox="0 0 100 100" class="w-full h-full drop-shadow-sm z-10" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M28 36 C 36 32, 54 36, 68 52 C 78 64, 76 74, 68 76 C 60 78, 48 68, 38 56 C 30 46, 26 40, 28 36 Z" fill="url(#banana_grad_{{ $size }})" />
            <path d="M28 36 C 24 38, 22 42, 22 46 C 24 42, 27 38, 28 36 Z" fill="#854d0e" />
            <path d="M68 76 C 72 76, 76 74, 78 70 C 75 73, 71 75, 68 76 Z" fill="#a16207" />
            <path d="M34 40 C 46 44, 58 56, 66 68" stroke="#fef08a" stroke-width="2.5" stroke-linecap="round" fill="none" />
            <defs>
                <linearGradient id="banana_grad_{{ $size }}" x1="25" y1="30" x2="75" y2="75" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#fef08a" />
                    <stop offset="0.4" stop-color="#facc15" />
                    <stop offset="1" stop-color="#eab308" />
                </linearGradient>
            </defs>
        </svg>
    @elseif ($type === 'lemon')
        <!-- Lemon -->
        <svg viewBox="0 0 100 100" class="w-full h-full drop-shadow-sm z-10" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M30 34 C 42 22, 64 26, 74 40 C 82 50, 80 68, 68 76 C 54 84, 34 78, 26 64 C 20 54, 20 42, 30 34 Z" fill="url(#lemon_grad_{{ $size }})" />
            <path d="M66 32 C 74 24, 84 26, 86 32 C 78 36, 72 36, 66 32 Z" fill="#4ade80" />
            <ellipse cx="46" cy="46" rx="6" ry="12" transform="rotate(-35 46 46)" fill="white" opacity="0.4" />
            <defs>
                <linearGradient id="lemon_grad_{{ $size }}" x1="25" y1="25" x2="75" y2="80" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#fef08a" />
                    <stop offset="0.6" stop-color="#facc15" />
                    <stop offset="1" stop-color="#ca8a04" />
                </linearGradient>
            </defs>
        </svg>
    @elseif ($type === 'bread')
        <!-- Carbohydrate / Bread -->
        <svg viewBox="0 0 100 100" class="w-full h-full drop-shadow-sm z-10" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M24 50 C 24 34, 36 28, 50 28 C 64 28, 76 34, 76 50 C 76 68, 68 74, 50 74 C 32 74, 24 68, 24 50 Z" fill="url(#bread_grad_{{ $size }})" />
            <path d="M36 38 C 40 44, 40 50, 38 54" stroke="#78350f" stroke-width="2.5" stroke-linecap="round" />
            <path d="M50 36 C 52 44, 52 50, 50 56" stroke="#78350f" stroke-width="2.5" stroke-linecap="round" />
            <path d="M64 38 C 62 44, 62 50, 64 54" stroke="#78350f" stroke-width="2.5" stroke-linecap="round" />
            <defs>
                <linearGradient id="bread_grad_{{ $size }}" x1="30" y1="28" x2="70" y2="74" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#fde68a" />
                    <stop offset="0.5" stop-color="#f59e0b" />
                    <stop offset="1" stop-color="#b45309" />
                </linearGradient>
            </defs>
        </svg>
    @elseif ($type === 'vegetables')
        <!-- Vegetables -->
        <svg viewBox="0 0 100 100" class="w-full h-full drop-shadow-sm z-10" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="42" cy="54" r="18" fill="#22c55e" />
            <circle cx="58" cy="54" r="18" fill="#16a34a" />
            <circle cx="50" cy="42" r="16" fill="#4ade80" />
            <path d="M50 28 C 46 22, 54 18, 52 14" stroke="#15803d" stroke-width="3" stroke-linecap="round" />
        </svg>
    @elseif ($type === 'drink')
        <!-- Beverage / Drink -->
        <svg viewBox="0 0 100 100" class="w-full h-full drop-shadow-sm z-10" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="34" y="32" width="32" height="46" rx="8" fill="url(#drink_grad_{{ $size }})" />
            <path d="M42 32 L 42 22 L 58 22 L 58 32" stroke="#94a3b8" stroke-width="3" fill="none" />
            <line x1="38" y1="46" x2="62" y2="46" stroke="white" stroke-width="2" opacity="0.5" />
            <defs>
                <linearGradient id="drink_grad_{{ $size }}" x1="34" y1="32" x2="66" y2="78" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#38bdf8" />
                    <stop offset="1" stop-color="#0284c7" />
                </linearGradient>
            </defs>
        </svg>
    @else
        <!-- Modern Generic Product Icon / Package -->
        <svg viewBox="0 0 100 100" class="w-full h-full drop-shadow-sm z-10" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="28" y="30" width="44" height="46" rx="10" fill="url(#box_grad_{{ $size }})" />
            <path d="M28 44 L 50 56 L 72 44" stroke="#3b82f6" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            <line x1="50" y1="56" x2="50" y2="76" stroke="#3b82f6" stroke-width="3" stroke-linecap="round" />
            <defs>
                <linearGradient id="box_grad_{{ $size }}" x1="28" y1="30" x2="72" y2="76" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#dbeafe" />
                    <stop offset="1" stop-color="#93c5fd" />
                </linearGradient>
            </defs>
        </svg>
    @endif
</div>
