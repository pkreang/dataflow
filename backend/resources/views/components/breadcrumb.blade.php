@props(['items' => []])

@php
    // Render exactly what the caller passed — no Dashboard auto-prepend.
    // Dashboard is always one click away in the sidebar; an extra "Dashboard /"
    // prefix at every page just adds noise to deeper trails.
    $trail = collect($items)->filter(fn ($i) => ! empty($i['label']))->values();
    $last = $trail->count() - 1;
@endphp

<nav aria-label="Breadcrumb">
    <ol class="flex flex-wrap items-center gap-y-1">
        @foreach($trail as $idx => $item)
            <li class="flex items-center">
                @if($idx < $last && ! empty($item['url']))
                    <a href="{{ $item['url'] }}" class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors">{{ $item['label'] }}</a>
                @else
                    <span class="text-slate-700 dark:text-slate-300" @if($idx === $last) aria-current="page" @endif>{{ $item['label'] }}</span>
                @endif
                @if($idx < $last)
                    <span class="mx-1.5 text-slate-400 dark:text-slate-500" aria-hidden="true">/</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
