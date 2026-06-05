@extends('layouts.app')

@section('title', __('common.edit') . ' ' . __('common.workflow'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.workflow'), 'url' => route('settings.workflow.index')],
        ['label' => __('common.edit')],
    ]" />
@endsection

@section('content')
<div x-data="workflowBuilderEdit({{ Js::from($workflow->stages->map(fn($s) => [
    'step_no' => $s->step_no,
    'name' => $s->name,
    'approver_type' => $s->approver_type,
    'approver_ref' => (string) $s->approver_ref,
    'min_approvals' => $s->min_approvals,
    'require_signature' => (bool) $s->require_signature,
])->values()) }}, {{ Js::from($roles->values()) }}, {{ Js::from($users->values()) }}, {{ Js::from($positions->map(fn($p) => ['id' => (string) $p['id'], 'code' => $p['code'] ?? '', 'label' => $p['label'], 'users' => $p['users'] ?? []])->values()) }}, {{ Js::from([
    'untitled' => __('common.workflow_stage_untitled'),
    'minLabel' => __('common.workflow_preview_min_label'),
    'typeRole' => __('common.workflow_approver_role'),
    'typeUser' => __('common.workflow_approver_user'),
    'typePosition' => __('common.workflow_approver_position'),
    'people' => __('common.people_count'),
    'noUsersInPosition' => __('common.no_users_in_position'),
]) }})">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.edit') }} {{ __('common.workflow') }}</h2>
        <a href="{{ route('settings.workflow.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500">&larr; {{ __('common.back') }}</a>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert-error mb-4">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error mb-4">
            <ul class="list-disc pl-5 text-sm space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 card p-6">
            <form method="POST" action="{{ route('settings.workflow.update', $workflow) }}" class="space-y-5" @submit="return canSubmit()" novalidate>
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">{{ __('common.document_type') }}</label>
                        <select name="document_type" class="form-input mt-1">
                            @foreach(\App\Models\DocumentType::allActive() as $dt)
                                <option value="{{ $dt->code }}" @selected($workflow->document_type === $dt->code)>{{ $dt->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">{{ __('common.name') }}</label>
                        <input name="name" value="{{ $workflow->name }}" required class="form-input mt-1" />
                    </div>
                </div>

                <div>
                    <label class="form-label">{{ __('common.remark') }}</label>
                    <textarea name="description" rows="2" class="form-input mt-1 resize-y">{{ $workflow->description }}</textarea>
                </div>

                <div class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/20 px-4 py-3 space-y-1">
                    <input type="hidden" name="allow_requester_as_approver" value="0">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="allow_requester_as_approver" value="1" @checked(old('allow_requester_as_approver', $workflow->allow_requester_as_approver ?? true)) class="mt-1 rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500">
                        <span>
                            <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('common.workflow_allow_requester_as_approver') }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('common.workflow_allow_requester_as_approver_help') }}</span>
                        </span>
                    </label>
                </div>

                <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed rounded-lg border border-blue-200/80 dark:border-blue-800/80 bg-blue-50/90 dark:bg-blue-950/30 px-3 py-2">
                    {{ __('common.workflow_stage_assignment_help') }}
                </p>

                <div class="flex items-center gap-2">
                    <button type="button" @click="addStage()" class="btn-primary text-sm">+ {{ __('common.workflow_add_stage') }}</button>
                </div>

                <template x-for="(stage, idx) in stages" :key="stage.uuid">
                    <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/20 p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="font-medium text-slate-800 dark:text-slate-200">{{ __('common.workflow_step_short') }} <span x-text="stage.step_no"></span></h3>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="moveUp(idx)" class="btn-secondary text-xs px-2 py-1">{{ __('common.move_up') }}</button>
                                <button type="button" @click="moveDown(idx)" class="btn-secondary text-xs px-2 py-1">{{ __('common.move_down') }}</button>
                                <button type="button" @click="cloneStage(idx)" class="btn-secondary text-xs px-2 py-1">{{ __('common.workflow_clone') }}</button>
                                <button type="button" @click="removeStage(idx)" class="btn-danger text-xs px-2 py-1">{{ __('common.delete') }}</button>
                            </div>
                        </div>
                        <input type="hidden" :name="`stages[${idx}][step_no]`" x-model="stage.step_no">
                        <div class="grid gap-3 items-end" style="grid-template-columns: 1fr 100px 2fr 60px">
                            <div>
                                <label class="text-xs text-slate-500">{{ __('common.workflow_stage_name') }}</label>
                                <input :name="`stages[${idx}][name]`" x-model="stage.name" @input="checkValidity()" required class="form-input mt-1" />
                            </div>
                            <div>
                                <label class="text-xs text-slate-500">{{ __('common.workflow_approver_type') }}</label>
                                <select :name="`stages[${idx}][approver_type]`" x-model="stage.approver_type" @change="stage.approver_ref=''; checkValidity()" class="form-input mt-1">
                                    <option value="position">{{ __('common.workflow_approver_position') }}</option>
                                    <option value="user">{{ __('common.workflow_approver_user') }}</option>
                                    <option value="role">{{ __('common.workflow_approver_role') }}</option>
                                    @if(($allowRequesterPick ?? false) || $workflow->stages->contains('approver_type', 'requester_pick'))
                                        <option value="requester_pick">{{ __('common.workflow_approver_requester_pick') }}</option>
                                    @endif
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-slate-500">{{ __('common.workflow_approver_ref') }}</label>
                                <template x-if="stage.approver_type === 'role'">
                                    <select :name="`stages[${idx}][approver_ref]`" x-model="stage.approver_ref" @change="checkValidity()" required class="form-input mt-1">
                                        <option value="">{{ __('common.workflow_placeholder_select_role') }}</option>
                                        <template x-for="role in roles" :key="`role-${role}`">
                                            <option :value="role" x-text="role"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="stage.approver_type === 'user'">
                                    <select :name="`stages[${idx}][approver_ref]`" x-model="stage.approver_ref" @change="checkValidity()" required class="form-input mt-1">
                                        <option value="">{{ __('common.workflow_placeholder_select_user') }}</option>
                                        <template x-for="user in users" :key="`user-${user.id}`">
                                            <option :value="String(user.id)" x-text="user.label"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="stage.approver_type === 'position'">
                                    <div>
                                        <select :name="`stages[${idx}][approver_ref]`" x-model="stage.approver_ref" @change="checkValidity()" required class="form-input mt-1">
                                            <option value="">{{ __('common.workflow_placeholder_select_position') }}</option>
                                            <template x-for="p in positions" :key="`pos-${p.id}`">
                                                <option :value="String(p.id)" x-text="p.label"></option>
                                            </template>
                                        </select>
                                        <p x-show="stage.approver_ref" x-text="positionUsersPreview(stage.approver_ref)" class="text-xs text-slate-500 dark:text-slate-400 mt-1"></p>
                                    </div>
                                </template>
                                <template x-if="stage.approver_type === 'requester_pick'">
                                    <p class="text-xs text-blue-700 dark:text-blue-300 mt-1 bg-blue-50 dark:bg-blue-900/10 rounded p-2">
                                        {{ __('common.workflow_requester_pick_hint') }}
                                    </p>
                                </template>
                            </div>
                            <div>
                                <label class="text-xs text-slate-500" title="{{ __('common.workflow_min_approvals') }}">ขั้นต่ำ</label>
                                <input type="number" min="1" :name="`stages[${idx}][min_approvals]`" x-model="stage.min_approvals" @input="checkValidity()" required class="form-input mt-1 text-center" />
                            </div>
                        </div>
                        <div class="flex items-center gap-2 pt-1">
                            <label class="inline-flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                                <input type="checkbox" :name="`stages[${idx}][require_signature]`" value="1"
                                       :checked="!!stage.require_signature"
                                       @change="stage.require_signature = $event.target.checked"
                                       class="rounded border-slate-300 dark:border-slate-600 dark:bg-slate-700">
                                <span>{{ __('common.workflow_require_signature') }}</span>
                            </label>
                            <span class="text-xs text-slate-400">{{ __('common.workflow_require_signature_help') }}</span>
                        </div>
                    </div>
                </template>

                <div class="flex items-center justify-end pt-2">
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('settings.workflow.index') }}" class="btn-secondary">{{ __('common.cancel') }}</a>
                        <button :disabled="!isValid" :class="isValid ? '' : 'opacity-50 cursor-not-allowed'" class="btn-primary">{{ __('common.save') }}</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="xl:col-span-1 card p-5">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-3">{{ __('common.workflow_flow_preview') }}</h3>
            <div class="space-y-2">
                <template x-for="stage in stages" :key="stage.uuid + '-preview'">
                    <div class="rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/20 px-3 py-2 text-sm">
                        <span class="font-medium text-slate-800 dark:text-slate-200">#<span x-text="stage.step_no"></span> <span x-text="stage.name || i18n.untitled"></span></span>
                        <div class="text-xs text-slate-500 mt-1"><span x-text="approverTypeLabel(stage.approver_type)"></span>: <span x-text="stage.approver_ref || '-'"></span> | <span x-text="i18n.minLabel"></span> <span x-text="stage.min_approvals"></span></div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
    function workflowBuilderEdit(initialStages, roles, users, positions, i18n) {
        return {
            roles: roles || [],
            users: users || [],
            positions: positions || [],
            i18n: i18n || { untitled: '', minLabel: 'min.', typeRole: 'Role', typeUser: 'User', typePosition: 'Position' },
            stages: [],
            seq: 1,
            isValid: true,
            init() {
                this.stages = (initialStages || []).map((s) => ({
                    uuid: this.seq++,
                    step_no: Number(s.step_no || 1),
                    name: s.name || '',
                    approver_type: s.approver_type ?? 'position',
                    approver_ref: String(s.approver_ref || ''),
                    min_approvals: Number(s.min_approvals || 1),
                    require_signature: !!s.require_signature,
                }));
                if (!this.stages.length) this.addStage();
                this.normalize();
                this.checkValidity();
                // Force sync select values after x-if templates render
                this.$nextTick(() => {
                    setTimeout(() => {
                        this.$el.querySelectorAll('select[name*="approver_ref"]').forEach((sel, i) => {
                            if (this.stages[i]) sel.value = this.stages[i].approver_ref;
                        });
                    }, 50);
                });
            },
            addStage() {
                const approver_type = this.positions[0] ? 'position' : (this.users[0] ? 'user' : 'role');
                let approver_ref = '';
                if (approver_type === 'position' && this.positions[0]) approver_ref = String(this.positions[0].id);
                else if (approver_type === 'user' && this.users[0]) approver_ref = String(this.users[0].id);
                this.stages.push({uuid: this.seq++, step_no: this.stages.length + 1, name: '', approver_type, approver_ref, min_approvals: 1, require_signature: false});
                this.normalize();
                this.checkValidity();
            },
            removeStage(idx) {
                this.stages.splice(idx, 1);
                if (!this.stages.length) this.addStage();
                this.normalize();
                this.checkValidity();
            },
            moveUp(idx) {
                if (idx <= 0) return;
                [this.stages[idx - 1], this.stages[idx]] = [this.stages[idx], this.stages[idx - 1]];
                this.normalize();
                this.checkValidity();
            },
            moveDown(idx) {
                if (idx >= this.stages.length - 1) return;
                [this.stages[idx + 1], this.stages[idx]] = [this.stages[idx], this.stages[idx + 1]];
                this.normalize();
                this.checkValidity();
            },
            cloneStage(idx) {
                const s = this.stages[idx];
                this.stages.splice(idx + 1, 0, {uuid: this.seq++, step_no: s.step_no, name: s.name, approver_type: s.approver_type, approver_ref: s.approver_ref, min_approvals: s.min_approvals, require_signature: !!s.require_signature});
                this.normalize();
                this.checkValidity();
            },
            normalize() {
                this.stages.forEach((s, i) => s.step_no = i + 1);
            },
            checkValidity() {
                this.isValid = this.stages.length > 0 && this.stages.every((s) =>
                    String(s.name || '').trim() !== '' &&
                    // requester_pick has no fixed approver_ref — chosen at submit time
                    (s.approver_type === 'requester_pick' || String(s.approver_ref || '').trim() !== '') &&
                    Number(s.min_approvals || 0) >= 1
                );
            },
            canSubmit() {
                this.checkValidity();
                return this.isValid;
            },
            approverTypeLabel(t) {
                const i = this.i18n;
                if (t === 'role') return i.typeRole;
                if (t === 'user') return i.typeUser;
                if (t === 'position') return i.typePosition;
                return t || '—';
            },
            positionUsersPreview(positionId) {
                const pos = this.positions.find(p => String(p.id) === String(positionId));
                if (!pos) return '';
                const users = pos.users || [];
                if (users.length === 0) return this.i18n.noUsersInPosition || '';
                const count = users.length + ' ' + (this.i18n.people || '');
                const preview = users.length > 5 ? users.slice(0, 5).join(', ') + '…' : users.join(', ');
                return count + ': ' + preview;
            },
        };
    }
</script>
@endsection
