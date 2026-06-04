<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="min-h-full">
<head>
    <script>
        // Light-only — never apply `dark` class.
        try { document.documentElement.classList.remove('dark'); } catch (e) {}
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('page-title', $pageTitle ?? config('app.name')) - {{ config('app.name') }}</title>

    <link rel="icon" href="data:,">

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- CSS only: avoid loading Alpine/Chart on auth pages. --}}
    @vite(['resources/css/app.css'])

@php
    $loginBgPath = isset($loginBackground) ? trim((string) $loginBackground) : '';
    $bgImage = $loginBgPath !== ''
        ? asset('storage/' . $loginBgPath)
        : asset('images/approval-workflow.jpg');
    /** JSON string for url(...) — must be unescaped in <style> ({{ }} turns " into &quot; and breaks CSS). */
    $bgUrlJson = json_encode($bgImage, JSON_UNESCAPED_SLASHES) ?: '""';
    $rawLoginBg = trim((string) ($loginBackgroundColor ?? '#1e3a8a'));
    $loginBgColor = '#1e3a8a';
    if (preg_match('/^#[0-9A-Fa-f]{3,8}$/', $rawLoginBg)) {
        $loginBgColor = $rawLoginBg;
    } elseif (preg_match('/^rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\)$/i', $rawLoginBg)) {
        $loginBgColor = $rawLoginBg;
    }
@endphp
    {{-- All background on body via normal flow — no fixed layers / z-index stacking (avoids "dead" hit targets in Chrome). --}}
    <style>
        .auth-guest-body {
            min-height: 100dvh;
            min-height: 100vh;
            margin: 0;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            font-family: 'Inter', 'Noto Sans Thai', ui-sans-serif, system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            pointer-events: auto;
            background-color: {{ $loginBgColor }};
            background-image: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), url({!! $bgUrlJson !!});
            background-size: cover, cover;
            background-position: center, center;
            background-repeat: no-repeat, no-repeat;
        }
    </style>
