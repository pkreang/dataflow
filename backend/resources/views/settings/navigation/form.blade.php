@extends('layouts.app')

@section('title', $menu->exists ? __('common.edit_menu_item') : __('common.add_menu_item'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.navigation_menu'), 'url' => route('settings.navigation.index')],
        ['label' => $menu->exists ? __('common.edit') : __('common.add')],
    ]" />
@endsection

@section('content')
<div class="max-w-2xl" x-data="navMenuForm()">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ $menu->exists ? __('common.edit_menu_item') : __('common.add_menu_item') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $menu->exists ? __('common.update_menu_item_desc') : __('common.create_menu_item_desc') }}</p>
        </div>
        <a href="{{ route('settings.navigation.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500">&larr; {{ __('common.back') }}</a>
    </div>

    @if ($errors->any())
        <div class="alert-error mb-4">
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ $menu->exists ? route('settings.navigation.update', $menu) : route('settings.navigation.store') }}" novalidate>
        @csrf
        @if ($menu->exists) @method('PUT') @endif

        <div class="card p-6 space-y-5">
            {{-- Label (Thai) --}}
            <div>
                <label for="label_th" class="form-label">{{ __('common.menu_field_label_th') }}</label>
                <input type="text" name="label_th" id="label_th"
                       value="{{ old('label_th', $menu->label_th) }}"
                       placeholder="{{ __('common.menu_field_label_th_placeholder') }}"
                       class="form-input">
            </div>

            {{-- Label (English) --}}
            <div>
                <label for="label_en" class="form-label">{{ __('common.menu_field_label_en') }}</label>
                <input type="text" name="label_en" id="label_en"
                       value="{{ old('label_en', $menu->label_en) }}"
                       placeholder="{{ __('common.menu_field_label_en_placeholder') }}"
                       class="form-input">
            </div>

            {{-- Icon --}}
            <div>
                <label for="icon" class="form-label">{{ __('common.menu_field_icon') }}</label>
                <div class="flex items-center gap-3">
                    <input type="text" name="icon" id="icon"
                           x-model="icon"
                           value="{{ old('icon', $menu->icon) }}"
                           placeholder="e.g. home, users, key"
                           class="form-input flex-1">
                    <div class="flex items-center gap-2 px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg min-w-[100px]">
                        <span class="text-xs text-slate-500 dark:text-slate-400">Preview:</span>
                        <span x-show="icon && icons[icon]"
                              x-html="icons[icon] || ''"
                              class="w-5 h-5 text-blue-600"></span>
                        <span x-show="!icon || !icons[icon]" class="text-xs text-slate-400 dark:text-slate-400">—</span>
                    </div>
                </div>
                <p class="text-xs text-slate-400 dark:text-slate-400 mt-1">{{ __('common.menu_icon_available') }}: home, settings, users, shield, key, lock-closed, bars-3, chart-bar, document, currency, cube, building-office, wrench</p>
                <p class="text-xs text-slate-400 dark:text-slate-400 mt-0.5">{{ __('common.menu_icon_source') }}</p>
            </div>

            {{-- Route --}}
            <div>
                <label for="route" class="form-label">{{ __('common.menu_field_route') }}</label>
                <input type="text" name="route" id="route"
                       value="{{ old('route', $menu->route) }}"
                       placeholder="/dashboard (leave empty for group/parent)"
                       class="form-input">
                <p class="text-xs text-slate-400 dark:text-slate-400 mt-1">Leave empty if this is a parent/group menu (e.g. Settings)</p>
            </div>

            {{-- Parent Menu --}}
            <div>
                <label for="parent_id" class="form-label">{{ __('common.parent_menu') }}</label>
                <select name="parent_id" id="parent_id" class="form-input">
                    <option value="">{{ __('common.none_root_menu') }}</option>
                    @foreach ($parentMenus as $parent)
                        <option value="{{ $parent->id }}"
                                {{ old('parent_id', $menu->parent_id) == $parent->id ? 'selected' : '' }}>
                            {{ $parent->translated_label }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Permission --}}
            <div>
                <label for="permission" class="form-label">{{ __('common.menu_field_permission') }}</label>
                <select name="permission" id="permission" class="form-input">
                    <option value="">{{ __('common.none_visible_all') }}</option>
                    @foreach ($permissions as $perm)
                        <option value="{{ $perm }}"
                                title="{{ $perm }}"
                                {{ old('permission', $menu->permission) === $perm ? 'selected' : '' }}>
                            {{ \App\Support\PermissionDisplay::label($perm) }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('common.menu_permission_gates_route') }}</p>
            </div>

            {{-- Sort Order --}}
            <div>
                <label for="sort_order" class="form-label">{{ __('common.menu_field_order') }}</label>
                <input type="number" name="sort_order" id="sort_order"
                       value="{{ old('sort_order', $menu->sort_order ?? 0) }}"
                       min="0" max="999"
                       class="form-input w-24 text-center">
            </div>

            {{-- Status --}}
            <div>
                <x-form.active-toggle
                    name="is_active"
                    :checked="old('is_active', $menu->exists ? $menu->is_active : true)" />
            </div>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-end gap-3 mt-6">
            <a href="{{ route('settings.navigation.index') }}" class="btn-secondary">
                {{ __('common.cancel') }}
            </a>
            <button type="submit" class="btn-primary">
                {{ __('common.save') }}
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('navMenuForm', () => ({
        icon: '{{ old("icon", $menu->icon ?? "") }}',
        icons: {
            'home':        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
            'settings':    '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
            'users':       '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
            'shield':      '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
            'key':         '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>',
            'lock-closed': '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>',
            'bars-3':      '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>',
            'chart-bar':   '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
            'document':    '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            'currency':    '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            'cube':        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>',
            'building-office': '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/></svg>',
            'wrench': '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.427-.526.244-1.395-.538-1.395H12.75M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409m4.5 4.5l1.409 1.409"/></svg>',
        },
    }));
});
</script>
@endsection
