<div>
    <h2 class="text-lg font-semibold mb-4">Step 3 — Migrate & Seed</h2>

    <p class="text-sm text-slate-600 mb-4">
        This creates every database table the application needs and seeds default plans and platform settings.
    </p>

    @if ($output)
        <pre class="bg-slate-900 text-slate-100 text-xs rounded-lg p-4 mb-4 max-h-64 overflow-auto">{{ $output }}</pre>
    @endif

    @if ($failed)
        <p class="text-sm text-rose-600 mb-4">Migration failed. Check the output above and try again.</p>
    @endif

    <div class="flex justify-between mt-6">
        <button wire:click="runMigrations" type="button"
                class="px-4 py-2 rounded-lg text-sm font-medium bg-slate-100 hover:bg-slate-200">
            {{ $ran ? 'Re-run Migrations' : 'Run Migrations' }}
        </button>

        <a href="{{ route('install.admin') }}"
           @class([
               'inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium',
               'bg-indigo-600 text-white hover:bg-indigo-500' => $ran,
               'bg-slate-200 text-slate-400 pointer-events-none' => ! $ran,
           ])>
            Next
        </a>
    </div>
</div>
