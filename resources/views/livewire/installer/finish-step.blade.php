<div>
    <h2 class="text-lg font-semibold mb-4">Step 5 — Finish</h2>

    @if (! $done)
        <p class="text-sm text-slate-600 mb-6">
            This generates the application encryption key and locks the installer so it can't be run again.
        </p>
        <div class="flex justify-end">
            <button wire:click="finish" type="button"
                    class="px-4 py-2 rounded-lg text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-500">
                Finish Installation
            </button>
        </div>
    @else
        <p class="text-sm text-emerald-600 mb-6">Installation complete. The installer is now locked.</p>
        <a href="{{ route('superadmin.login') }}"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-500">
            Go to Super Admin Login
        </a>
    @endif
</div>
