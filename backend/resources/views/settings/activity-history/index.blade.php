@extends('layouts.app')

@section('title', __('common.activity_history'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.activity_history')],
    ]" />
@endsection

@section('content')
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ __('common.activity_history') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ __('common.activity_history_desc') }}</p>
        </div>
        <a href="{{ route('settings.activity-history.export', request()->query()) }}"
           class="btn-secondary inline-flex items-center gap-2 text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            {{ __('common.export') }} CSV
        </a>
    </div>

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('settings.activity-history.index') }}"
          class="card px-4 py-3 mb-4 flex flex-wrap items-end gap-3">

        <div class="flex-1 min-w-[160px]">
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">
                {{ __('common.entity_type') }}
            </label>
            <select name="entity_type"
                    class="form-select w-full text-sm">
                <option value="">{{ __('common.all') }}</option>
                @foreach ($entityTypes as $type)
                    <option value="{{ $type }}" @selected(request('entity_type') === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex-1 min-w-[160px]">
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">
                {{ __('common.actor') }}
            </label>
            <select name="actor_id" class="form-select w-full text-sm">
                <option value="">{{ __('common.all') }}</option>
                @foreach ($actors as $actor)
                    <option value="{{ $actor->id }}" @selected((string) request('actor_id') === (string) $actor->id)>
                        {{ $actor->first_name }} {{ $actor->last_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="flex-1 min-w-[130px]">
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">
                {{ __('common.from') }}
            </label>
            <input type="date" name="date_from" value="{{ request('date_from') }}"
                   class="form-input w-full text-sm">
        </div>

        <div class="flex-1 min-w-[130px]">
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">
                {{ __('common.to') }}
            </label>
            <input type="date" name="date_to" value="{{ request('date_to') }}"
                   class="form-input w-full text-sm">
        </div>

        <div class="flex-1 min-w-[180px]">
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">
                {{ __('common.search') }}
            </label>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="{{ __('common.actor') }}..."
                   class="form-input w-full text-sm">
        </div>

        <div class="flex gap-2 shrink-0">
            <button type="submit" class="btn-primary text-sm">{{ __('common.filter') }}</button>
            @if (request()->hasAny(['entity_type', 'actor_id', 'date_from', 'date_to', 'search']))
                <a href="{{ route('settings.activity-history.index') }}"
                   class="btn-secondary text-sm">{{ __('common.reset') }}</a>
            @endif
        </div>
    </form>

    <x-data-table
        :columns="[
            ['key' => 'created_at', 'label' => __('common.when')],
            ['key' => 'actor',      'label' => __('common.actor')],
            ['key' => 'entity',     'label' => __('common.entity_type').' / ID'],
            ['key' => 'action',     'label' => __('common.action')],
            ['key' => 'details',    'label' => __('common.changed_fields')],
        ]"
        :rows="$logs"
        :empty-message="__('common.system_change_log_empty')"
        :disable-pagination="true"
    >
        @foreach ($logs as $log)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                <td class="table-sub whitespace-nowrap">
                    {{ $log->created_at?->format('d/m/Y H:i') }}
                </td>
                <td class="table-primary">
                    @if ($log->actor)
                        {{ $log->actor->first_name }} {{ $log->actor->last_name }}
                    @else
                        <span class="text-slate-400">{{ __('common.system') }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-sm">
                    <span class="font-medium text-slate-700 dark:text-slate-300">{{ $log->entity_type }}</span>
                    @if ($log->entity_id)
                        <span class="ml-1 text-slate-400 dark:text-slate-500">#{{ $log->entity_id }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-sm">
                    @php
                        $actionClass = match ($log->action) {
                            'created' => 'text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-900/30',
                            'updated' => 'text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30',
                            'deleted' => 'text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/30',
                            default   => 'text-slate-600 dark:text-slate-400 bg-slate-100 dark:bg-slate-700',
                        };
                        $actionLabel = match ($log->action) {
                            'created' => __('common.action_created'),
                            'updated' => __('common.action_updated'),
                            'deleted' => __('common.action_deleted'),
                            default   => $log->action,
                        };
                    @endphp
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $actionClass }}">
                        {{ $actionLabel }}
                    </span>
                </td>
                <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400 max-w-xs">
                    @if ($log->changed_fields)
                        <div class="space-y-0.5">
                            @foreach (array_slice((array) $log->changed_fields, 0, 3) as $field => $change)
                                <div class="text-xs">
                                    <span class="font-medium text-slate-600 dark:text-slate-300">{{ $field }}</span>:
                                    @if (is_array($change) && isset($change['from'], $change['to']))
                                        <span class="line-through text-slate-400">{{ is_array($change['from']) ? '[array]' : Str::limit((string) $change['from'], 30) }}</span>
                                        <span class="mx-0.5 text-slate-400">→</span>
                                        <span>{{ is_array($change['to']) ? '[array]' : Str::limit((string) $change['to'], 30) }}</span>
                                    @else
                                        <span>{{ Str::limit(json_encode($change, JSON_UNESCAPED_UNICODE), 50) }}</span>
                                    @endif
                                </div>
                            @endforeach
                            @if (count((array) $log->changed_fields) > 3)
                                <div class="text-xs text-slate-400">+{{ count((array) $log->changed_fields) - 3 }} more...</div>
                            @endif
                        </div>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-per-page-footer :paginator="$logs" :perPage="$perPage" id="activity-history-pagination" />
@endsection
