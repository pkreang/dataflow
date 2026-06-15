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

    @if ($errors->any())
        <div class="alert-error mb-4">
            <ul class="text-sm space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($forms->isEmpty())
        <div class="card p-6 text-sm text-slate-600 dark:text-slate-400">
            {{ __('common.approval_routing_no_forms') }}
        </div>
    @else
        <div x-data="{
            original: @js($initialState),
            state: JSON.parse(JSON.stringify(@js($initialState))),
            deptOptions: @js($departments->map(fn ($d) => ['id' => (string) $d->id, 'name' => $d->name])->values()),
            posOptions: @js($positions->map(fn ($p) => ['id' => (string) $p->id, 'name' => $p->name])->values()),
            deletedIds: [],
            isDirty() {
                return JSON.stringify(this.state) !== JSON.stringify(this.original) || this.deletedIds.length > 0;
            },
            get dirtyCount() {
                let n = this.deletedIds.length;
                Object.keys(this.state).forEach(fid => {
                    if (this.state[fid].default.workflowId !== this.original[fid].default.workflowId) n++;
                    this.state[fid].exceptions.forEach((ex, i) => {
                        const orig = this.original[fid].exceptions[i];
                        if (!orig || JSON.stringify(ex) !== JSON.stringify(orig)) n++;
                    });
                });
                return n;
            },
            hasDuplicate(fid) {
                const seen = new Set();
                for (const ex of this.state[fid].exceptions) {
                    if (ex.advanced || ex.scope === 'both') continue;
                    const key = ex.scope + ':' + (ex.scope === 'department' ? ex.targetDeptId : ex.targetPosId);
                    if ((ex.targetDeptId || ex.targetPosId) && seen.has(key)) return true;
                    seen.add(key);
                }
                return false;
            },
            get anyDuplicate() { return Object.keys(this.state).some(fid => this.hasDuplicate(fid)); },
            addException(fid) {
                this.state[fid].exceptions.push({
                    id: null, scope: 'department', targetDeptId: '', targetPosId: '', workflowId: '', advanced: false,
                });
            },
            removeException(fid, idx) {
                const ex = this.state[fid].exceptions[idx];
                if (ex.id) this.deletedIds.push(ex.id);
                this.state[fid].exceptions.splice(idx, 1);
            },
            reset() {
                this.state = JSON.parse(JSON.stringify(this.original));
                this.deletedIds = [];
            },
            submitForm(formEl) {
                formEl.querySelectorAll('.dynamic-input').forEach(el => el.remove());
                const mk = (name, val) => {
                    let inp = document.createElement('input');
                    inp.type = 'hidden'; inp.name = name; inp.value = val;
                    inp.classList.add('dynamic-input');
                    formEl.appendChild(inp);
                };
                let i = 0;
                Object.keys(this.state).forEach(fid => {
                    if (this.state[fid].default.workflowId !== this.original[fid].default.workflowId) {
                        mk('defaults[' + fid + ']', this.state[fid].default.workflowId);
                    }
                    this.state[fid].exceptions.forEach(ex => {
                        if (ex.advanced || ex.scope === 'both') return;
                        const target = ex.scope === 'department' ? ex.targetDeptId : ex.targetPosId;
                        if (!target || !ex.workflowId) return;
                        mk('exceptions[' + i + '][form_id]', fid);
                        mk('exceptions[' + i + '][scope]', ex.scope);
                        if (ex.scope === 'department') mk('exceptions[' + i + '][department_id]', ex.targetDeptId);
                        if (ex.scope === 'position') mk('exceptions[' + i + '][position_id]', ex.targetPosId);
                        mk('exceptions[' + i + '][workflow_id]', ex.workflowId);
                        i++;
                    });
                });
                this.deletedIds.forEach(id => mk('deleted_policy_ids[]', id));
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

                {{-- Resolution order hint --}}
                <div class="rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 px-4 py-3 mb-4">
                    <p class="text-xs text-blue-800 dark:text-blue-200">{{ __('common.approval_routing_priority_hint') }}</p>
                </div>

                {{-- Cards per form, grouped by document type --}}
                <div class="space-y-6">
                    @foreach ($formGroups as $docType => $formsInGroup)
                        @php
                            $docLabel = $documentTypeLabels[$docType] ?? \Illuminate\Support\Str::headline(str_replace('_', ' ', $docType));
                            $typeWorkflows = $workflowsByType->get($docType, collect());
                        @endphp
                        <div>
                            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">{{ $docLabel }}</h3>
                            <div class="space-y-3">
                                @foreach ($formsInGroup as $form)
                                    @php $fid = (string) $form->id; @endphp
                                    <div class="card p-5">
                                        <div class="flex items-center justify-between mb-4">
                                            <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $form->name }}</span>
                                            <a href="{{ route('settings.document-forms.policy.edit', $form) }}"
                                               class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-500">
                                                {{ __('common.approval_routing_advanced_link') }}
                                            </a>
                                        </div>

                                        {{-- Default workflow --}}
                                        <div class="flex items-center gap-4 mb-4">
                                            <span class="w-44 shrink-0 text-sm text-slate-700 dark:text-slate-300">{{ __('common.approval_routing_default_workflow') }}</span>
                                            <div class="flex-1 max-w-md">
                                                @if ($initialState[$fid]['default']['advanced'])
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">
                                                        {{ __('common.approval_routing_advanced_badge') }}
                                                    </span>
                                                @elseif ($typeWorkflows->isEmpty())
                                                    <span class="text-xs text-slate-400 dark:text-slate-500 italic">{{ __('common.no_workflows_for_document_type') }}</span>
                                                @else
                                                    <select x-model="state['{{ $fid }}'].default.workflowId"
                                                            class="form-input w-full transition-shadow duration-150"
                                                            :class="state['{{ $fid }}'].default.workflowId !== original['{{ $fid }}'].default.workflowId && 'ring-2 ring-amber-400 dark:ring-amber-500'">
                                                        <option value="">{{ __('common.approval_routing_default_not_set') }}</option>
                                                        @foreach ($typeWorkflows as $workflow)
                                                            <option value="{{ $workflow->id }}">{{ $workflow->name }}</option>
                                                        @endforeach
                                                    </select>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Exceptions --}}
                                        <div class="border-t border-slate-100 dark:border-slate-700 pt-4">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">{{ __('common.approval_routing_exceptions') }}</span>
                                            </div>

                                            <p x-show="state['{{ $fid }}'].exceptions.length === 0" x-cloak
                                               class="text-xs text-slate-400 dark:text-slate-500 italic mb-2">
                                                {{ __('common.approval_routing_no_exceptions') }}
                                            </p>

                                            <div class="space-y-2">
                                                <template x-for="(ex, idx) in state['{{ $fid }}'].exceptions" :key="idx">
                                                    <div>
                                                        {{-- Advanced / combo row: read-only --}}
                                                        <template x-if="ex.advanced || ex.scope === 'both'">
                                                            <div class="flex items-center gap-3 px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-800/60">
                                                                <span class="text-xs text-slate-600 dark:text-slate-300"
                                                                      x-text="(ex.targetDeptId ? deptOptions.find(d => d.id === ex.targetDeptId)?.name : '') + (ex.targetDeptId && ex.targetPosId ? ' + ' : '') + (ex.targetPosId ? posOptions.find(p => p.id === ex.targetPosId)?.name : '')"></span>
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">
                                                                    {{ __('common.approval_routing_advanced_badge') }}
                                                                </span>
                                                                <a href="{{ route('settings.document-forms.policy.edit', $form) }}"
                                                                   class="ml-auto text-xs text-blue-600 dark:text-blue-400 hover:text-blue-500">
                                                                    {{ __('common.approval_routing_advanced_link') }}
                                                                </a>
                                                            </div>
                                                        </template>

                                                        {{-- Editable row --}}
                                                        <template x-if="!ex.advanced && ex.scope !== 'both'">
                                                            <div class="flex flex-wrap items-center gap-2">
                                                                <select x-model="ex.scope" class="form-input w-32">
                                                                    <option value="department">{{ __('common.approval_routing_scope_department') }}</option>
                                                                    <option value="position">{{ __('common.approval_routing_scope_position') }}</option>
                                                                </select>

                                                                <select x-show="ex.scope === 'department'" x-model="ex.targetDeptId" class="form-input flex-1 min-w-40">
                                                                    <option value="">{{ __('common.approval_routing_select_target') }}</option>
                                                                    <template x-for="d in deptOptions" :key="d.id">
                                                                        <option :value="d.id" x-text="d.name" :selected="d.id === ex.targetDeptId"></option>
                                                                    </template>
                                                                </select>
                                                                <select x-show="ex.scope === 'position'" x-cloak x-model="ex.targetPosId" class="form-input flex-1 min-w-40">
                                                                    <option value="">{{ __('common.approval_routing_select_target') }}</option>
                                                                    <template x-for="p in posOptions" :key="p.id">
                                                                        <option :value="p.id" x-text="p.name" :selected="p.id === ex.targetPosId"></option>
                                                                    </template>
                                                                </select>

                                                                <span class="text-slate-400">&rarr;</span>

                                                                <select x-model="ex.workflowId" class="form-input flex-1 min-w-44">
                                                                    <option value="">{{ __('common.approval_routing_select_target') }}</option>
                                                                    @foreach ($typeWorkflows as $workflow)
                                                                        <option value="{{ $workflow->id }}">{{ $workflow->name }}</option>
                                                                    @endforeach
                                                                </select>

                                                                <button type="button" @click="removeException('{{ $fid }}', idx)"
                                                                        class="text-xs text-red-600 dark:text-red-400 hover:text-red-500 px-2 py-1">
                                                                    {{ __('common.approval_routing_remove_exception') }}
                                                                </button>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>

                                            <p x-show="hasDuplicate('{{ $fid }}')" x-cloak
                                               class="text-xs text-red-600 dark:text-red-400 mt-2">
                                                {{ __('common.approval_routing_duplicate_exception') }}
                                            </p>

                                            @if ($typeWorkflows->isNotEmpty())
                                                <button type="button" @click="addException('{{ $fid }}')"
                                                        class="mt-3 text-xs text-blue-600 dark:text-blue-400 hover:text-blue-500 font-medium">
                                                    + {{ __('common.approval_routing_add_exception') }}
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
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
                        <button type="submit" class="btn-primary" :disabled="anyDuplicate"
                                :class="anyDuplicate && 'opacity-50 cursor-not-allowed'">
                            {{ __('common.save_all') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @endif
@endsection
