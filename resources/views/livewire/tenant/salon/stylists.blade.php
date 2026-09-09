<div class="space-y-5">
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold shadow-sm">{{ session('status') }}</div>
    @endif

    <div>
        <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ __('Stylists & Staff Assignments') }}</h2>
        <p class="text-xs text-slate-400">{{ __('Mark which staff members can be booked as specialists') }}</p>
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-x-auto">
        <table class="w-full text-xs sm:text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-slate-500 dark:text-slate-400 font-bold border-b border-slate-100 dark:border-slate-800">
                <tr>
                    <th class="px-5 py-3.5">{{ __('Staff member') }}</th>
                    <th class="px-5 py-3.5">{{ __('Role') }}</th>
                    <th class="px-5 py-3.5 text-right">{{ __('Bookable') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                @forelse ($staff as $u)
                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                        <td class="px-5 py-3.5 font-bold text-slate-800 dark:text-slate-200">{{ $u->name }}</td>
                        <td class="px-5 py-3.5 text-slate-500">{{ ucfirst(str_replace('_', ' ', (string) $u->role)) }}</td>
                        <td class="px-5 py-3.5 text-right">
                            <button wire:click="toggle('{{ $u->id }}')" type="button"
                                    class="px-3.5 py-1.5 rounded-xl text-[11px] font-bold transition {{ $u->is_specialist ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200' }}">
                                {{ $u->is_specialist ? __('Specialist') : __('Not bookable') }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-12 text-center text-slate-400 text-xs">{{ __('No approved staff members yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
