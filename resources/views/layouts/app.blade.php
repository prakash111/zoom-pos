<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Zoom POS & Market') }}</title>

    <!-- Fonts & Styles -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600;1,700;1,800&family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="h-full bg-slate-100 dark:bg-slate-950 text-slate-800 dark:text-slate-100 antialiased flex flex-col font-sans">

    <!-- 1. Top Global Navigation Header -->
    <header class="no-print sticky top-0 z-40 w-full bg-slate-900 border-b border-slate-800 shadow-md">
        @include('layouts.partials.top-navbar')
    </header>

    <!-- 2. Subheader Navigation & Module Breadcrumbs (Single Dockable Bar) -->
    <nav class="no-print w-full bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 sm:px-6 py-2.5 flex items-center justify-between shadow-sm">
        @include('layouts.partials.sub-header')
    </nav>

    <!-- 3. Dynamic Main Content Area -->
    <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    @livewireScripts
    @stack('scripts')
</body>
</html>
