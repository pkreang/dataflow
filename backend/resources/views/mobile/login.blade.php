@php
    $appName = config('app.name');
@endphp
<!DOCTYPE html>
<html class="h-full" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <script>
        // Mobile login is light-only (matches mockup) — clear any inherited dark class.
        try { document.documentElement.classList.remove('dark'); } catch (e) {}
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0c2460">

    <title>{{ $appName }} - {{ __('common.login') }}</title>

    <link rel="icon" href="data:,">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Mobile login overrides — darker mountain blend to match mockup feel */
        .mob-login-screen {
            position: relative;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            color: white;
        }
        .mob-login-bg {
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                linear-gradient(180deg, rgba(255,255,255,.02), rgba(255,255,255,.08) 45%, rgba(20,45,50,.4) 100%),
                url('/images/mobile-bg.png') center/cover no-repeat;
        }
        .mob-login-hero {
            position: relative;
            z-index: 1;
            text-align: center;
            padding: 56px 24px 28px;
        }
        .mob-login-hero h1 {
            color: white;
            font-size: 28px;
            font-weight: 700;
            margin: 14px 0 4px;
            text-shadow: 0 2px 8px rgba(10,35,55,.4);
        }
        .mob-login-hero p {
            color: rgba(255,255,255,.85);
            font-size: 13px;
            margin: 0;
        }
        .mob-login-logo {
            width: 72px; height: 72px;
            border-radius: 22px;
            background: rgba(255,255,255,.92);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--mob-navy);
            font-weight: 800;
            font-size: 30px;
            letter-spacing: -0.5px;
            box-shadow: 0 12px 32px rgba(10,35,70,.30);
        }
        .mob-login-card {
            position: relative;
            z-index: 1;
            margin: 8px 20px 24px;
            padding: 22px 20px 24px;
            border-radius: 28px;
            background: rgba(255,255,255,.92);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255,255,255,.6);
            box-shadow: 0 18px 48px rgba(10,35,70,.18);
            color: var(--mob-navy);
        }
        .mob-login-card h2 {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 4px;
            color: var(--mob-navy);
        }
        .mob-login-card .subtitle {
            font-size: 13px;
            color: var(--mob-muted);
            margin: 0 0 18px;
        }
        .mob-login-field { margin-bottom: 14px; }
        .mob-login-field label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--mob-navy);
            margin-bottom: 6px;
        }
        .mob-login-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .mob-login-input-wrap > svg {
            position: absolute;
            left: 14px;
            width: 18px; height: 18px;
            color: var(--mob-muted);
            pointer-events: none;
        }
        .mob-login-input-wrap input {
            width: 100%;
            padding: 12px 14px 12px 42px;
            border-radius: 14px;
            border: 1px solid #d6dde9;
            background: #f6f8fb;
            font-size: 14px;
            color: var(--mob-navy);
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .mob-login-input-wrap input:focus {
            border-color: var(--mob-blue);
            box-shadow: 0 0 0 3px rgba(15,110,216,.15);
            background: white;
        }
        .mob-login-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            margin-bottom: 16px;
            color: var(--mob-muted);
        }
        .mob-login-row a {
            color: var(--mob-blue);
            font-weight: 600;
            text-decoration: none;
        }
        .mob-login-btn {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px;
            border: none;
            border-radius: 16px;
            background: var(--mob-blue);
            color: white;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s, transform .05s;
        }
        .mob-login-btn:hover { background: var(--mob-navy); }
        .mob-login-btn:active { transform: scale(.98); }
        .mob-login-btn.secondary {
            background: transparent;
            color: var(--mob-blue);
            border: 1px solid #d6dde9;
        }
        .mob-login-btn.secondary:hover { background: rgba(15,110,216,.06); }
        .mob-login-divider {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 16px 0;
            font-size: 11px;
            color: var(--mob-muted);
        }
        .mob-login-divider::before,
        .mob-login-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #d6dde9;
        }
        .mob-login-error {
            margin: 0 0 14px;
            padding: 10px 12px;
            border-radius: 12px;
            background: rgba(220,38,38,.10);
            color: #b91c1c;
            font-size: 13px;
        }
        .mob-login-footer {
            position: relative;
            z-index: 1;
            text-align: center;
            color: rgba(255,255,255,.75);
            font-size: 11px;
            padding: 0 20px 24px;
            text-shadow: 0 1px 4px rgba(10,35,55,.3);
        }
    </style>
</head>
<body class="mob-shell font-sans antialiased">

<main class="mob-phone">
    <div class="mob-login-screen">
        <div class="mob-login-bg" aria-hidden="true"></div>

        <section class="mob-login-hero">
            <div class="mob-login-logo">{{ mb_substr($appName, 0, 1) }}</div>
            <h1>{{ $appName }}</h1>
            <p>{{ __('common.app_tagline') ?? 'ระบบฟอร์มและอนุมัติภายในองค์กร' }}</p>
        </section>

        <section class="mob-login-card">
            <h2>{{ __('common.login') }}</h2>
            <p class="subtitle">{{ __('auth.please_login') ?? 'กรุณาเข้าสู่ระบบเพื่อใช้งาน' }}</p>

            @if ($errors->any())
                <div class="mob-login-error">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            @if (isset($authConfigured) && ! $authConfigured)
                <div class="mob-login-error">{{ __('auth.misconfigured') }}</div>
            @endif

            @if (! empty($authLocalEnabled))
                <form method="POST" action="{{ route('login') }}" novalidate data-no-submit-loading>
                    @csrf
                    <div class="mob-login-field">
                        <label for="email">{{ __('auth.placeholder_email') }}</label>
                        <div class="mob-login-input-wrap">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <input type="email" name="email" id="email" value="{{ old('email') }}"
                                   placeholder="{{ __('auth.placeholder_email') }}" required autofocus
                                   autocomplete="username" inputmode="email" enterkeyhint="next">
                        </div>
                    </div>

                    <div class="mob-login-field">
                        <label for="password">{{ __('auth.placeholder_password') }}</label>
                        <div class="mob-login-input-wrap">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            <input type="password" name="password" id="password" required
                                   placeholder="{{ __('auth.placeholder_password') }}"
                                   autocomplete="current-password" enterkeyhint="go">
                        </div>
                    </div>

                    <div class="mob-login-row">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="remember" value="1">
                            <span>{{ __('common.remember_me') ?? 'จดจำฉัน' }}</span>
                        </label>
                        <a href="{{ route('password.request') }}">{{ __('common.forgot_password') }}</a>
                    </div>

                    <button type="submit" class="mob-login-btn">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        {{ __('common.login') }}
                    </button>
                </form>
            @endif

            @if (! empty($authEntraEnabled) || ! empty($authLdapEnabled))
                @if (! empty($authLocalEnabled))
                    <div class="mob-login-divider"><span>{{ __('auth.or_use') }}</span></div>
                @endif
                @if (! empty($authEntraEnabled))
                    <a href="{{ route('auth.entra.redirect') }}" class="mob-login-btn secondary" style="margin-bottom: 10px;">
                        <svg width="18" height="18" viewBox="0 0 21 21"><path fill="currentColor" d="M0 0h10v10H0V0zm11 0h10v10H11V0zM0 11h10v10H0V11zm11 0h10v10H11V11z"/></svg>
                        {{ __('auth.sign_in_with_microsoft') }}
                    </a>
                @endif
            @endif
        </section>

        <div class="flex-1"></div>

        <div class="mob-login-footer">v{{ config('app.version') }}</div>
    </div>
</main>

</body>
</html>
