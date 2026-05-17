@php
    $appDisplayName = config('app.name');
    $brandAbbr = collect(preg_split('/\s+/', trim((string) $appDisplayName)))->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('');
    if ($brandAbbr === '') {
        $brandAbbr = 'DE';
    }
    $layoutUser = session('user') ?? [];
    $layoutUserName = trim(($layoutUser['first_name'] ?? '') . ' ' . ($layoutUser['last_name'] ?? '')) ?: ($layoutUser['name'] ?? 'User');
    $layoutUserAvatar = $layoutUser['avatar'] ?? ('https://ui-avatars.com/api/?name=' . urlencode($layoutUserName ?: 'U') . '&background=0ea5e9&color=fff');
    $layoutUserInitials = strtoupper(mb_substr($layoutUser['first_name'] ?? '', 0, 1) . mb_substr($layoutUser['last_name'] ?? '', 0, 1)) ?: strtoupper(mb_substr($layoutUserName, 0, 2)) ?: 'U';
    $layoutAvatarColors = ['#3B82F6', '#8B5CF6', '#10B981', '#F59E0B', '#EF4444'];
    $layoutAvatarBg = $layoutAvatarColors[abs(crc32($layoutUserName ?? 'U')) % 5];
@endphp
<!DOCTYPE html>
<html class="h-full" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <script>
        // App is light-only — never apply `dark` class regardless of stored theme or OS preference.
        // Density (compact) is still honored.
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $userTheme = session('user.theme');
        if (! $userTheme && session('api_token')) {
            $userTheme = \App\Models\User::find(session('user.id'))?->theme;
        }
        $userDensity = session('user.density');
        if (! $userDensity && session('api_token')) {
            $userDensity = \App\Models\User::find(session('user.id'))?->density;
        }
    @endphp
    @if($userTheme && in_array($userTheme, ['light','dark'], true))
        <meta name="user-theme" content="{{ $userTheme }}">
    @endif
    @if($userDensity === 'compact')
        <meta name="user-density" content="compact">
    @endif

    <title>{{ $appDisplayName }} - @yield('title', __('common.dashboard'))</title>

    <link rel="icon" href="data:,">

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script>
        window.__PINNED_MENU_IDS__ = @json(($pinnedMenus ?? collect())->pluck('id')->map(fn ($id) => (string) $id)->values());
    </script>

    @stack('scripts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200"
      x-data="{ sidebarOpen: false, sidebarCollapsed: false }"
      data-submit-loading-text="{{ __('common.saving_in_progress') }}">
    <div class="flex min-h-screen">
        {{-- Mobile overlay --}}
        <div x-show="sidebarOpen"
             x-transition:enter="transition-opacity ease-linear duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="sidebarOpen = false"
             class="fixed inset-0 z-20 bg-slate-900/50 lg:hidden"
             x-cloak
             aria-hidden="true"></div>

        {{-- Spacer: occupies left space so main content is not overlapped (sidebar is fixed) --}}
        <div class="hidden lg:block flex-shrink-0 transition-[width] duration-200 ease-in-out bg-transparent"
             data-sidebar-spacer
             :style="{ width: sidebarCollapsed ? '5rem' : '16rem' }"></div>

        {{-- Sidebar --}}
        <aside class="app-sidebar fixed inset-y-0 left-0 z-30 flex flex-col transform transition-all duration-200 ease-in-out -translate-x-full lg:translate-x-0"
               style="background: linear-gradient(to bottom, #1e40af, #1d4ed8);"
               :class="{
                   'w-64': !sidebarCollapsed,
                   'w-20': sidebarCollapsed,
                   'translate-x-0': sidebarOpen
               }">
            <div class="h-16 flex items-center justify-between px-4 border-b border-white/10">
                <button type="button"
                        @click="sidebarCollapsed = !sidebarCollapsed"
                        class="sidebar-brand text-white cursor-pointer hover:opacity-90 bg-transparent border-0 p-0 text-left"
                        style="font-size: 30px; font-weight: 900; letter-spacing: 0.08em; line-height: 1;"
                        :title="sidebarCollapsed ? 'ขยายเมนู' : 'ยุบเมนู'"
                        aria-label="ยุบหรือขยายเมนู">
                    <span x-show="!sidebarCollapsed" x-cloak>{{ $appDisplayName }}</span>
                    <span x-show="sidebarCollapsed" x-cloak>{{ $brandAbbr }}</span>
                </button>
                <button @click="sidebarOpen = false" type="button" class="lg:hidden inline-flex items-center justify-center min-h-11 min-w-11 p-2 -mr-2 text-blue-200 hover:text-white focus:outline-none rounded-lg" aria-label="ปิดเมนู">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <nav id="sidebar-nav" class="sidebar-nav-scroll flex-1 min-h-0 p-4 space-y-1 overflow-y-auto overflow-x-hidden">
                @if(!empty($pinnedMenus) && $pinnedMenus->isNotEmpty())
                    <div class="mb-3" x-show="!sidebarCollapsed" x-cloak>
                        <p class="px-3 text-[10px] font-semibold uppercase tracking-wider text-blue-200/70 mb-1">
                            ★ {{ __('common.pinned_favorites') ?? 'Pinned' }}
                        </p>
                        <x-sidebar-menu :menus="$pinnedMenus" :is-pinned-section="true" />
                        <div class="border-t border-white/10 mt-2"></div>
                    </div>
                @endif
                <x-sidebar-menu :menus="$navigationMenus ?? collect()" />
            </nav>

            <div class="p-4 border-t border-white/10"
                 :class="sidebarCollapsed ? 'flex justify-center' : ''">
                <div class="flex items-center gap-3" :class="sidebarCollapsed ? 'justify-center' : ''">
                    <img src="{{ $layoutUserAvatar }}" alt="" class="w-9 h-9 shrink-0 rounded-full object-cover ring-2 ring-blue-400/50">
                    <div x-show="!sidebarCollapsed" x-cloak class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-white truncate">{{ $layoutUserName }}</p>
                        <p class="text-xs text-blue-200 truncate">{{ $layoutUser['email'] ?? '' }}</p>
                        <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">v{{ config('app.version') }}</div>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Main: no extra pl-* on lg; spacer above already reserves sidebar width (fixed aside does not consume flex space) --}}
        <div class="flex-1 min-w-0 flex flex-col gap-4">
            <header class="sticky top-0 z-20 h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex items-center justify-between gap-4 px-4 sm:px-8">
                <div class="flex items-center gap-3 min-w-0">
                    <button @click="sidebarOpen = true" type="button" class="lg:hidden shrink-0 inline-flex items-center justify-center min-h-11 min-w-11 p-2 -ml-2 text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg focus:outline-none" aria-label="Open menu">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100 truncate">@yield('title', __('common.dashboard'))</h1>
                </div>

                <div class="flex items-center gap-2">
                    @stack('header-actions')
                    {{-- Theme toggle removed — app is light-only --}}

                    <button @click="$store.density.toggle()"
                            class="inline-flex items-center justify-center min-h-11 min-w-11 p-2 rounded-lg transition-colors
                                   text-slate-500 dark:text-slate-400
                                   hover:bg-slate-100 dark:hover:bg-slate-800"
                            :aria-label="$store.density.mode === 'compact' ? '{{ __('common.density_switch_to_comfortable') }}' : '{{ __('common.density_switch_to_compact') }}'"
                            :title="$store.density.mode === 'compact' ? '{{ __('common.density_compact') }}' : '{{ __('common.density_comfortable') }}'">
                        {{-- comfortable icon: 3 widely-spaced rows --}}
                        <svg x-show="$store.density.mode !== 'compact'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                        {{-- compact icon: 4 dense rows --}}
                        <svg x-show="$store.density.mode === 'compact'" x-cloak class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5h16M4 9h16M4 13h16M4 17h16"/>
                        </svg>
                    </button>

                    <div class="flex rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden text-xs">
                        <a href="{{ route('lang.switch', 'th') }}"
                           class="px-2.5 py-1 font-medium transition-colors
                                  {{ app()->getLocale() === 'th' ? 'bg-blue-600 text-white' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700' }}">
                            TH
                        </a>
                        <a href="{{ route('lang.switch', 'en') }}"
                           class="px-2.5 py-1 font-medium transition-colors border-l border-slate-200 dark:border-slate-700
                                  {{ app()->getLocale() === 'en' ? 'bg-blue-600 text-white' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700' }}">
                            EN
                        </a>
                    </div>

                    <x-notification-bell />

                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" type="button"
                                class="flex items-center gap-1.5 min-h-11 px-2 py-1 rounded-lg
                                       hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                            @if($layoutUser['avatar'] ?? null)
                                <img src="{{ $layoutUserAvatar }}" alt="" class="w-7 h-7 rounded-full object-cover">
                            @else
                                <div class="w-7 h-7 rounded-full flex items-center justify-center
                                            text-xs font-bold text-white"
                                     style="background: {{ $layoutAvatarBg }}">
                                    {{ $layoutUserInitials }}
                                </div>
                            @endif
                            <svg class="w-3.5 h-3.5 text-slate-400 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div x-show="open" @click.outside="open = false" x-cloak
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute right-0 top-10 w-52 z-50
                                    bg-white dark:bg-slate-800
                                    border border-slate-200 dark:border-slate-700
                                    rounded-[12px] shadow-[var(--shadow-lg)] py-1">

                            <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-700">
                                <p class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate">
                                    {{ $layoutUserName }}
                                </p>
                                <p class="text-xs text-slate-400 dark:text-slate-400 truncate">{{ $layoutUser['email'] ?? '' }}</p>
                            </div>

                            <a href="{{ route('profile.edit') }}"
                               class="flex items-center gap-2.5 px-3 py-2 text-sm
                                      text-slate-700 dark:text-slate-300
                                      hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                <svg class="w-4 h-4 text-slate-400 dark:text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                {{ __('common.my_profile') }}
                            </a>

                            @if (! empty($layoutCanChangePassword))
                            <a href="{{ route('profile.password') }}"
                               class="flex items-center gap-2.5 px-3 py-2 text-sm
                                      text-slate-700 dark:text-slate-300
                                      hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                <svg class="w-4 h-4 text-slate-400 dark:text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                {{ __('common.change_password') }}
                            </a>
                            @elseif (! empty($authPasswordHelpUrl))
                            <a href="{{ $authPasswordHelpUrl }}" target="_blank" rel="noopener noreferrer"
                               class="flex items-center gap-2.5 px-3 py-2 text-sm
                                      text-slate-700 dark:text-slate-300
                                      hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                </svg>
                                {{ __('auth.open_password_help_link') }}
                            </a>
                            @endif

                            <div class="my-1 border-t border-slate-100 dark:border-slate-700"></div>

                            <form method="POST" action="{{ route('logout') }}" novalidate>
                                @csrf
                                <button type="submit"
                                        class="w-full flex items-center gap-2.5 px-3 py-2 text-sm
                                               text-red-600 dark:text-red-400
                                               hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    {{ __('common.sign_out') }}
                                </button>
                            </form>

                        </div>
                    </div>
                </div>
            </header>

            @hasSection('breadcrumb')
                <div class="px-4 sm:px-6 lg:px-10 py-[var(--cell-pad-y)] border-b border-slate-100 dark:border-slate-800 text-sm text-slate-500 dark:text-slate-400">
                    @yield('breadcrumb')
                </div>
            @endif

            {{-- overflow-x-hidden breaks position:sticky inside main (e.g. document form builder). Allow horizontal overflow on those pages only. --}}
            @php
                $isFormFillPage = request()->routeIs('forms.create', 'forms.draft.edit', 'forms.submission.show', 'settings.workflow.edit', 'settings.workflow.create');
                $isFormBuilderPage = request()->routeIs('settings.document-forms.create', 'settings.document-forms.edit');
            @endphp
            <main @class([
                'flex-1 w-full min-w-0',
                'p-4 sm:p-6 lg:px-10' => ! $isFormFillPage,
                'p-3 sm:p-4 lg:px-4 lg:py-5' => $isFormFillPage,
                'overflow-x-hidden'   => ! $isFormBuilderPage,
                'overflow-x-visible'  => $isFormBuilderPage,
            ])>
                @yield('content')
            </main>
        </div>
    </div>

    {{-- Page-level floating actions (outside <main> scroll/overflow) --}}
    @stack('floating-actions')

    <style>
        [x-cloak] { display: none !important; }
    </style>
    <script>
        (function() {
            if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
            window.addEventListener('beforeunload', function() {
                try {
                    var n = document.getElementById('sidebar-nav');
                    if (n) sessionStorage.setItem('sidebarScroll', String(n.scrollTop));
                } catch (e) {}
            });
            function restoreSidebarScroll() {
                try {
                    var saved = sessionStorage.getItem('sidebarScroll');
                    if (saved !== null) {
                        var nav = document.getElementById('sidebar-nav');
                        if (nav) {
                            var n = parseInt(saved, 10);
                            if (!isNaN(n) && n >= 0) nav.scrollTop = n;
                        }
                        sessionStorage.removeItem('sidebarScroll');
                    }
                } catch (e) {}
            }
            function runRestore() {
                restoreSidebarScroll();
                setTimeout(restoreSidebarScroll, 50);
                setTimeout(restoreSidebarScroll, 200);
                setTimeout(restoreSidebarScroll, 400);
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    requestAnimationFrame(runRestore);
                });
            } else {
                requestAnimationFrame(runRestore);
            }
        })();
    </script>
</body>
</html>
