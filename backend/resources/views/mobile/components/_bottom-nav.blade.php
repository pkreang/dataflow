@php
    $currentRoute = request()->route()?->getName() ?? '';
    $tabs = [
        [
            'name' => 'mobile.home',
            'url' => route('mobile.home'),
            'label' => __('common.overview'),
            'icon' => 'M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1h-5v-7H9v7H4a1 1 0 01-1-1V9.5z',
        ],
        [
            'name' => 'mobile.write',
            'url' => route('mobile.forms'),
            'label' => __('common.write_form'),
            'icon' => 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z',
        ],
        [
            'name' => 'mobile.requests',
            'url' => route('mobile.requests'),
            'label' => __('common.requests'),
            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        ],
        [
            'name' => 'mobile.approvals',
            'url' => route('mobile.approvals'),
            'label' => __('common.approvals'),
            'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        [
            'name' => 'mobile.me',
            'url' => route('mobile.me'),
            'label' => __('common.settings'),
            'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
        ],
    ];
@endphp
<nav class="mob-bottom-nav">
    @foreach($tabs as $tab)
        @php
            $active = $currentRoute === $tab['name']
                || ($tab['name'] === 'mobile.write' && in_array($currentRoute, ['mobile.forms', 'mobile.write']));
        @endphp
        <a href="{{ $tab['url'] }}" class="{{ $active ? 'active' : '' }}">
            <span class="w-9 h-9 rounded-xl flex items-center justify-center {{ $active ? '' : '' }}"
                  @if($active) style="background: rgba(15,110,216,.12)" @endif>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="{{ $active ? '2.4' : '1.8' }}">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $tab['icon'] }}"/>
                </svg>
            </span>
            <span class="truncate max-w-full">{{ $tab['label'] }}</span>
        </a>
    @endforeach
</nav>
