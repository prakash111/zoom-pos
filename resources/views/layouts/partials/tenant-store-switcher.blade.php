@if ($canStores)
    <div x-data="{ storeMenuOpen: false }" x-on:keydown.escape.stop="storeMenuOpen = false; $refs.storeTrigger.focus()" x-on:click.outside="storeMenuOpen = false" data-testid="tenant-store-switcher">
        <button type="button" x-ref="storeTrigger" x-on:click="storeMenuOpen = !storeMenuOpen" :aria-expanded="storeMenuOpen" aria-controls="tenant-store-menu"
                class="flex max-w-[120px] sm:max-w-xs items-center gap-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400 hover:underline py-1"
                title="{{ __('Switch store') }}">
            <span class="truncate">{{ $activeStore?->name ?? $tenantCompany->name }}</span>
            <svg aria-hidden="true" class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
        </button>
        <div id="tenant-store-menu" x-show="storeMenuOpen" x-cloak x-transition
             class="absolute left-3 sm:left-6 top-full mt-2 w-80 max-w-[calc(100vw-3rem)] rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900 text-slate-900 dark:text-slate-100 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700">
                <h2 class="font-bold">{{ __('Switch store') }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $tenantStoreCount }} {{ __('stores') }} · {{ $tenantStoreLimit === -1 ? __('Unlimited plan') : __('Plan limit: :limit', ['limit' => $tenantStoreLimit]) }}</p>
            </div>
            <div class="max-h-64 overflow-y-auto p-2">
                @forelse ($switchableStores as $branch)
                    <form method="POST" action="{{ route('tenant.stores.switch', $branch->id) }}">
                        @csrf
                        <input type="hidden" name="redirect_to" value="dashboard">
                        <button type="submit" @disabled($branch->id === $activeStore?->id)
                                class="w-full flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-left hover:bg-slate-100 dark:hover:bg-slate-800 disabled:bg-emerald-50 dark:disabled:bg-emerald-950">
                            <span class="min-w-0">
                                <span class="block font-semibold truncate">{{ $branch->name }}</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $branch->code }}{{ $branch->is_primary ? ' · '.__('Main branch') : '' }}</span>
                                @if ($branch->address)<span class="block text-xs text-slate-500 dark:text-slate-400 truncate">{{ $branch->address }}</span>@endif
                            </span>
                            @if ($branch->id === $activeStore?->id)
                                <span class="text-xs font-bold text-emerald-700 dark:text-emerald-400">✓ {{ __('Selected') }}</span>
                            @endif
                        </button>
                    </form>
                @empty
                    <p class="p-3 text-sm">{{ __('No active stores are assigned to your account.') }}</p>
                @endforelse
            </div>
            <div class="border-t border-slate-200 dark:border-slate-700 p-3 space-y-2">
                @if ($canAddStore)
                    <a href="{{ route('tenant.settings.stores') }}#create-store" class="block rounded-xl bg-emerald-600 px-4 py-2.5 text-center text-sm font-bold text-white hover:bg-emerald-700">+ {{ __('Add New Store / Branch') }}</a>
                @elseif ($canCreateStore)
                    <a href="{{ route('tenant.billing.index') }}" class="block text-sm text-emerald-700 dark:text-emerald-400">{{ __('Store limit reached. Upgrade to add a branch.') }}</a>
                @endif
                <a href="{{ route('tenant.settings.stores') }}" class="block rounded-xl px-4 py-2 text-center text-sm font-semibold hover:bg-slate-100 dark:hover:bg-slate-800">{{ __('Manage Stores & Branches') }}</a>
            </div>
        </div>
    </div>
@endif
