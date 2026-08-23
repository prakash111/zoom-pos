<div>
    <h2 class="text-lg font-semibold mb-4">Step 4 — Super Admin Account</h2>

    @if ($alreadyBootstrapped)
        <p class="text-sm text-slate-600 mb-4">A Super Admin account already exists.</p>
        <a href="{{ route('install.finish') }}" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-500">
            Next
        </a>
    @else
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">Name</label>
                <input type="text" wire:model="name" class="w-full rounded-lg border-slate-300 text-sm">
                @error('name') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Email</label>
                <input type="email" wire:model="email" class="w-full rounded-lg border-slate-300 text-sm">
                @error('email') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Password</label>
                <input type="password" wire:model="password" class="w-full rounded-lg border-slate-300 text-sm">
                @error('password') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Confirm Password</label>
                <input type="password" wire:model="password_confirmation" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
        </div>

        <div class="flex justify-end mt-6">
            <button wire:click="save" type="button"
                    class="px-4 py-2 rounded-lg text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-500">
                Create Super Admin & Continue
            </button>
        </div>
    @endif
</div>
