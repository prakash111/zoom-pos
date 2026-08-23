<div class="relative w-full h-full min-h-[400px] flex items-center justify-center p-4 select-none">
    <svg class="w-full max-w-lg h-auto drop-shadow-sm" viewBox="0 0 600 480" fill="none" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <!-- Background Blob Gradient -->
            <linearGradient id="blobGrad1" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#93c5fd" stop-opacity="0.85"/>
                <stop offset="100%" stop-color="#60a5fa" stop-opacity="0.6"/>
            </linearGradient>
            <linearGradient id="blobGrad2" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#bfdbfe" stop-opacity="0.7"/>
                <stop offset="100%" stop-color="#93c5fd" stop-opacity="0.4"/>
            </linearGradient>
            <!-- Cashier Shirt Gradient -->
            <linearGradient id="shirtGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#3b82f6"/>
                <stop offset="100%" stop-color="#2563eb"/>
            </linearGradient>
            <!-- Screen Gradient -->
            <linearGradient id="screenGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#ffffff"/>
                <stop offset="100%" stop-color="#f1f5f9"/>
            </linearGradient>
            <!-- POS Body Gradient -->
            <linearGradient id="posBodyGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" stop-color="#64748b"/>
                <stop offset="100%" stop-color="#475569"/>
            </linearGradient>
            <!-- Counter Desk Gradient -->
            <linearGradient id="counterGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" stop-color="#f8b387"/>
                <stop offset="10%" stop-color="#f49b6b"/>
                <stop offset="100%" stop-color="#e08253"/>
            </linearGradient>
        </defs>

        <!-- Background Fluid Blobs matching screenshot -->
        <path d="M 280 260 C 240 180, 420 120, 520 180 C 600 240, 560 380, 460 400 C 380 420, 310 320, 280 260 Z" fill="url(#blobGrad1)"/>
        <path d="M 180 340 C 140 280, 260 220, 360 260 C 440 300, 400 420, 300 440 C 220 450, 200 380, 180 340 Z" fill="url(#blobGrad2)"/>

        <!-- Cashier Character (Behind Counter) -->
        <g id="cashier">
            <!-- Torso / Shirt -->
            <path d="M 400 240 C 370 240, 350 280, 340 380 L 530 380 C 520 280, 500 240, 470 240 Z" fill="url(#shirtGrad)"/>
            
            <!-- Left Arm & Hand pointing to screen -->
            <path d="M 360 270 C 340 285, 330 300, 335 320 C 340 330, 370 325, 390 310 Z" fill="#2563eb"/>
            <!-- Hand & Pointing Finger -->
            <path d="M 338 315 C 330 315, 315 318, 300 318 C 295 318, 292 312, 298 310 C 310 305, 330 302, 340 305 Z" fill="#fed7aa"/>
            <circle cx="340" cy="315" r="8" fill="#fed7aa"/>

            <!-- Right Arm -->
            <path d="M 460 270 C 490 285, 520 300, 530 340 C 520 350, 490 330, 470 300 Z" fill="#1d4ed8"/>

            <!-- White Shirt Collar -->
            <polygon points="410,240 435,270 460,240 445,240 435,255 425,240" fill="#ffffff"/>

            <!-- Neck -->
            <rect x="420" y="215" width="30" height="35" rx="5" fill="#fed7aa"/>

            <!-- Head / Face -->
            <ellipse cx="435" cy="180" rx="36" ry="44" fill="#fed7aa"/>

            <!-- Stylized Hair matching screenshot with crisp contrast -->
            <path d="M 396 175 C 390 140, 410 110, 435 110 C 455 110, 465 125, 475 130 C 490 135, 485 170, 478 185 C 475 165, 470 145, 455 140 C 430 135, 410 150, 405 180 Z" fill="#0f172a" stroke="#1e293b" stroke-width="1"/>
            <!-- Hair Sideburns / Back -->
            <path d="M 470 160 C 480 180, 475 210, 465 220 C 468 200, 470 180, 465 170 Z" fill="#0f172a"/>
        </g>

        <!-- POS Terminal & Hardware (Dual Screen POS Register) -->
        <g id="pos-terminal">
            <!-- Scanner / Card Reader Base on Right -->
            <rect x="300" y="340" width="45" height="25" rx="4" fill="#334155"/>
            <rect x="305" y="345" width="35" height="4" rx="2" fill="#64748b"/>

            <!-- Main POS Base Stand -->
            <rect x="230" y="340" width="70" height="25" rx="5" fill="#94a3b8"/>
            <rect x="235" y="343" width="60" height="19" rx="3" fill="#cbd5e1"/>
            
            <!-- Neck Stand -->
            <path d="M 255 340 L 250 280 L 280 280 L 275 340 Z" fill="#475569"/>

            <!-- Secondary Customer Screen (Back Display) -->
            <polygon points="275,255 315,265 315,315 275,305" fill="#334155"/>
            <polygon points="278,258 312,267 312,312 278,302" fill="#475569"/>

            <!-- Main Cashier Touchscreen (Angled) -->
            <polygon points="245,230 305,235 295,315 230,305" fill="#1e293b"/>
            <rect x="235" y="235" width="65" height="75" rx="8" transform="matrix(0.98 0.08 -0.1 0.99 35 -15)" fill="#334155"/>
            
            <!-- Bright White Glow Screen matching pos in screenshot -->
            <polygon points="248,238 298,242 288,308 238,300" fill="url(#screenGrad)"/>
            
            <!-- Subtle UI lines on Screen -->
            <line x1="250" y1="250" x2="280" y2="252" stroke="#cbd5e1" stroke-width="3" stroke-linecap="round"/>
            <line x1="250" y1="260" x2="270" y2="262" stroke="#cbd5e1" stroke-width="2" stroke-linecap="round"/>
            <rect x="248" y="275" width="22" height="14" rx="3" fill="#3b82f6" opacity="0.8"/>
            <rect x="274" y="277" width="12" height="12" rx="3" fill="#10b981" opacity="0.8"/>
        </g>

        <!-- Wooden Checkout Counter / Desk matching screenshot -->
        <g id="counter">
            <!-- Counter Top Lip -->
            <rect x="180" y="360" width="420" height="16" rx="6" fill="#f49b6b"/>
            <rect x="180" y="364" width="420" height="8" fill="#e08253"/>
            <!-- Counter Front Face -->
            <rect x="185" y="376" width="415" height="100" fill="url(#counterGrad)"/>
            <!-- Counter Accent Line -->
            <line x1="185" y1="410" x2="600" y2="410" stroke="#d97746" stroke-width="2" opacity="0.4"/>
        </g>
    </svg>
</div>
