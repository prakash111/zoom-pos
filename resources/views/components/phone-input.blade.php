@props([
    'name' => 'phone',
    'id' => null,
    'value' => null,
    'defaultDialCode' => null,
    'required' => false,
    'placeholder' => null,
    'label' => null,
    'class' => '',
    'wrapperClass' => '',
])

@php
    $id = $id ?? 'phone_input_' . $name . '_' . uniqid();
    $rawVal = (string) ($value ?? old($name, ''));
    $systemDefaultDial = \App\Services\Localization\PlatformRegionalService::defaultDialCode();
    $activeDefaultDial = $defaultDialCode ?: $systemDefaultDial;
    if (! str_starts_with($activeDefaultDial, '+')) {
        $activeDefaultDial = '+' . $activeDefaultDial;
    }

    $initialDial = $activeDefaultDial;
    $initialLocal = '';

    if ($rawVal !== '') {
        $cleanRaw = trim($rawVal);
        if (str_starts_with($cleanRaw, '+')) {
            // Attempt to match longest known dial code
            $allDialCodes = array_values(array_unique(array_values(\App\Services\Localization\PlatformRegionalService::COUNTRY_DIAL_CODES)));
            usort($allDialCodes, fn($a, $b) => strlen($b) <=> strlen($a));
            foreach ($allDialCodes as $dc) {
                if (str_starts_with($cleanRaw, $dc)) {
                    $initialDial = $dc;
                    $initialLocal = substr($cleanRaw, strlen($dc));
                    break;
                }
            }
            if ($initialLocal === '') {
                $initialLocal = substr($cleanRaw, 1);
            }
        } else {
            $initialLocal = $cleanRaw;
        }
    }

    $initialLocal = ltrim(preg_replace('/\D+/', '', $initialLocal), '0');

    // Options for dial code selector
    $dialOptions = [
        '+91' => '🇮🇳 India (+91)',
        '+1' => '🇺🇸 US / 🇨🇦 CA (+1)',
        '+44' => '🇬🇧 UK (+44)',
        '+971' => '🇦🇪 UAE (+971)',
        '+966' => '🇸🇦 Saudi Arabia (+966)',
        '+61' => '🇦🇺 Australia (+61)',
        '+65' => '🇸🇬 Singapore (+65)',
        '+60' => '🇲🇾 Malaysia (+60)',
        '+62' => '🇮🇩 Indonesia (+62)',
        '+49' => '🇩🇪 Germany (+49)',
        '+33' => '🇫🇷 France (+33)',
        '+39' => '🇮🇹 Italy (+39)',
        '+34' => '🇪🇸 Spain (+34)',
        '+55' => '🇧🇷 Brazil (+55)',
        '+27' => '🇿🇦 South Africa (+27)',
        '+234' => '🇳🇬 Nigeria (+234)',
        '+254' => '🇰🇪 Kenya (+254)',
        '+880' => '🇧🇩 Bangladesh (+880)',
        '+92' => '🇵🇰 Pakistan (+92)',
        '+81' => '🇯🇵 Japan (+81)',
        '+86' => '🇨🇳 China (+86)',
    ];

    if (! isset($dialOptions[$activeDefaultDial])) {
        $dialOptions = [$activeDefaultDial => $activeDefaultDial] + $dialOptions;
    }

    $commonDialCodesJson = json_encode(array_keys($dialOptions));
@endphp

<div class="w-full {{ $wrapperClass }}"
     x-data="{
        dialCode: @js($initialDial),
        localNumber: @js($initialLocal),
        get fullNumber() {
            let clean = (this.localNumber || '').toString().replace(/\D+/g, '').replace(/^0+/, '');
            if (!clean) return '';
            let dial = (this.dialCode || '').toString().trim();
            if (!dial.startsWith('+')) dial = '+' + dial;
            return dial + clean;
        },
        onLocalInput(e) {
            let val = (e.target.value || '').toString().trim();
            if (val.startsWith('+')) {
                const codes = {{ $commonDialCodesJson }};
                codes.sort((a, b) => b.length - a.length);
                for (let c of codes) {
                    if (val.startsWith(c)) {
                        this.dialCode = c;
                        this.localNumber = val.substring(c.length).replace(/\D+/g, '').replace(/^0+/, '');
                        return;
                    }
                }
            }
            if (val.startsWith('0')) {
                this.localNumber = val.replace(/^0+/, '');
            }
        }
     }">
    @if ($label)
        <label for="{{ $id }}" class="block text-xs sm:text-sm font-semibold text-slate-800 dark:text-slate-200 mb-1.5">
            {{ $label }}
            @if ($required)
                <span class="text-rose-500">*</span>
            @endif
        </label>
    @endif

    {{-- Synced hidden input with normalized E.164 phone string --}}
    <input type="hidden" name="{{ $name }}" :value="fullNumber">
    <input type="hidden" name="{{ $name }}_country" :value="dialCode">

    <div class="flex rounded-xl border border-slate-200 dark:border-slate-700/80 bg-white dark:bg-slate-900/60 overflow-hidden focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20 transition-colors {{ $class }}">
        {{-- Dial Code Selector --}}
        <div class="relative flex items-center bg-slate-50 dark:bg-slate-800/80 border-r border-slate-200 dark:border-slate-700/80 shrink-0">
            <select x-model="dialCode"
                    aria-label="{{ __('Country Dial Code') }}"
                    class="appearance-none bg-transparent pl-3 pr-6 py-2.5 sm:py-3 text-xs sm:text-sm font-semibold text-slate-700 dark:text-slate-300 focus:outline-none cursor-pointer">
                @foreach ($dialOptions as $dial => $dialLabel)
                    <option value="{{ $dial }}" {{ $dial === $activeDefaultDial ? 'selected' : '' }}>
                        {{ $dial }}
                    </option>
                @endforeach
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-1.5 text-slate-400">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </div>

        {{-- Local Phone Number Input --}}
        <input id="{{ $id }}"
               type="tel"
               x-model="localNumber"
               x-on:input="onLocalInput"
               {{ $required ? 'required' : '' }}
               placeholder="{{ $placeholder ?: __('Enter phone number') }}"
               autocomplete="tel-national"
               class="flex-1 px-3.5 py-2.5 sm:py-3 bg-transparent text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 outline-none border-0 focus:ring-0">
    </div>
</div>
