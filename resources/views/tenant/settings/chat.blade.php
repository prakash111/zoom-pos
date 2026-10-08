@if(view()->exists('chat::settings'))
    @include('chat::settings')
@elseif(view()->exists('module-chat::settings'))
    @include('module-chat::settings')
@else
    @extends('layouts.tenant')
    @section('content')
    <div class="max-w-4xl mx-auto py-6 sm:py-8 px-4 space-y-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white">{{ __('Live Chat & Store Announcements') }}</h1>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Configure team chat settings, AI smart replies, and promotional broadcasts.') }}</p>
            </div>
            <a href="{{ route('tenant.settings.index') }}" 
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-extrabold text-slate-700 dark:text-slate-200 transition active:scale-95 shadow-2xs border border-slate-200/80 dark:border-slate-700 shrink-0">
                <span>&larr;</span>
                <span>{{ __('Back to Settings') }}</span>
            </a>
        </div>
        <div class="p-6 sm:p-7 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl shadow-sm space-y-6">
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Chat module configuration is currently unavailable or disabled.') }}</p>
        </div>
    </div>
    @endsection
@endif
