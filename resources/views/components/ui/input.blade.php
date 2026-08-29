@props(['label' => null, 'error' => null, 'hint' => null])
<div>
    @if ($label)
        <label @if($attributes->get('id')) for="{{ $attributes->get('id') }}" @endif class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">{{ $label }}</label>
    @endif
    {{-- Large touch target (py-2.5 ≈ 44px total height) for counter/tablet use. --}}
    <input {{ $attributes->merge(['class' => 'w-full px-3.5 py-2.5 rounded-xl border text-sm bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] transition '.($error ? 'border-rose-400 dark:border-rose-700' : 'border-slate-200 dark:border-slate-700')]) }}>
    @if ($error)
        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $error }}</p>
    @elseif ($hint)
        <p class="mt-1 text-xs text-slate-400">{{ $hint }}</p>
    @endif
</div>