</head>
<body class="auth-guest-body text-base text-slate-800 dark:text-slate-200">

    <main class="auth-guest-main w-full max-w-[634px] mx-auto flex flex-col items-center">
        <div class="w-full flex flex-col lg:flex-row rounded-[16px] shadow-2xl border border-white/10 bg-white dark:bg-gray-900 login-card overflow-visible">
            <div class="login-welcome-pane hidden lg:flex lg:w-[42%] flex-col justify-center items-center text-center p-8 lg:p-10 bg-gradient-to-b from-blue-800 to-blue-600 text-white login-welcome">
                @if ($systemLogo ?? null)
                    <img src="{{ asset('storage/' . $systemLogo) }}" alt="{{ config('app.name') }}" class="max-h-20 w-auto object-contain mb-8 opacity-95">
                @else
                    <h1 class="login-brand">{{ config('app.name') }}</h1>
                @endif
                <h2 class="login-welcome-title">{{ $welcomeTitle ?? __('common.login_welcome', ['app' => config('app.name')]) }}</h2>
                <p class="login-welcome-desc">{{ $welcomeSubtitle ?? __('common.login_welcome_subtitle') }}</p>
                @if ($loginIllustration ?? null)
                    <img src="{{ asset('storage/' . $loginIllustration) }}" alt="Login illustration"
                         class="max-h-40 w-auto object-contain mt-6 opacity-95">
                @endif
            </div>

            <div class="login-form-pane flex-1 flex items-center justify-center p-6 sm:p-8 lg:p-10 bg-white dark:bg-gray-900 min-w-0">
                <div class="w-full max-w-[280px] sm:max-w-xs">
                    @if ($systemLogo ?? null)
                        <img src="{{ asset('storage/' . $systemLogo) }}" alt="{{ config('app.name') }}" class="lg:hidden h-12 w-auto object-contain mb-6 mx-auto">
                    @else
                        <h1 class="lg:hidden text-center font-bold tracking-widest text-blue-600 mb-6 text-2xl">{{ config('app.name') }}</h1>
                    @endif
                    @yield('content')
                </div>
            </div>
        </div>

    </main>
    <p class="fixed bottom-3 right-4 z-10 text-xs text-white/70 font-medium drop-shadow select-none pointer-events-none" aria-hidden="true">v{{ config('app.version') }}</p>

    <style>
    [x-cloak]{display:none!important}
    .login-card { min-width: 0; max-width: 634px; }
    .login-welcome-pane { border-radius: 0; }
    @media (max-width: 1023px) {
        .login-form-pane { border-radius: 16px; }
    }
    @media (min-width: 1024px) {
        .login-card { box-shadow: 0 25px 50px -12px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.05); }
        .login-welcome-pane {
            border-top-left-radius: 16px;
            border-bottom-left-radius: 16px;
        }
        .login-form-pane {
            border-top-right-radius: 16px;
            border-bottom-right-radius: 16px;
        }
    }
    .login-form-pane input,
    .login-form-pane textarea,
    .login-form-pane select {
        -webkit-user-select: text;
        user-select: text;
    }
    .login-form-pane input:-webkit-autofill,
    .login-form-pane input:-webkit-autofill:hover,
    .login-form-pane input:-webkit-autofill:focus {
        -webkit-text-fill-color: rgb(15 23 42);
        transition: background-color 99999s ease-out;
        box-shadow: inset 0 0 0 1000px rgb(255 255 255);
    }
    .dark .login-form-pane input:-webkit-autofill,
    .dark .login-form-pane input:-webkit-autofill:hover,
    .dark .login-form-pane input:-webkit-autofill:focus {
        -webkit-text-fill-color: rgb(241 245 249);
        box-shadow: inset 0 0 0 1000px rgb(15 23 42);
    }
    .login-welcome { background: linear-gradient(to bottom, #1e40af, #2563eb); color: #fff; }
    .login-welcome .login-brand,
    .login-welcome .login-welcome-title,
    .login-welcome .login-welcome-desc { color: #fff; }
    .login-welcome .login-welcome-desc { opacity: 0.9; }
    .login-brand { font-size: 2.75rem; font-weight: 700; letter-spacing: 0.025em; margin-bottom: 1.5rem; }
    .login-welcome-title { font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem; }
    .login-welcome-desc { font-size: 0.9375rem; line-height: 1.6; }
    .login-form-title { font-size: 1.625rem; font-weight: 700; color: inherit; margin-bottom: 1.5rem; text-align: center; }
    .login-form-label { display: block; font-size: 1.0625rem; font-weight: 500; margin-bottom: 0.25rem; color: #374151; }
    .dark .login-form-label { color: #d1d5db; }
    .login-form-input { font-size: 0.9375rem; padding-top: 0.5rem; padding-bottom: 0.5rem; }
    .login-form-link { font-size: 0.9375rem; color: #2563eb; text-decoration: underline; }
    .dark .login-form-link { color: #60a5fa; }
    .login-form-btn { width: 100%; padding: 0.5rem 1rem; font-size: 0.9375rem; font-weight: 600; background: #2563eb; color: #fff; border-radius: 0.5rem; border: none; cursor: pointer; transition: background 0.2s; }
    .login-form-btn:hover { background: #1d4ed8; }
    .login-form-error { font-size: 0.9375rem; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.js-auth-password-toggle').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var input = btn.previousElementSibling;
                    if (!input || input.tagName !== 'INPUT') return;
                    var showIcon = btn.querySelector('.js-auth-pw-icon-show');
                    var hideIcon = btn.querySelector('.js-auth-pw-icon-hide');
                    var toText = input.type === 'password';
                    input.type = toText ? 'text' : 'password';
                    btn.setAttribute('aria-pressed', toText ? 'true' : 'false');
                    if (showIcon) showIcon.classList.toggle('hidden', toText);
                    if (hideIcon) hideIcon.classList.toggle('hidden', !toText);
                });
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
