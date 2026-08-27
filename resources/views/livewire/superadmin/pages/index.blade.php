<div class="space-y-6">

    @if (session('status'))
        <div class="px-5 py-3.5 rounded-2xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 text-xs sm:text-sm font-semibold flex items-center gap-2.5 shadow-xs border border-emerald-200 dark:border-emerald-800">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2.5">
                <span>📄 Custom Pages</span>
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">Public content pages (About, Terms, Privacy, etc.) &mdash; one of these can also be set as the landing page in Branding settings.</p>
        </div>

        <div class="flex items-center gap-2">
            <a wire:navigate.hover href="{{ route('superadmin.menus.index') }}" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs transition text-center flex items-center gap-1.5">
                <span>🧭</span> {{ __('Manage Navigation Menus') }}
            </a>
            <a wire:navigate.hover href="{{ route('superadmin.pages.create') }}" class="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black text-xs shadow-md shadow-blue-500/20 transition text-center">
                + New Page
            </a>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 dark:border-slate-800">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by title or slug..."
                   class="w-full sm:w-80 rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-[10px] uppercase font-extrabold text-slate-400">
                    <tr>
                        <th class="px-4 py-2.5">Title</th>
                        <th class="px-4 py-2.5">Slug</th>
                        <th class="px-4 py-2.5">Status</th>
                        <th class="px-4 py-2.5">Updated</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($pages as $page)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                            <td class="px-4 py-2.5 font-bold text-slate-900 dark:text-white">
                                {{ $page->title }}
                                @if ($page->id === $landingPageId)
                                    <span class="ml-1.5 px-2 py-0.5 rounded-full text-[9px] font-black bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">LANDING PAGE</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 font-mono text-slate-500">/pages/{{ $page->slug }}</td>
                            <td class="px-4 py-2.5">
                                @if ($page->is_active)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">Active</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">Inactive</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-slate-400">{{ $page->updated_at->diffForHumans() }}</td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                @if ($page->is_active)
                                    <a href="{{ route('pages.show', $page->slug) }}" target="_blank" class="text-slate-400 hover:text-slate-700 dark:hover:text-white font-bold mr-3" title="View live">View</a>
                                @endif
                                <a wire:navigate.hover href="{{ route('superadmin.pages.edit', $page) }}" class="text-blue-600 hover:underline font-bold mr-3">Edit</a>
                                <button type="button"
                                        wire:click="delete({{ $page->id }})"
                                        wire:confirm="Delete page &quot;{{ $page->title }}&quot;? This cannot be undone."
                                        class="text-rose-600 hover:underline font-bold cursor-pointer">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400 italic">No custom pages yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3 border-t border-slate-100 dark:border-slate-800">{{ $pages->links() }}</div>
    </div>

</div>
