@props([
    'colspan' => 1,
    'message' => null,
    'card' => false,
])
@php
    $message = $message ?? __('common.table_empty_title');
@endphp
@if ($card)
    <div class="bg-white dark:bg-slate-800 rounded-[12px] border border-slate-200 dark:border-slate-700 shadow-[var(--shadow-sm)] px-6 py-8">
        <div class="mx-auto flex max-w-sm flex-col items-center gap-3 text-slate-400 dark:text-slate-500 text-center">
            <svg class="w-10 h-10 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4"/>
            </svg>
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $message }}</p>
            {{ $slot }}
        </div>
    </div>
@else
    <tr>
        <td colspan="{{ $colspan }}" class="px-6 py-8 text-center">
            <div class="mx-auto flex max-w-sm flex-col items-center gap-3 text-slate-400 dark:text-slate-500">
                <svg class="w-10 h-10 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4"/>
                </svg>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $message }}</p>
                {{ $slot }}
            </div>
        </td>
    </tr>
@endif
