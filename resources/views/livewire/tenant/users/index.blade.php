<div class="space-y-6">
    
    <!-- Top Alerts -->
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="px-5 py-3 rounded-2xl bg-rose-50 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300 text-xs sm:text-sm font-semibold flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            {{ session('error') }}
        </div>
    @endif

    <!-- Team Invitation Share Card (Shown upon successful invite or resend) -->
    @if ($justInvitedCode)
        <div class="bg-gradient-to-br from-blue-500/10 via-indigo-500/10 to-purple-500/10 border-2 border-blue-500/30 dark:border-blue-500/40 rounded-3xl p-6 sm:p-7 shadow-lg space-y-4">
            
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-blue-600 text-white flex items-center justify-center font-bold text-sm shadow-md">
                        🎉
                    </div>
                    <div>
                        <h3 class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white">
                            Invitation Ready for {{ $justInvitedUser?->name ?? 'Team Member' }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Role: <strong class="text-blue-600 dark:text-blue-400 uppercase font-black">{{ $justInvitedUser?->role }}</strong> &bull; Share the invitation link or code via WhatsApp or Email.
                        </p>
                    </div>
                </div>

                <button type="button"
                        wire:click="$set('justInvitedCode', null)"
                        class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 font-black text-lg p-1">
                    &times;
                </button>
            </div>

            <!-- Code & Link Display Box -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800">
                
                <!-- Invitation Code -->
                <div class="space-y-1">
                    <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{{ __("One-Time Invitation Code") }}</div>
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xl font-black text-blue-600 dark:text-blue-400 tracking-wider select-all">{{ $justInvitedCode }}</span>
                    </div>
                </div>

                <!-- Direct Join Link -->
                <div class="space-y-1">
                    <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{{ __("Direct Invitation Link") }}</div>
                    <div class="text-xs font-mono text-slate-600 dark:text-slate-300 truncate select-all">
                        {{ $justInvitedLink }}
                    </div>
                </div>

            </div>

            <!-- 1-Click WhatsApp, Email & Copy Actions -->
            <div class="flex flex-wrap items-center gap-2.5 pt-1">
                
                <!-- Send via WhatsApp -->
                @if ($justInvitedWhatsAppUrl)
                    <a href="{{ $justInvitedWhatsAppUrl }}"
                       target="_blank"
                       class="px-4 py-2.5 rounded-xl text-xs font-extrabold bg-[#25D366] hover:bg-[#1EBE5D] text-white shadow-md shadow-emerald-500/20 active:scale-95 transition-all flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824zm-3.423-14.416c-6.627 0-12 5.373-12 12 0 2.158.57 4.184 1.564 5.941l-1.657 6.059 6.223-1.632c1.705.932 3.654 1.465 5.73 1.465 6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/></svg>
                        <span>{{ __("Share via WhatsApp") }}</span>
                    </a>
                @endif

                <!-- Send / Resend Email -->
                @if ($justInvitedUser)
                    <button type="button"
                            wire:click="sendEmailInvite({{ $justInvitedUser->id }}, '{{ $justInvitedCode }}')"
                            class="px-4 py-2.5 rounded-xl text-xs font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/20 active:scale-95 transition-all flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                        <span>{{ __("Send Email via SMTP") }}</span>
                    </button>
                @endif

            </div>

        </div>
    @endif

    <!-- Access Control Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">{{ __("Access Control") }}</h2>
            <p class="text-xs text-slate-400 mt-0.5">{{ __("Register users and define what each one can see and do.") }}</p>
        </div>

        <div class="flex items-center gap-2">
            <a wire:navigate.hover href="{{ route('tenant.users.permissions') }}"
               class="px-4 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 shadow-sm border border-slate-200 dark:border-slate-700 flex items-center gap-1.5 transition active:scale-95">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>
                <span>{{ __("Permissions Matrix") }}</span>
            </a>

            <button wire:click="newInvite"
                    type="button"
                    class="px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-500/25 active:scale-95 transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
                <span>+ {{ __("Invite User") }}</span>
            </button>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="relative max-w-md">
        <input type="text"
               wire:model.live.debounce.300ms="search"
               placeholder="{{ __("Search team by name, email, or role...") }}"
               class="w-full pl-10 pr-4 py-2.5 bg-white dark:bg-slate-800 rounded-2xl border-none shadow-[0_2px_15px_rgb(0,0,0,0.03)] text-xs sm:text-sm font-medium focus:ring-2 focus:ring-blue-500 text-slate-800 dark:text-slate-100">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
        </div>
    </div>

    <!-- Invite Form Modal / Drawer -->
    @if ($showInviteForm)
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.04)] border border-slate-100 dark:border-slate-800 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                <div>
                    <h3 class="text-base font-black text-slate-900 dark:text-white">{{ __("Invite New Team Member") }}</h3>
                    <p class="text-xs text-slate-400">{{ __("Send an invitation to join your store via WhatsApp or Email") }}</p>
                </div>
                <button wire:click="$set('showInviteForm', false)" type="button" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Full Name *") }}</label>
                    <input type="text" wire:model="inviteName" placeholder="{{ __("e.g. Sarah Jenkins") }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                    @error('inviteName') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Email Address *") }}</label>
                    <input type="email" wire:model="inviteEmail" placeholder="{{ __("sarah@store.com") }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                    @error('inviteEmail') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Phone / WhatsApp (Optional)") }}</label>
                    <input type="text" wire:model="invitePhone" placeholder="{{ __("+1 555-0199") }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                    @error('invitePhone') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Role & Profile *") }}</label>
                    <select wire:model="inviteRole" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 font-semibold">
                        @foreach ($roles as $rKey => $rLabel)
                            <option value="{{ $rKey }}">{{ $rLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Commission Scheme") }}</label>
                    <select wire:model="inviteCommissionType" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 font-semibold">
                        <option value="percentage">{{ __("Percentage (%) of Sale Total") }}</option>
                        <option value="fixed">{{ __("Fixed Amount ($) per Sale") }}</option>
                        <option value="profit_percentage">{{ __("Percentage (%) of Net Profit") }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Commission Rate / Amount") }}</label>
                    <input type="number" step="0.01" min="0" wire:model="inviteCommissionRate" placeholder="0.00" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500">
                </div>
            </div>

            <!-- Email Notification Toggle -->
            <div class="flex items-center gap-2 pt-2">
                <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700 dark:text-slate-300">
                    <input type="checkbox" wire:model="sendViaEmail" class="w-4 h-4 rounded-md border-slate-300 text-blue-600 focus:ring-blue-500">
                    <span>{{ __("Send invitation email automatically via store SMTP") }}</span>
                </label>
            </div>

            <div class="flex justify-end gap-2.5 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button wire:click="$set('showInviteForm', false)" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition">
                    {{ __("Cancel") }}
                </button>
                <button wire:click="invite" type="button" class="px-6 py-2.5 rounded-2xl text-xs font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/20 active:scale-95 transition">
                    {{ __("Create & Generate Invite") }}
                </button>
            </div>
        </div>
    @endif

    <!-- Users Table -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm text-left">
                <thead class="bg-slate-50/80 dark:bg-slate-800/60 text-slate-400 dark:text-slate-500 font-extrabold border-b border-slate-100 dark:border-slate-800 uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="px-6 py-4">{{ __("User") }}</th>
                        <th class="px-6 py-4">{{ __("Role Profile") }}</th>
                        <th class="px-6 py-4">{{ __("Commission") }}</th>
                        <th class="px-6 py-4 text-center">{{ __("Status") }}</th>
                        <th class="px-6 py-4 text-right">{{ __("Actions") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse ($users as $user)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                            
                            <!-- User Avatar & Info -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-black text-sm flex items-center justify-center shadow-sm">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="font-extrabold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                                            <span>{{ $user->name }}</span>
                                            @if ($user->id === auth()->id())
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">{{ __("You") }}</span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] text-slate-400 font-mono mt-0.5">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>

                            <!-- Role Dropdown Selector -->
                            <td class="px-6 py-4">
                                <select wire:change="updateUserRole('{{ $user->id }}', $event.target.value)"
                                        @disabled($user->id === auth()->id())
                                        class="rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold text-slate-800 dark:text-slate-200 focus:ring-blue-500 py-1.5 px-3">
                                    @foreach ($roles as $rKey => $rLabel)
                                        <option value="{{ $rKey }}" @selected(strtolower($user->role) === $rKey)>
                                            {{ $rLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>

                            <!-- Commission Scheme Badge & Quick Edit -->
                            <td class="px-6 py-4">
                                <button type="button"
                                        wire:click="openCommissionModal('{{ $user->id }}')"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 hover:bg-blue-50 dark:hover:bg-blue-950/50 hover:text-blue-600 transition border border-slate-200 dark:border-slate-700">
                                    @if ((float) $user->commission_rate > 0)
                                        <span class="text-emerald-600 font-black">
                                            {{ app(\App\Services\CommissionService::class)->formatRate((float) $user->commission_rate, $user->commission_type) }}
                                        </span>
                                    @else
                                        <span class="text-slate-400 font-semibold">{{ __('0% (None)') }}</span>
                                    @endif
                                    <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                            </td>

                            <!-- Status Badge -->
                            <td class="px-6 py-4 text-center">
                                <span @class([
                                    'px-3 py-1 rounded-full text-[11px] font-extrabold inline-block',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' => $user->status === 'approved',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300' => $user->status === 'convidado',
                                    'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300' => $user->status === 'suspended',
                                ])>{{ ucfirst($user->status === 'approved' ? 'Active' : ($user->status === 'convidado' ? 'Invited' : 'Suspended')) }}</span>
                            </td>

                            <!-- Action Buttons -->
                            <td class="px-6 py-4 text-right space-x-1.5 whitespace-nowrap">
                                
                                <!-- Resend / Share Invite (For Pending Invites) -->
                                @if ($user->status === 'convidado')
                                    <button type="button"
                                            wire:click="resendInvite('{{ $user->id }}')"
                                            class="px-2.5 py-1.5 rounded-xl text-xs font-extrabold bg-blue-50 dark:bg-blue-950/50 hover:bg-blue-600 text-blue-600 hover:text-white transition shadow-2xs inline-flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" /></svg>
                                        <span>{{ __("Share Invite") }}</span>
                                    </button>
                                @endif

                                <!-- Switch to user account (Impersonate) -->
                                @if ($user->id !== auth()->id() && $user->status === 'approved')
                                    <button type="button"
                                            wire:click="switchToUser('{{ $user->id }}')"
                                            wire:confirm="Switch session to view the store as {{ $user->name }} ({{ $user->role }})?"
                                            class="px-3 py-1.5 rounded-xl text-xs font-extrabold bg-slate-100 dark:bg-slate-800 hover:bg-blue-600 text-slate-700 dark:text-slate-200 hover:text-white transition shadow-2xs inline-flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                                        <span>{{ __("Switch Account") }}</span>
                                    </button>
                                @endif

                                <!-- Granular Permissions -->
                                <a wire:navigate.hover href="{{ route('tenant.users.user-permissions', $user) }}"
                                   class="px-3 py-1.5 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition inline-block">
                                    {{ __("Permissions") }}
                                </a>

                                <!-- Toggle Suspend/Activate -->
                                @if ($user->id !== auth()->id())
                                    <button type="button"
                                            wire:click="toggleUserStatus('{{ $user->id }}')"
                                            class="px-2.5 py-1.5 rounded-xl text-xs font-bold text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/40 transition">
                                        {{ $user->status === 'suspended' ? 'Activate' : 'Suspend' }}
                                    </button>

                                    <!-- Delete -->
                                    <button type="button"
                                            wire:click="delete('{{ $user->id }}')"
                                            wire:confirm="Are you sure you want to permanently remove user {{ $user->name }}?"
                                            class="px-2.5 py-1.5 rounded-xl text-xs font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition">
                                        {{ __("Remove") }}
                                    </button>
                                @endif

                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-400 text-xs">
                                {{ __('No team members found. Click "+ Invite User" to add staff.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit User Commission Modal -->
    @if ($showCommissionModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 max-w-md w-full shadow-2xl border border-slate-100 dark:border-slate-800 space-y-5 animate-in fade-in zoom-in duration-150">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-600 text-white flex items-center justify-center font-bold text-sm shadow-md">
                            💰
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white">
                                {{ __('Commission Scheme') }}
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $editingUserName }}
                            </p>
                        </div>
                    </div>
                    <button wire:click="$set('showCommissionModal', false)" type="button" class="text-slate-400 hover:text-slate-600 font-black text-lg p-1">&times;</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Commission Calculation Type") }}</label>
                        <select wire:model="editingCommissionType" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-semibold focus:ring-blue-500">
                            <option value="percentage">{{ __("Percentage (%) of Sale Total") }}</option>
                            <option value="fixed">{{ __("Fixed Amount ($) per Sale") }}</option>
                            <option value="profit_percentage">{{ __("Percentage (%) of Net Profit") }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Rate / Fixed Amount") }}</label>
                        <input type="number" step="0.01" min="0" wire:model="editingCommissionRate" placeholder="0.00" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 font-bold">
                        @error('editingCommissionRate') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-2.5 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button wire:click="$set('showCommissionModal', false)" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition">
                        {{ __("Cancel") }}
                    </button>
                    <button wire:click="updateCommission" type="button" class="px-6 py-2.5 rounded-2xl text-xs font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/20 active:scale-95 transition">
                        {{ __("Save Commission") }}
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
