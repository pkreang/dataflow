@props(['menus', 'isPinnedSection' => false, 'menuBadges' => []])

@foreach($menus as $menu)
    @if($menu->route === null && $menu->children->isNotEmpty())
        {{-- Group with submenu (expand / collapse) --}}
        <div class="pt-3"
             x-data="{
                 open: {{ $menu->hasActiveChild() ? 'true' : 'false' }}
                       || (localStorage.getItem('nav_menu_{{ $menu->id }}') === '1')
             }"
             x-effect="localStorage.setItem('nav_menu_{{ $menu->id }}', open ? '1' : '0')">

            <button @click="open = !open" type="button"
                    class="w-full flex items-center rounded-lg px-[var(--menu-item-pad-x)] py-[var(--menu-item-pad-y)] text-blue-100 hover:bg-white/10 transition-colors duration-200"
                    :class="sidebarCollapsed ? 'justify-center' : 'justify-between'">
                <span class="flex items-center gap-3 min-w-0" :class="sidebarCollapsed ? 'w-full justify-center' : ''">
                    <x-nav-icon :name="$menu->icon" class="w-5 h-5 shrink-0 text-blue-200" />
                    <span class="text-sm font-medium truncate" x-show="!sidebarCollapsed" x-cloak>{{ $menu->translated_label }}</span>
                </span>
                <svg x-show="!sidebarCollapsed"
                     :class="open && 'rotate-180'"
                     class="w-4 h-4 text-blue-300 transition-transform duration-200"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="open && !sidebarCollapsed"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-1"
                 x-cloak
                 class="ml-5 mt-1 space-y-0.5 border-l border-white/20 pl-3">

                @php
                    $activeChild = $menu->children->filter(fn ($c) => $c->isActive())->sortByDesc(fn ($c) => strlen($c->route ?? ''))->first();
                @endphp
                @foreach($menu->children as $child)
                    @php $childActive = $activeChild && $child->id === $activeChild->id; @endphp
                    <div class="relative group">
                        <a href="{{ $child->route }}" @click="sidebarOpen = false"
                           class="flex items-center gap-3 px-[var(--menu-item-pad-x)] py-[var(--menu-sub-pad-y)] rounded-lg text-sm font-medium transition-colors {{ $childActive ? 'bg-white/15 text-white font-semibold' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
                           @if(! $isPinnedSection) :class="sidebarCollapsed ? '' : 'pr-8'" @endif>
                            <x-nav-icon :name="$child->icon" class="w-4 h-4" />
                            <span x-show="!sidebarCollapsed" x-cloak>{{ $child->translated_label }}</span>
                            @php $badge = (int) ($menuBadges[$child->route] ?? 0); @endphp
                            @if($badge > 0)
                                <span x-show="!sidebarCollapsed" x-cloak
                                      class="ml-auto inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none">{{ $badge > 99 ? '99+' : $badge }}</span>
                            @endif
                        </a>
                        @if(! $isPinnedSection && $child->id > 0 && $child->route)
                            <span x-show="!sidebarCollapsed" x-cloak>
                                <x-sidebar-pin-button :menu-id="$child->id" size="sm" />
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

    @else
        {{-- Single menu item --}}
        @php $menuActive = $menu->isActive(); @endphp
        <div class="relative group">
            <a href="{{ $menu->route }}" @click="sidebarOpen = false"
               class="flex items-center rounded-lg px-[var(--menu-item-pad-x)] py-[var(--menu-item-pad-y)] text-sm font-medium {{ $menuActive ? 'bg-white/15 text-white font-semibold' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
               :class="sidebarCollapsed ? 'justify-center' : @js($isPinnedSection ? 'gap-3' : 'gap-3 pr-8')">
                <x-nav-icon :name="$menu->icon" class="w-5 h-5 shrink-0 text-blue-200" />
                <span x-show="!sidebarCollapsed" x-cloak>{{ $menu->translated_label }}</span>
                @php $badge = (int) ($menuBadges[$menu->route] ?? 0); @endphp
                @if($badge > 0)
                    <span x-show="!sidebarCollapsed" x-cloak
                          class="ml-auto inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none">{{ $badge > 99 ? '99+' : $badge }}</span>
                @endif
            </a>
            @if(! $isPinnedSection && $menu->id > 0 && $menu->route)
                <span x-show="!sidebarCollapsed" x-cloak>
                    <x-sidebar-pin-button :menu-id="$menu->id" />
                </span>
            @endif
        </div>
    @endif
@endforeach
