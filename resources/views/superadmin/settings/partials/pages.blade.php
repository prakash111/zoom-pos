<div class="space-y-6">
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800 space-y-6">
        
        <!-- CMS Header & Create Button -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-5">
            <div>
                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>📄</span> {{ __('Custom Public Pages & CMS') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('Publish rich marketing pages, terms of service, privacy policies, or a custom landing homepage.') }}
                </p>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-2.5 sm:gap-3 w-full sm:w-auto">
                <div class="relative w-full sm:w-auto">
                    <input type="text"
                           wire:model.live.debounce.300ms="pageSearch"
                           placeholder="{{ __('Search pages...') }}"
                           class="w-full sm:w-64 px-4 py-2 pl-9 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-white">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>

                <a wire:navigate.hover href="{{ route('superadmin.pages.create') }}"
                   class="w-full sm:w-auto px-4 py-2.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition flex items-center justify-center gap-1.5 shadow-md shadow-indigo-600/30 cursor-pointer shrink-0">
                    <span>➕</span>
                    <span>{{ __('Create Page') }}</span>
                </a>
            </div>
        </div>

        <!-- Pages Table -->
        <div class="overflow-x-auto rounded-2xl border border-slate-100 dark:border-slate-800">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="px-4 py-3">{{ __('Title & URL Slug') }}</th>
                        <th class="px-4 py-3">{{ __('Status / Role') }}</th>
                        <th class="px-4 py-3">{{ __('Last Updated') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($pages as $page)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                            <td class="px-4 py-3.5">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $page->title }}</div>
                                <a href="{{ route('pages.show', $page->slug) }}" target="_blank" class="text-[11px] text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1 mt-0.5 font-mono">
                                    <span>/pages/{{ $page->slug }}</span>
                                    <span class="text-[10px]">↗</span>
                                </a>
                            </td>
                            <td class="px-4 py-3.5">
                                @if ($page->id === $landingPageId)
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800">
                                        ⭐ {{ __('Active Homepage') }}
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                        {{ __('Public Page') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-slate-500">
                                {{ $page->updated_at->diffForHumans() }}
                            </td>
                            <td class="px-4 py-3.5 text-right space-x-2">
                                <a wire:navigate.hover href="{{ route('superadmin.pages.edit', $page) }}"
                                   class="px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold hover:bg-indigo-100 text-xs transition">
                                    ✏️ {{ __('Edit') }}
                                </a>
                                <button type="button"
                                        wire:click="deletePage({{ $page->id }})"
                                        wire:confirm="{{ __('Are you sure you want to delete this custom page?') }}"
                                        class="px-3 py-1.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 hover:bg-rose-100 text-xs font-bold transition cursor-pointer">
                                    🗑️ {{ __('Delete') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-slate-400">
                                <div class="text-2xl mb-2">📄</div>
                                <div class="font-bold">{{ __('No custom pages found.') }}</div>
                                <p class="text-[11px] mt-1">{{ __('Click "Create Page" above to add your first CMS page.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($pages->hasPages())
            <div class="pt-3">
                {{ $pages->links() }}
            </div>
        @endif
    </div>
</div>
