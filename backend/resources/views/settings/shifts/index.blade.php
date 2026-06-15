@extends('layouts.app')

@section('title', __('common.shifts'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.shifts')],
    ]" />
@endsection

@section('content')
<div>
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ __('common.shifts') }}</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('common.shifts_hint') }}</p>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4"><p class="text-sm">{{ session('success') }}</p></div>
    @endif
    @if ($errors->any())
        <div class="alert-error mb-4"><p class="text-sm">{{ $errors->first() }}</p></div>
    @endif

    {{-- ── Shift master ─────────────────────────────── --}}
    <div class="card p-4 mb-4">
        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">{{ __('common.shift_master') }}</h3>
        <form method="POST" action="{{ route('settings.shifts.store') }}" class="flex flex-wrap items-end gap-3 mb-4">
            @csrf
            <div>
                <label class="block text-xs text-slate-500 mb-1">{{ __('common.code') }}</label>
                <input type="text" name="code" required maxlength="50" value="{{ old('code') }}" class="form-input text-sm w-28">
            </div>
            <div class="flex-1 min-w-40">
                <label class="block text-xs text-slate-500 mb-1">{{ __('common.name') }}</label>
                <input type="text" name="name" required maxlength="255" value="{{ old('name') }}" class="form-input text-sm w-full">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">{{ __('common.shift_start') }}</label>
                <input type="time" name="start_time" required value="{{ old('start_time') }}" class="form-input text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">{{ __('common.shift_end') }}</label>
                <input type="time" name="end_time" required value="{{ old('end_time') }}" class="form-input text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">{{ __('common.shift_break_minutes') }}</label>
                <input type="number" name="break_minutes" min="0" max="480" value="{{ old('break_minutes', 60) }}" class="form-input text-sm w-24">
            </div>
            <button type="submit" class="btn-primary">{{ __('common.add') }}</button>
        </form>

        @if ($shifts->isEmpty())
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('common.no_data') }}</p>
        @else
            <div class="table-wrapper">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('common.code') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('common.name') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('common.shift_time') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('common.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach ($shifts as $shift)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 {{ $shift->is_active ? '' : 'opacity-60' }}">
                                <td class="px-4 py-3 text-sm font-mono text-slate-900 dark:text-slate-100">{{ $shift->code }}</td>
                                <td class="px-4 py-3 text-sm text-slate-900 dark:text-slate-100">{{ $shift->name }}</td>
                                <td class="px-4 py-3 text-sm text-slate-500">
                                    {{ substr($shift->start_time, 0, 5) }} – {{ substr($shift->end_time, 0, 5) }}
                                    @if ($shift->crossesMidnight())
                                        <span class="badge-blue text-[11px] ml-1">{{ __('common.shift_overnight') }}</span>
                                    @endif
                                    @if ($shift->break_minutes > 0)
                                        <span class="text-xs text-slate-400 ml-1">({{ __('common.shift_break_label', ['minutes' => $shift->break_minutes]) }})</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if ($shift->is_active)
                                        <span class="badge-success text-xs">{{ __('common.active') }}</span>
                                    @else
                                        <span class="badge-neutral text-xs">{{ __('common.inactive') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <x-row-actions :items="[
                                        ['label' => $shift->is_active ? __('common.deactivate') : __('common.activate'), 'method' => 'PATCH', 'action' => route('settings.shifts.toggle', $shift), 'icon' => 'toggle'],
                                        ['label' => __('common.delete'), 'method' => 'DELETE', 'action' => route('settings.shifts.destroy', $shift), 'icon' => 'delete', 'confirm' => __('common.shift_delete_confirm'), 'class' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20'],
                                    ]" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ── Assignments ─────────────────────────────── --}}
    <div class="card p-4">
        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">{{ __('common.shift_assignments') }}</h3>
        <form method="POST" action="{{ route('settings.shifts.assign') }}" class="flex flex-wrap items-end gap-3 mb-4">
            @csrf
            <div class="flex-1 min-w-48">
                <label class="block text-xs text-slate-500 mb-1">{{ __('common.user') }}</label>
                <select name="user_id" required class="form-input text-sm w-full">
                    <option value="">{{ __('common.select') }}</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected(old('user_id') == $u->id)>{{ trim($u->first_name.' '.$u->last_name) }} ({{ $u->email }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">{{ __('common.shift') }}</label>
                <select name="shift_id" required class="form-input text-sm">
                    @foreach ($shifts->where('is_active', true) as $shift)
                        <option value="{{ $shift->id }}" @selected(old('shift_id') == $shift->id)>{{ $shift->code }} — {{ $shift->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">{{ __('common.effective_from') }}</label>
                <input type="date" name="effective_from" required value="{{ old('effective_from', now()->toDateString()) }}" class="form-input text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">{{ __('common.effective_to') }} <span class="text-slate-400">({{ __('common.optional') }})</span></label>
                <input type="date" name="effective_to" value="{{ old('effective_to') }}" class="form-input text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">{{ __('common.shift_work_days') }}</label>
                <div class="flex gap-1.5 pt-1">
                    @foreach ([1 => 'จ', 2 => 'อ', 3 => 'พ', 4 => 'พฤ', 5 => 'ศ', 6 => 'ส', 7 => 'อา'] as $d => $label)
                        <label class="inline-flex items-center gap-0.5 text-xs text-slate-600 dark:text-slate-300">
                            <input type="checkbox" name="work_days[]" value="{{ $d }}" @checked(in_array($d, old('work_days', [1,2,3,4,5])))> {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <button type="submit" class="btn-primary">{{ __('common.shift_assign') }}</button>
        </form>

        @if ($assignments->isEmpty())
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('common.no_data') }}</p>
        @else
            <div class="table-wrapper">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('common.user') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('common.shift') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('common.date_range') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('common.shift_work_days') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach ($assignments as $a)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3 text-sm text-slate-900 dark:text-slate-100">
                                    {{ trim(($a->user?->first_name ?? '').' '.($a->user?->last_name ?? '')) ?: '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-900 dark:text-slate-100">
                                    {{ $a->shift?->code }} — {{ $a->shift?->name }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-500">
                                    {{ $a->effective_from->format('d/m/Y') }} —
                                    @if ($a->effective_to)
                                        {{ $a->effective_to->format('d/m/Y') }}
                                    @else
                                        <span class="text-slate-400">{{ __('common.no_end_date') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-500">
                                    @php $dayLabels = [1 => 'จ', 2 => 'อ', 3 => 'พ', 4 => 'พฤ', 5 => 'ศ', 6 => 'ส', 7 => 'อา']; @endphp
                                    {{ collect($a->work_days ?? [])->map(fn ($d) => $dayLabels[$d] ?? $d)->join(' ') ?: __('common.shift_all_days') }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <x-row-actions :items="[
                                        ['label' => __('common.delete'), 'method' => 'DELETE', 'action' => route('settings.shifts.assignments.destroy', $a), 'icon' => 'delete', 'confirm' => __('common.confirm_delete'), 'class' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20'],
                                    ]" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pt-3">{{ $assignments->links() }}</div>
        @endif
    </div>
</div>
@endsection
