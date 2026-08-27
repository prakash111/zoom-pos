<!-- Top Auth Nav Header -->
<div class="w-full flex items-center justify-between p-6">
    @if(setting('landing_page_enabled', true))
        <a href="{{ url('/') }}" class="btn btn-sm bg-slate-800/80 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl text-xs font-medium flex items-center gap-2 px-3 py-1.5 transition">
            <span>←</span> {{ __('Back to Home') }}
        </a>
    @else
        <div class="w-10"><!-- Spacer when landing page is disabled --></div>
    @endif

    <div class="flex items-center gap-3">
        <!-- Language Switcher & Theme Mode Toggle -->
    </div>
</div>
