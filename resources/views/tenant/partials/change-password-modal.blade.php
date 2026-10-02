<!-- Modal Outer Wrapper -->
<div id="changePasswordModal" 
     tabindex="-1"
     aria-hidden="true"
     style="display: none;" 
     class="hidden fixed inset-0 z-[9999] bg-slate-950/80 backdrop-blur-sm items-center justify-center p-4">
    
    <div class="relative w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-6"
         onclick="event.stopPropagation();">
        
        <!-- Header -->
        <div class="flex items-center justify-between pb-4 border-b border-slate-800">
            <div class="flex items-center gap-2.5">
                <div class="p-2 bg-amber-500/10 text-amber-400 rounded-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                </div>
                <div>
                    <h3 id="modal-password-title" class="text-base font-bold text-white">{{ __('Change Account Password') }}</h3>
                    <p class="text-xs text-slate-400">{{ __('Update credentials for :name', ['name' => auth()->user()?->name ?? 'User']) }}</p>
                </div>
            </div>
            <!-- Close Button -->
            <button type="button" 
                    onclick="closeChangePasswordModal(event)" 
                    class="text-slate-400 hover:text-white p-1.5 rounded-lg hover:bg-slate-800 transition cursor-pointer"
                    aria-label="{{ __('Close') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Form Body -->
        <form action="{{ route('tenant.password.update') }}" method="POST" class="space-y-4 mt-4">
            @csrf
            @method('PUT')

            @if(config('app.demo_mode', env('DEMO_MODE', false)))
                <div class="p-3 bg-amber-500/10 border border-amber-500/20 rounded-xl text-xs text-amber-400 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                        </svg>
                        <span class="font-medium">{{ __('Demo Mode Active: Password change is disabled') }}</span>
                    </div>
                    <span class="px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase bg-amber-500/20 text-amber-300 rounded border border-amber-500/30">{{ __('Protected') }}</span>
                </div>
            @endif

            @if (session('status'))
                <div class="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded-xl text-xs text-emerald-400 flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="p-3 bg-rose-500/10 border border-rose-500/20 rounded-xl text-xs text-rose-400 flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <!-- Current Password -->
            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1.5">{{ __('Current Password *') }}</label>
                <input type="password" 
                       name="current_password" 
                       id="change_pwd_current"
                       required 
                       placeholder="{{ __('Enter current password') }}"
                       class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:border-emerald-500">
                @error('current_password', 'updatePassword')
                    <p class="text-xs text-rose-400 mt-1 font-medium">{{ $message }}</p>
                @enderror
                @if(!$errors->updatePassword->has('current_password'))
                    @error('current_password')
                        <p class="text-xs text-rose-400 mt-1 font-medium">{{ $message }}</p>
                    @enderror
                @endif
            </div>

            <!-- New Password -->
            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1.5">{{ __('New Password *') }}</label>
                <input type="password" 
                       name="password" 
                       id="change_pwd_new"
                       required 
                       minlength="6"
                       placeholder="{{ __('Minimum 6 characters') }}"
                       class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:border-emerald-500">
                <!-- Alias for backwards compatibility -->
                <input type="hidden" name="new_password" id="alias_pwd_new">
                @error('password', 'updatePassword')
                    <p class="text-xs text-rose-400 mt-1 font-medium">{{ $message }}</p>
                @enderror
                @if(!$errors->updatePassword->has('password'))
                    @error('password')
                        <p class="text-xs text-rose-400 mt-1 font-medium">{{ $message }}</p>
                    @enderror
                @endif
            </div>

            <!-- Confirm New Password -->
            <div>
                <label class="block text-xs font-medium text-slate-300 mb-1.5">{{ __('Confirm New Password *') }}</label>
                <input type="password" 
                       name="password_confirmation" 
                       id="change_pwd_confirm"
                       required 
                       minlength="6"
                       placeholder="{{ __('Re-enter new password') }}"
                       class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:border-emerald-500">
                <!-- Alias for backwards compatibility -->
                <input type="hidden" name="new_password_confirmation" id="alias_pwd_confirm">
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800 mt-6">
                <button type="button" 
                        onclick="closeChangePasswordModal(event)" 
                        class="px-4 py-2.5 rounded-xl border border-slate-700 hover:bg-slate-800 text-xs font-medium text-slate-300 transition cursor-pointer">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" 
                        @if(config('app.demo_mode', env('DEMO_MODE', false))) disabled title="{{ __('Password change is disabled in demo mode') }}" @endif
                        class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-red-600 to-pink-600 hover:from-red-500 hover:to-pink-500 disabled:opacity-40 disabled:cursor-not-allowed text-white font-semibold text-xs transition shadow-lg shadow-pink-600/20 flex items-center gap-1.5 cursor-pointer">
                    @if(config('app.demo_mode', env('DEMO_MODE', false)))
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg>
                        <span>{{ __('Password change is disabled in demo mode') }}</span>
                    @else
                        <span>{{ __('Update Password') }}</span>
                    @endif
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openChangePasswordModal(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    const modal = document.getElementById('changePasswordModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
    }
}

function closeChangePasswordModal(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    const modal = document.getElementById('changePasswordModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
    }
}

// Close when clicking directly on darkened backdrop only
document.getElementById('changePasswordModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeChangePasswordModal(e);
    }
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('changePasswordModal');
        if (modal && modal.style.display !== 'none' && !modal.classList.contains('hidden')) {
            closeChangePasswordModal(e);
        }
    }
});

// Keep alias input values synchronized if present
document.getElementById('change_pwd_new')?.addEventListener('input', function(e) {
    const alias = document.getElementById('alias_pwd_new');
    if (alias) alias.value = e.target.value;
});
document.getElementById('change_pwd_confirm')?.addEventListener('input', function(e) {
    const alias = document.getElementById('alias_pwd_confirm');
    if (alias) alias.value = e.target.value;
});
</script>

@if($errors->updatePassword->any())
<script>
    document.addEventListener("DOMContentLoaded", function() {
        openChangePasswordModal();
    });
</script>
@endif
