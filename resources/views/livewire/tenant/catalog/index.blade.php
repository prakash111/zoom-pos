<div class="space-y-6">
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>
                {{ session('status') }}
                @if (session('published_url'))
                    — <a href="{{ session('published_url') }}" target="_blank" class="underline font-mono font-bold">{{ session('published_url') }}</a>
                @endif
            </span>
        </div>
    @endif

    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-5">
        <div>
            <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">Publish an Online Catalog</h2>
            <p class="text-xs text-slate-400">Create a public shareable digital menu or product showcase link</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Catalog Title *</label>
                <input type="text" wire:model="title" placeholder="e.g. Fresh Summer Produce, Special Deals…" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                @error('title') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Link Expiration</label>
                <select wire:model="ttlDays" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="1">1 day</option>
                    <option value="7">7 days</option>
                    <option value="30">30 days</option>
                    <option value="0">Never (Permanent link)</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">Select Products to Include</label>
            @error('selectedProductIds') <p class="text-rose-600 text-xs mb-2">{{ $message }}</p> @enderror
            <div class="max-h-64 overflow-y-auto border border-slate-200 dark:border-slate-800 rounded-2xl divide-y divide-slate-100 dark:divide-slate-800 p-1">
                @forelse ($products as $product)
                    <label class="flex items-center gap-3 px-4 py-2.5 text-xs sm:text-sm hover:bg-slate-50 dark:hover:bg-slate-800/50 rounded-xl cursor-pointer transition">
                        <input type="checkbox" wire:model="selectedProductIds" value="{{ $product->id }}" class="rounded-lg text-blue-600 focus:ring-blue-500">
                        <x-pos-product-icon :name="$product->name" size="xs" />
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ $product->name }}</span>
                        <span class="text-slate-400 font-bold ml-auto">${{ number_format($product->sale_price, 2) }}</span>
                    </label>
                @empty
                    <div class="px-4 py-8 text-center text-xs text-slate-400">No active products available.</div>
                @endforelse
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <button wire:click="publish"
                    type="button"
                    class="px-6 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/20 active:scale-95 transition">
                Publish Public Catalog
            </button>
        </div>
    </div>

    <!-- Active Published Catalogs -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-[0_4px_20px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 font-extrabold text-sm text-slate-900 dark:text-white">
            Published Shareable Links
        </div>
        <table class="w-full text-xs sm:text-sm">
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                @forelse ($catalogs as $catalog)
                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                        <td class="px-6 py-4 font-bold text-slate-800 dark:text-slate-200">{{ $catalog->title }}</td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400 text-xs">{{ count($catalog->product_ids) }} items</td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400 text-xs font-mono">Expires: {{ $catalog->expires_at?->format('Y-m-d') ?? 'Never' }}</td>
                        <td class="px-6 py-4">
                            <a href="{{ route('catalog.show', $catalog->id) }}" target="_blank" class="text-xs font-bold text-blue-600 hover:underline inline-flex items-center gap-1">
                                <span>Open Link</span> &nearr;
                            </a>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button wire:click="revoke('{{ $catalog->id }}')" wire:confirm="Revoke this link?" type="button" class="text-xs font-bold text-rose-600 hover:underline px-2.5 py-1 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40 transition">Revoke</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-400 text-xs">
                            No catalogs published yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
