@props(['stat'])

@if ($stat['avg'] !== null)
    <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ number_format($stat['avg'], 2) }}</span>
@else
    <span class="text-xs text-slate-400">—</span>
@endif
<div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">
    {{ $stat['completed'] }} / {{ $stat['total'] }}
</div>
