@php
    $hasChildren = $unit->children->isNotEmpty();
    $typeBadgeClass = match($unit->type) {
        'company'    => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
        'division'   => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
        'department' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
        'section'    => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
        default      => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    };
    $typeLabel = __('common.org_type_' . $unit->type);
@endphp
<div class="group">
    <div class="flex items-center gap-2 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors"
         style="padding-left: {{ 16 + $depth * 24 }}px">

        {{-- Expand/collapse toggle --}}
        @if ($hasChildren)
            <button type="button"
                    @click="toggle({{ $unit->id }})"
                    class="flex-shrink-0 w-5 h-5 flex items-center justify-center text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 transition-transform"
                    :class="isCollapsed({{ $unit->id }}) ? '' : 'rotate-90'">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        @else
            <span class="flex-shrink-0 w-5 h-5"></span>
        @endif

        {{-- Type badge --}}
        <span class="flex-shrink-0 text-xs px-1.5 py-0.5 rounded font-medium {{ $typeBadgeClass }}">{{ $typeLabel }}</span>

        {{-- Name --}}
        <span class="flex-1 text-sm font-medium text-slate-900 dark:text-slate-100 {{ $unit->is_active ? '' : 'opacity-50 line-through' }}">
            {{ $unit->name }}
            <span class="ml-1 text-xs text-slate-400 dark:text-slate-500 font-mono">{{ $unit->auto_code }}</span>
        </span>

        {{-- Head --}}
        @if ($unit->head)
            <span class="hidden sm:inline-flex items-center text-xs text-slate-500 dark:text-slate-400 gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                {{ $unit->head->first_name }} {{ $unit->head->last_name }}
            </span>
        @endif

        {{-- Actions --}}
        <div class="flex-shrink-0">
            <x-row-actions :items="[
                ['label' => __('common.edit'), 'href' => route('settings.org-units.edit', $unit), 'icon' => 'edit'],
                ['label' => $unit->is_active ? __('common.disable') : __('common.enable'), 'method' => 'PUT', 'action' => route('settings.org-units.update', $unit), 'icon' => 'toggle', 'hidden' => ['toggle_active' => '1']],
                ['label' => __('common.delete'), 'method' => 'DELETE', 'action' => route('settings.org-units.destroy', $unit), 'icon' => 'delete', 'confirm' => __('common.delete_confirm_msg', ['name' => $unit->name]), 'class' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20'],
            ]" />
        </div>
    </div>

    {{-- Children --}}
    @if ($hasChildren)
        <div x-show="!isCollapsed({{ $unit->id }})">
            @foreach ($unit->children as $child)
                @include('settings.org-units._node', ['unit' => $child, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif
</div>
