<div class="space-y-6">
    <!-- PIX Gateway & Instant Payment Configuration -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
        <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="w-10 h-10 rounded-2xl bg-teal-50 dark:bg-teal-950/60 text-teal-600 dark:text-teal-400 flex items-center justify-center font-bold text-lg">
                ⚡
            </div>
            <div>
                <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('PIX Instant Payment & QR Code Engine') }}</h3>
                <p class="text-xs text-slate-400">{{ __('Configure PIX Key, merchant recipient details, and EMVCo static QR Code for POS checkout') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('PIX Key Type') }}</label>
                <select wire:model="pixKeyType" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold py-2 px-3">
                    <option value="cpf_cnpj">{{ __('CPF / CNPJ') }}</option>
                    <option value="email">{{ __('Email') }}</option>
                    <option value="phone">{{ __('Phone (+55...)') }}</option>
                    <option value="random">{{ __('Random Key (EVP)') }}</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('PIX Key') }}</label>
                <input type="text" wire:model="pixKey" placeholder="{{ __('e.g. 12.345.678/0001-90 or finance@store.com') }}"
                       class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-mono font-bold py-2 px-3">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Account Holder / Store Name') }}</label>
                <input type="text" wire:model="pixMerchantName" placeholder="{{ $company->name ?? '' }}"
                       class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold py-2 px-3">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Account Holder City') }}</label>
                <input type="text" wire:model="pixMerchantCity" placeholder="{{ __('e.g. SAO PAULO or NEW YORK') }}"
                       class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold py-2 px-3">
            </div>
        </div>
    </div>

    <!-- Card Machine Merchant Fees Matrix -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
        <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="w-10 h-10 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-lg">
                💳
            </div>
            <div>
                <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('Card Processing Fees') }}</h3>
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-100">{{ __('Card Machine Merchant Fees') }}</h4>
                <p class="text-xs text-slate-400">{{ __('Configure automatic fee deductions and net receivable calculations for card transactions.') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Standard Debit Fee (%)') }}</label>
                <input type="number" step="0.01" min="0" wire:model="cardFeeDebit" placeholder="1.50"
                       class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-bold text-slate-900 dark:text-white">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Credit 1x / Single Installment Fee (%)') }}</label>
                <input type="number" step="0.01" min="0" wire:model="cardFeeCredit1x" placeholder="3.20"
                       class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-bold text-slate-900 dark:text-white">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Credit Multi-Installments (2x–12x) Base Fee (%)') }}</label>
                <input type="number" step="0.01" min="0" wire:model="cardFeeCreditInstallments" placeholder="4.50"
                       class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-bold text-slate-900 dark:text-white">
            </div>
        </div>
    </div>

    <!-- Scale Barcode Protocol -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
        <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="w-10 h-10 rounded-2xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold text-lg">
                ⚖️
            </div>
            <div>
                <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('Scale Barcode Integration') }}</h3>
                <p class="text-xs text-slate-400">{{ __('Toledo, Filizola, Elgin and standard EAN-13 price/weight embedded barcode integration') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Scale Prefix Digit') }}</label>
                <input type="text" wire:model="barcodeScalePrefix" maxlength="2" placeholder="2"
                       class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-mono font-bold py-2 px-3">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Embedded Data Format') }}</label>
                <select wire:model="barcodeScaleType" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold py-2 px-3">
                    <option value="weight">{{ __('Weight (Weight in Grams - e.g. 2 + CCCCC + WWWWW + D)') }}</option>
                    <option value="price">{{ __('Total Price (Price in Cents - e.g. 2 + CCCCC + VVVVV + D)') }}</option>
                </select>
            </div>
        </div>
    </div>
</div>
