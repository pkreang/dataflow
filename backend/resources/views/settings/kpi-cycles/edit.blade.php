@extends('layouts.app')

@section('title', $cycle->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.kpi_cycles'), 'url' => route('settings.kpi-cycles.index')],
        ['label' => $cycle->name],
    ]" />
@endsection

@section('content')
    @php
        $isDraft = $cycle->status === 'draft';
        $isOpen = $cycle->status === 'open';
        $userOptions = $users->map(fn ($u) => ['id' => $u->id, 'label' => trim(($u->first_name ?: '') . ' ' . ($u->last_name ?: '')) ?: $u->email])->values();
        $initialAssignments = $cycle->assignments->map(fn ($a) => [
            'target_user_id' => $a->target_user_id,
            'evaluator_user_id' => $a->evaluator_user_id,
            'role' => $a->role,
            'submission_id' => $a->submission_id,
            'submission_status' => $a->submission?->status,
        ])->values();
    @endphp

    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ $cycle->name }}</h2>
            <div class="text-xs mt-1">
                @php
                    $cls = match ($cycle->status) {
                        'open' => 'badge-blue',
                        'closed' => 'badge-gray',
                        default => 'badge-yellow',
                    };
                @endphp
                <span class="{{ $cls }}">{{ __('common.kpi_cycle_status_' . $cycle->status) }}</span>
            </div>
        </div>
        <a href="{{ route('settings.kpi-cycles.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500">&larr; {{ __('common.back') }}</a>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4"><p class="text-sm">{{ session('success') }}</p></div>
    @endif
    @if ($errors->has('kpi_cycle'))
        <div class="alert-error mb-4"><p class="text-sm">{{ $errors->first('kpi_cycle') }}</p></div>
    @endif
    @if ($errors->any() && ! $errors->has('kpi_cycle'))
        <div class="alert-error mb-4">
            <ul class="list-disc list-inside text-sm">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('settings.kpi-cycles.update', $cycle) }}"
          x-data="kpiCycleEditor({{ Js::from($initialAssignments) }}, {{ Js::from($userOptions) }})"
          class="space-y-4" novalidate>
        @csrf
        @method('PUT')

        <div class="card p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="form-label">{{ __('common.kpi_cycle_name') }} <span class="text-red-500">*</span></label>
                <input name="name" value="{{ old('name', $cycle->name) }}" required class="form-input mt-1" />
            </div>
            <div>
                <label class="form-label">{{ __('common.kpi_cycle_form') }} <span class="text-red-500">*</span></label>
                <select name="form_id" required class="form-input mt-1" {{ $isDraft ? '' : 'disabled' }}>
                    @foreach ($forms as $form)
                        <option value="{{ $form->id }}" @selected(old('form_id', $cycle->form_id) == $form->id)>{{ $form->name }}</option>
                    @endforeach
                </select>
                @unless($isDraft)
                    <input type="hidden" name="form_id" value="{{ $cycle->form_id }}">
                @endunless
            </div>
            <div>
                <label class="form-label">{{ __('common.kpi_cycle_period_start') }}</label>
                <input type="date" name="period_start" value="{{ old('period_start', optional($cycle->period_start)->format('Y-m-d')) }}" class="form-input mt-1" />
            </div>
            <div>
                <label class="form-label">{{ __('common.kpi_cycle_period_end') }}</label>
                <input type="date" name="period_end" value="{{ old('period_end', optional($cycle->period_end)->format('Y-m-d')) }}" class="form-input mt-1" />
            </div>
        </div>

        <div class="card p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('common.kpi_cycle_assignments') }}</h3>
                @if($isDraft)
                    <button type="button" @click="addRow()" class="btn-secondary text-sm">{{ __('common.kpi_cycle_add_assignment') }}</button>
                @else
                    <span class="text-xs text-slate-400">{{ __('common.kpi_cycle_assignments_locked_after_open') }}</span>
                @endif
            </div>

            <template x-if="rows.length === 0">
                <p class="text-sm text-slate-400 italic">{{ __('common.kpi_cycle_no_assignments') }}</p>
            </template>

            <div class="overflow-x-auto" x-show="rows.length > 0">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-500">
                            <th class="py-2 pr-2">{{ __('common.kpi_cycle_assignment_target') }}</th>
                            <th class="py-2 pr-2">{{ __('common.kpi_cycle_assignment_evaluator') }}</th>
                            <th class="py-2 pr-2 w-40">{{ __('common.kpi_cycle_assignment_role') }}</th>
                            <th class="py-2 pr-2 w-40">{{ __('common.kpi_cycle_submission_status') }}</th>
                            <th class="py-2 w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, idx) in rows" :key="idx">
                            <tr class="border-t border-slate-200 dark:border-slate-700">
                                <td class="py-2 pr-2">
                                    <select :name="`assignments[${idx}][target_user_id]`"
                                            x-model.number="row.target_user_id"
                                            x-init="$nextTick(() => { if (row.target_user_id) $el.value = row.target_user_id })"
                                            class="form-input text-sm" :disabled="!{{ $isDraft ? 'true' : 'false' }}">
                                        <option value="">—</option>
                                        <template x-for="u in users" :key="u.id"><option :value="u.id" x-text="u.label"></option></template>
                                    </select>
                                </td>
                                <td class="py-2 pr-2">
                                    <select :name="`assignments[${idx}][evaluator_user_id]`"
                                            x-model.number="row.evaluator_user_id"
                                            x-init="$nextTick(() => { if (row.evaluator_user_id) $el.value = row.evaluator_user_id })"
                                            class="form-input text-sm" :disabled="!{{ $isDraft ? 'true' : 'false' }}">
                                        <option value="">—</option>
                                        <template x-for="u in users" :key="u.id"><option :value="u.id" x-text="u.label"></option></template>
                                    </select>
                                </td>
                                <td class="py-2 pr-2">
                                    <select :name="`assignments[${idx}][role]`" x-model="row.role" class="form-input text-sm" :disabled="!{{ $isDraft ? 'true' : 'false' }}">
                                        <option value="self">{{ __('common.kpi_cycle_role_self') }}</option>
                                        <option value="supervisor">{{ __('common.kpi_cycle_role_supervisor') }}</option>
                                        <option value="peer">{{ __('common.kpi_cycle_role_peer') }}</option>
                                    </select>
                                </td>
                                <td class="py-2 pr-2 text-xs text-slate-500">
                                    <span x-text="row.submission_status || '—'"></span>
                                </td>
                                <td class="py-2 text-right">
                                    @if($isDraft)
                                        <button type="button" @click="removeRow(idx)" class="text-red-500 hover:text-red-700" title="{{ __('common.delete') }}">&times;</button>
                                    @endif
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                {{-- Open/Close: JS-built forms appended to body — nesting a <form> inside the
                     outer update form closes the outer one in HTML5 (every input after this
                     point becomes orphaned), so the Save button silently does nothing.
                     Same pattern as _form-action-buttons.blade.php Create Report fix. --}}
                @if($isDraft)
                    <button type="button"
                            onclick="if (confirm('{{ __('common.kpi_cycle_open_confirm') }}')) {
                                const f = document.createElement('form');
                                f.method = 'POST';
                                f.action = '{{ route('settings.kpi-cycles.open', $cycle) }}';
                                const t = document.createElement('input');
                                t.type = 'hidden'; t.name = '_token'; t.value = '{{ csrf_token() }}';
                                f.appendChild(t);
                                document.body.appendChild(f);
                                f.submit();
                            }"
                            class="btn-primary text-sm">{{ __('common.kpi_cycle_open_button') }}</button>
                @elseif($isOpen)
                    <button type="button"
                            onclick="if (confirm('{{ __('common.kpi_cycle_close_confirm') }}')) {
                                const f = document.createElement('form');
                                f.method = 'POST';
                                f.action = '{{ route('settings.kpi-cycles.close', $cycle) }}';
                                const t = document.createElement('input');
                                t.type = 'hidden'; t.name = '_token'; t.value = '{{ csrf_token() }}';
                                f.appendChild(t);
                                document.body.appendChild(f);
                                f.submit();
                            }"
                            class="btn-danger text-sm">{{ __('common.kpi_cycle_close_button') }}</button>
                @endif
                @if($cycle->assignments->isNotEmpty())
                    <a href="{{ route('settings.kpi-cycles.report', $cycle) }}" class="btn-secondary text-sm">
                        {{ __('common.kpi_cycle_report_view_button') }}
                    </a>
                @endif
            </div>
            <button type="submit" class="btn-primary">{{ __('common.save') }}</button>
        </div>
    </form>

    @push('scripts')
    <script>
        function kpiCycleEditor(initialRows, users) {
            return {
                rows: (initialRows || []).map((r) => ({ ...r })),
                users: users || [],
                addRow() {
                    this.rows.push({ target_user_id: '', evaluator_user_id: '', role: 'supervisor', submission_id: null, submission_status: null });
                },
                removeRow(idx) { this.rows.splice(idx, 1); },
            };
        }
    </script>
    @endpush
@endsection
