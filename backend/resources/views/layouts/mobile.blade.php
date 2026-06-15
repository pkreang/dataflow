@php
    $appDisplayName = config('app.name');
    $mobileUser = session('user') ?? [];
    $mobileUserName = trim(($mobileUser['first_name'] ?? '').' '.($mobileUser['last_name'] ?? '')) ?: ($mobileUser['name'] ?? 'User');
@endphp
<!DOCTYPE html>
<html class="h-full" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    {{-- Mobile UI is light-only (matches mockup) — drop any inherited dark class.
         Density (compact) is still respected if the user has set it desktop-side. --}}
    <script>
        (function() {
            try {
                document.documentElement.classList.remove('dark');
                var d = localStorage.getItem('density');
                if (d === 'compact') {
                    document.documentElement.classList.add('compact');
                }
            } catch (e) {}
        })();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f6ed8">

    <title>{{ $appDisplayName }} - @yield('title', __('common.dashboard'))</title>

    <link rel="icon" href="data:,">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="mob-shell font-sans antialiased">

    <main class="mob-phone flex flex-col">
        <div class="mob-bg-photo" aria-hidden="true"></div>

        <div class="relative z-10 flex flex-col flex-1 pb-24">
            @include('mobile.components._app-bar')

            <div class="flex-1 px-4 pt-2 pb-4 overflow-y-auto">
                @yield('content')
            </div>
        </div>

        {{-- Floating action button (hides on /m/me automatically) --}}
        @include('mobile.components._fab')

        {{-- Bottom nav --}}
        @include('mobile.components._bottom-nav')
    </main>

</body>
</html>
