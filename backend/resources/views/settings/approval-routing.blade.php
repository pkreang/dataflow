@extends('layouts.app')

@section('title', __('common.approval_routing'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.workflow'), 'url' => route('settings.workflow.index')],
        ['label' => __('common.approval_routing')],
    ]" />
@endsection

@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.approval_routing') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('common.approval_routing_subtitle') }}</p>
        </div>
        <a href="{{ route('settings.workflow.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500">&larr; {{ __('common.back') }}</a>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4">
            <p class="text-sm">{{ session('success') }}</p>
        </div>
    @endif

    @if ($departments->isEmpty())
        <div class="card p-6 text-sm text-slate-600 dark:text-slate-400">
            {{ __('common.department_workflow_bindings_no_types') }}
        </div>
    @else
        <div x-data="{
            original: @js($initialBindings),
            current: JSON.parse(JSON.stringify(@js($initialBindings))),
            openDepts: { '{{ $departments->first()?->id }}': true },
            toggle(id) { this.openDepts[id] = !this.openDepts[id] },
            isOpen(id) { return !!this.openDepts[id] },
            isDirty(key) { return this.current[key] !== this.original[key] },
            get dirtyCount() { return Object.keys(this.current).filter(k => this.isDirty(k)).length },
            deptDirtyCount(deptId) {
                return Object.keys(this.current).filter(k => k.startsWith(deptId + '|') && this.isDirty(k)).length;
            },
            reset() { this.current = JSON.parse(JSON.stringify(this.original)) },
            submitForm(formEl) {
                formEl.querySelectorAll('.dynamic-input').forEach(el => el.remove());
                let i = 0;
                Object.keys(this.current).filter(k => this.isDirty(k)).forEach(key => {
                    const sep = key.indexOf('|');
                    const deptId = key.substring(0, sep);
                    const docType = key.substring(sep + 1);
                    const mk = (name, val) => {
                        let inp = document.createElement('input');
                        inp.type = 'hidden'; inp.name = name; inp.value = val;
                        inp.classList.add('dynamic-input');
                        formEl.appendChild(inp);
                    };
                    mk('bindings[' + i + '][department_id]', deptId);
                    mk('bindings[' + i + '][document_type]', docType);
                    mk('bindings[' + i + '][workflow_id]', this.current[key]);
                    i++;
                });
                formEl.submit();
            }
        }">
            <form method="POST" action="{{ route('settings.approval-routing.save') }}"
                  @submit.prevent="submitForm($el)" novalidate>
                @csrf

                {{-- Allow requester override toggle --}}
                <div class="card p-4 mb-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="allow_requester_override" value="1"
                               @checked(old('allow_requester_override', $allowRequesterOverride ?? false)) class="mt-1">
                        <span>
                            <span class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ __('common.approval_allow_requester_override') }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('common.approval_allow_requester_override_desc') }}</span>
                        </span>
                    </label>
                </div>

                {{-- Dept × doc_type → workflow matrix --}}
                <div class="space-y-3">
                    @foreach ($departments as $department)
                        <div class="table-wrapper">
                            <button type="button" @click="toggle('{{ $department->id }}')"
                                    class="w-full flex items-center justify-between px-5 py-3.5 text-left hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors rounded-xl">
                                <div class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-slate-500 dark:text-slate-400 transition-transform duration-200"
                                         :class="isOpen('{{ $department->id }}') && 'rotate-90'"
                                         fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                    </svg>
                                    <div>
                                        <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $department->name }}</span>
                                        <span class="ml-2 text-xs text-slate-500 dark:text-slate-400">({{ $department->code }})</span>
                                    </div>
                                </div>
                                <span x-show="deptDirtyCount('{{ $department->id }}') > 0"
                                      x-text="deptDirtyCount('{{ $department->id }}')"
                                      x-cloak
                                      class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-xs font-bold text-amber-700 bg-amber-100 dark:text-amber-300 dark:bg-amber-900/40 rounded-full">
                                </span>
                            </button>

                            <div x-show="isOpen('{{ $department->id }}')" x-collapse x-cloak>
                                <div class="border-t border-slate-200 dark:border-slate-700 divide-y divide-slate-200 dark:divide-slate-700">
                                    @foreach ($documentTypes as $docType)
                                        @php
                                            $cellKey = $department->id . '|' . $docType;
                                            $options = $workflows->where('document_type', $docType);
                                            $docLabel = $documentTypeLabels[$docType] ?? \Illuminate\Support\Str::headline(str_replace('_', ' ', $docType));
                                        @endphp
                                        <div class="px-5 py-3 flex items-center gap-4 transition-colors duration-150"
                                             :class="isDirty('{{ $cellKey }}') && 'bg-amber-50 dark:bg-amber-900/20'">
                                            <div class="w-44 shrink-0">
                                                <span class="text-sm text-slate-700 dark:text-slate-300">{{ $docLabel }}</span>
                                            </div>
                                            <div class="flex-1 max-w-md">
                                                @if ($options->isEmpty())
                                                    <span class="text-xs text-slate-400 dark:text-slate-500 italic">{{ __('common.no_workflows_for_document_type') }}</span>
                                                @else
                                                    <select x-model="current['{{ $cellKey }}']"
                                                            class="form-input w-full transition-shadow duration-150"
                                                            :class="isDirty('{{ $cellKey }}') && 'ring-2 ring-amber-400 dark:ring-amber-500'">
                                                        <option value="">-- {{ __('common.none') }} --</option>
                                                        @foreach ($options as $workflow)
                                                            <option value="{{ $workflow->id }}">{{ $workflow->name }}</option>
                                                        @endforeach
                                                    </select>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Sticky footer --}}
                <div class="sticky bottom-0 mt-4 flex items-center justify-between p-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm">
                    <div>
                        <span x-show="dirtyCount > 0" class="text-sm text-amber-600 dark:text-amber-400 font-medium">
                            <span x-text="dirtyCount"></span> {{ __('common.changes_pending') }}
                        </span>
                        <span x-show="dirtyCount === 0" class="text-sm text-slate-500 dark:text-slate-400">
                            {{ __('common.no_unsaved_changes') }}
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" @click="reset()" x-show="dirtyCount > 0" x-cloak
                                class="btn-secondary">
                            {{ __('common.reset') }}
                        </button>
                        <button type="submit" class="btn-primary">
                            {{ __('common.save_all') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @endif
@endsection
