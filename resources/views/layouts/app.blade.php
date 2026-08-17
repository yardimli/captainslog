@if($mainFragment ?? false)
<main id="page-content">
    @yield('content')
</main>
@else
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', "Captain's Log") }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        <script>if(localStorage.getItem('captainslog.theme')==='dark'||(!localStorage.getItem('captainslog.theme')&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark')</script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-900 dark:text-slate-100" data-time-format="{{ auth()->user()->time_format ?? '24' }}" data-session-keepalive-url="{{ route('session.keep-alive') }}">
        <div id="authenticated-app-shell" class="min-h-screen bg-slate-100 dark:bg-slate-950">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @hasSection('header')
                <header class="relative z-30 overflow-visible border-b border-slate-200 bg-white/90 shadow-sm dark:border-slate-800 dark:bg-slate-900/90">
                    <div id="page-heading-container" class="max-w-7xl mx-auto overflow-visible py-6 px-4 sm:px-6 lg:px-8">
                        @yield('header')
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main id="page-content">
                @if(session('status'))<div id="application-status-region" class="mx-auto mt-4 max-w-7xl px-4"><div id="application-status-message" class="rounded-xl bg-emerald-100 px-4 py-3 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</div></div>@endif
                @yield('content')
            </main>
        </div>
        @include('partials.javascript-templates')
    </body>
</html>
@endif
