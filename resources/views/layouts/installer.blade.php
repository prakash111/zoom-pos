<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Installer</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen">
    @include('layouts.partials.preloader')
    <div class="max-w-3xl mx-auto py-12 px-4">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-semibold">{{ config('app.name') }} Installer</h1>
        </div>

        <ol class="flex justify-between mb-10 text-sm">
            @foreach (['Requirements', 'Environment', 'Migrate', 'Admin Account', 'Finish'] as $i => $label)
                @php $n = $i + 1; @endphp
                <li class="flex-1 text-center relative">
                    <div @class([
                        'mx-auto w-8 h-8 rounded-full flex items-center justify-center font-medium',
                        'bg-indigo-600 text-white' => $n <= ($step ?? 1),
                        'bg-slate-300 text-slate-600' => $n > ($step ?? 1),
                    ])>{{ $n }}</div>
                    <div class="mt-1 text-xs text-slate-500">{{ $label }}</div>
                </li>
            @endforeach
        </ol>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-8">
            {{ $slot }}
        </div>
    </div>

    @livewireScripts
</body>
</html>
