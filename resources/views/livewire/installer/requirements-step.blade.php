<div>
    <h2 class="text-lg font-semibold mb-4">Step 1 — Requirements & Permissions</h2>

    <ul class="divide-y divide-slate-200 mb-6">
        @foreach ($checks as $check)
            <li class="flex items-center justify-between py-2 text-sm">
                <span>{{ $check['label'] }}</span>
                @if ($check['pass'])
                    <span class="text-emerald-600 font-medium">Pass</span>
                @else
                    <span class="text-rose-600 font-medium">Fail</span>
                @endif
            </li>
        @endforeach
    </ul>

    @if (! $allPassed)
        <p class="text-sm text-rose-600 mb-4">Resolve the failed checks above before continuing.</p>
    @endif

    <div class="flex justify-end">
        <a href="{{ route('install.environment') }}"
           @class([
               'inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium',
               'bg-indigo-600 text-white hover:bg-indigo-500' => $allPassed,
               'bg-slate-200 text-slate-400 pointer-events-none' => ! $allPassed,
           ])>
            Next
        </a>
    </div>
</div>
