<div class="space-y-6">
    
    <div>
        <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white">Global Tax Reference Matrix</h3>
        <p class="text-xs text-slate-400">Jurisdiction standards, default VAT rates, and GST/HSN classification guidelines</p>
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 font-extrabold text-left border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-6 py-3.5">Country ISO</th>
                        <th class="px-6 py-3.5">Jurisdiction</th>
                        <th class="px-6 py-3.5">Default Tax Scheme</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @foreach ($countries as $c)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                            <td class="px-6 py-4 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                {{ $c['code'] }}
                            </td>
                            <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">
                                {{ $c['name'] }}
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-400">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                    {{ $c['system'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
