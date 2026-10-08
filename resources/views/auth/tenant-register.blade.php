@extends('layouts.guest')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-8">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl overflow-hidden p-6 sm:p-8">
        
        <!-- Header -->
        <div class="flex items-center gap-3 border-b border-slate-100 dark:border-slate-800 pb-5 mb-6">
            <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 border border-blue-200/60 dark:border-blue-800">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <div>
                <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">{{ __('Create Your Store Account') }}</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('Select your business type and start your 14-day free trial.') }}
                </p>
            </div>
        </div>

        @if ($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs font-semibold space-y-1">
                <p class="font-bold flex items-center gap-1.5">
                    <svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>{{ __('Please correct the highlighted errors:') }}</span>
                </p>
                <ul class="list-disc list-inside space-y-0.5 pl-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('tenant.register') }}" method="POST" class="space-y-6">
            @csrf

            <!-- Store Details -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="store_name" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('Store / Business Name') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           id="store_name"
                           name="store_name"
                           required
                           value="{{ old('store_name') }}"
                           placeholder="Acme Stores"
                           class="w-full h-11 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 transition">
                    @error('store_name') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('Owner Email Address') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="email"
                           id="email"
                           name="email"
                           required
                           value="{{ old('email') }}"
                           placeholder="owner@example.com"
                           class="w-full h-11 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 transition">
                    @error('email') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Passwords -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('Password') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="password"
                           id="password"
                           name="password"
                           required
                           placeholder="••••••••"
                           class="w-full h-11 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 transition">
                    @error('password') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('Confirm Password') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="password"
                           id="password_confirmation"
                           name="password_confirmation"
                           required
                           placeholder="••••••••"
                           class="w-full h-11 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 transition">
                </div>
            </div>

            <!-- Business Type / Operating Mode Section -->
            <div class="space-y-3 pt-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                    {{ __('Business Type / Operating Mode') }} <span class="text-rose-500">*</span>
                </label>

                <!-- Dynamic Business Vertical Selection Cards -->
                <div class="grid grid-cols-1 gap-3">
                    @foreach($businessTypes as $type)
                        <label class="relative flex items-start p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/60 hover:border-emerald-500/50 hover:bg-emerald-500/5 cursor-pointer transition">
                            <input type="radio" 
                                   name="operating_mode" 
                                   value="{{ $type['id'] }}" 
                                   {{ (old('operating_mode', 'retail') === $type['id']) ? 'checked' : '' }}
                                   class="mt-1 text-emerald-600 focus:ring-emerald-500/20 bg-white dark:bg-slate-950 border-slate-300 dark:border-slate-700">
                            
                            <div class="ml-3">
                                <span class="block text-sm font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                    <span>{{ $type['name'] }}</span>
                                </span>
                                @if(!empty($type['description']))
                                    <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed">{{ $type['description'] }}</span>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>

                @error('operating_mode')
                    <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Submit Button -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
                <button type="submit"
                        class="w-full h-12 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold text-sm tracking-wide shadow-lg shadow-blue-600/30 active:scale-[0.99] transition flex items-center justify-center gap-2 cursor-pointer">
                    <span>{{ __('Create Store & Launch Trial →') }}</span>
                </button>
            </div>

            <div class="text-center pt-2">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Already have an account?') }}
                    <a href="{{ route('tenant.login') }}" class="font-bold text-blue-600 dark:text-blue-400 hover:underline">
                        {{ __('Sign In') }}
                    </a>
                </p>
            </div>
        </form>
    </div>
</div>
@endsection
