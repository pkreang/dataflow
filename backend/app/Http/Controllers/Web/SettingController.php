<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ApprovalWorkflow;
use App\Models\Department;
use App\Models\DocumentForm;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\DocumentType;
use App\Models\Position;
use App\Models\Setting;
use App\Services\Auth\AuthModeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingController extends Controller
{
    private const SYSTEM_DISK = 'public';

    private const SYSTEM_PATH = 'system';

    /**
     * Branding / logo & background config.
     */
    public function branding(): View
    {
        $systemLogo = Setting::get('system_logo');
        $loginBackground = Setting::get('login_background');
        $loginBackgroundColor = Setting::get('login_background_color', '#2563eb');
        $loginIllustration = Setting::get('login_illustration');

        return view('settings.branding', compact('systemLogo', 'loginBackground', 'loginBackgroundColor', 'loginIllustration'));
    }

    /**
     * Save branding (logo, login background).
     */
    public function saveBranding(Request $request): RedirectResponse
    {
        $request->validate([
            'system_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
            'login_background' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'login_illustration' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'login_background_color' => 'nullable|string|max:20',
        ], [], [
            'system_logo' => __('branding.system_logo'),
            'login_background' => __('branding.login_background'),
            'login_illustration' => __('branding.login_illustration'),
            'login_background_color' => __('branding.login_background_color'),
        ]);

        if ($request->hasFile('system_logo')) {
            $old = Setting::get('system_logo');
            if ($old) {
                Storage::disk(self::SYSTEM_DISK)->delete($old);
            }
            $path = $request->file('system_logo')->store(self::SYSTEM_PATH, self::SYSTEM_DISK);
            Setting::set('system_logo', $path);
        }

        if ($request->hasFile('login_background')) {
            $old = Setting::get('login_background');
            if ($old) {
                Storage::disk(self::SYSTEM_DISK)->delete($old);
            }
            $path = $request->file('login_background')->store(self::SYSTEM_PATH, self::SYSTEM_DISK);
            Setting::set('login_background', $path);
        }

        if ($request->hasFile('login_illustration')) {
            $old = Setting::get('login_illustration');
            if ($old) {
                Storage::disk(self::SYSTEM_DISK)->delete($old);
            }
            $path = $request->file('login_illustration')->store(self::SYSTEM_PATH, self::SYSTEM_DISK);
            Setting::set('login_illustration', $path);
        }

        if ($request->filled('login_background_color')) {
            Setting::set('login_background_color', $request->input('login_background_color'));
        }

        if ($request->boolean('remove_system_logo')) {
            $old = Setting::get('system_logo');
            if ($old) {
                Storage::disk(self::SYSTEM_DISK)->delete($old);
            }
            Setting::set('system_logo', '');
        }

        if ($request->boolean('remove_login_background')) {
            $old = Setting::get('login_background');
            if ($old) {
                Storage::disk(self::SYSTEM_DISK)->delete($old);
            }
            Setting::set('login_background', '');
        }

        if ($request->boolean('remove_login_illustration')) {
            $old = Setting::get('login_illustration');
            if ($old) {
                Storage::disk(self::SYSTEM_DISK)->delete($old);
            }
            Setting::set('login_illustration', '');
        }

        return redirect()->route('settings.branding')->with('success', __('branding.saved'));
    }

    private array $policyKeys = [
        'password_min_length',
        'password_max_length',
        'password_require_uppercase',
        'password_require_lowercase',
        'password_require_number',
        'password_require_special',
        'password_expires_days',
        'password_force_change_first_login',
        'password_prevent_reuse',
        'lockout_max_attempts',
        'lockout_duration_minutes',
    ];

    public function passwordPolicy(): View
    {
        $settings = [];
        foreach ($this->policyKeys as $key) {
            $settings[$key] = Setting::get($key);
        }

        return view('settings.password-policy', compact('settings'));
    }

    public function savePasswordPolicy(Request $request)
    {
        $request->validate([
            'password_min_length' => 'required|integer|min:1|max:128',
            'password_max_length' => 'required|integer|min:1|max:255',
            'password_expires_days' => 'required|integer|min:0|max:365',
            'password_prevent_reuse' => 'required|integer|min:0|max:24',
            'lockout_max_attempts' => 'required|integer|min:0|max:100',
            'lockout_duration_minutes' => 'required|integer|min:0|max:1440',
        ]);

        $boolKeys = [
            'password_require_uppercase',
            'password_require_lowercase',
            'password_require_number',
            'password_require_special',
            'password_force_change_first_login',
        ];

        foreach ($this->policyKeys as $key) {
            if (in_array($key, $boolKeys)) {
                Setting::set($key, $request->boolean($key) ? '1' : '0');
            } else {
                Setting::set($key, $request->input($key, '0'));
            }
        }

        return redirect()->route('settings.password-policy')
            ->with('success', __('password_policy.saved'));
    }

    /**
     * Default workflow per form + department/position exceptions
     * (reads/writes DocumentFormWorkflowPolicy — the primary routing layer).
     */
    public function approvalRouting(): View
    {
        $allowRequesterOverride = Setting::getBool('approval.allow_requester_override', false);
        $forms = DocumentForm::query()->where('is_active', true)->orderBy('name')->get();
        $formGroups = $forms->groupBy('document_type');
        $workflowsByType = ApprovalWorkflow::query()->where('is_active', true)
            ->orderBy('name')->get()->groupBy('document_type');
        $departments = Department::query()->where('is_active', true)->orderBy('name')->get();
        $orgUnits = \App\Models\OrgUnit::query()->where('is_active', true)->orderBy('name')->get();
        $positions = Position::query()->where('is_active', true)->orderBy('name')->get();

        $documentTypeLabels = DocumentType::allActive()
            ->mapWithKeys(fn ($dt) => [$dt->code => $dt->label()])
            ->toArray();

        $policies = DocumentFormWorkflowPolicy::query()->with('ranges')
            ->whereIn('form_id', $forms->pluck('id'))
            ->get()
            ->groupBy('form_id');

        $initialState = [];
        foreach ($forms as $form) {
            $state = [
                'default' => ['workflowId' => '', 'advanced' => false, 'policyId' => null],
                'exceptions' => [],
            ];
            foreach ($policies->get($form->id, collect()) as $policy) {
                $advanced = $this->policyIsAdvanced($policy);
                if ($policy->department_id === null && $policy->org_unit_id === null && $policy->position_id === null) {
                    $state['default'] = [
                        'workflowId' => $advanced ? '' : (string) ($policy->workflow_id ?? ''),
                        'advanced' => $advanced,
                        'policyId' => $policy->id,
                    ];
                } else {
                    $scope = match (true) {
                        $policy->position_id !== null => 'position',
                        $policy->org_unit_id !== null => 'org_unit',
                        default => 'department',
                    };
                    $state['exceptions'][] = [
                        'id' => $policy->id,
                        'scope' => $scope,
                        'targetDeptId' => $policy->department_id ? (string) $policy->department_id : '',
                        'targetOrgUnitId' => $policy->org_unit_id ? (string) $policy->org_unit_id : '',
                        'targetPosId' => $policy->position_id ? (string) $policy->position_id : '',
                        'workflowId' => (string) ($policy->workflow_id ?? ''),
                        'advanced' => $advanced,
                    ];
                }
            }
            $initialState[(string) $form->id] = $state;
        }

        return view('settings.approval-routing', compact(
            'allowRequesterOverride',
            'forms',
            'formGroups',
            'workflowsByType',
            'departments',
            'orgUnits',
            'positions',
            'documentTypeLabels',
            'initialState'
        ));
    }

    public function saveApprovalRouting(Request $request): RedirectResponse
    {
        $request->validate([
            'allow_requester_override' => 'nullable|boolean',
            'defaults' => 'nullable|array',
            'defaults.*' => 'nullable|string',
            'exceptions' => 'nullable|array',
            'exceptions.*.form_id' => 'required|integer|exists:document_forms,id',
            'exceptions.*.scope' => 'required|in:department,position,org_unit',
            'exceptions.*.department_id' => 'required_if:exceptions.*.scope,department|nullable|integer|exists:departments,id',
            'exceptions.*.org_unit_id' => 'required_if:exceptions.*.scope,org_unit|nullable|integer|exists:org_units,id',
            'exceptions.*.position_id' => 'required_if:exceptions.*.scope,position|nullable|integer|exists:positions,id',
            'exceptions.*.workflow_id' => 'required|integer|exists:approval_workflows,id',
            'deleted_policy_ids' => 'nullable|array',
            'deleted_policy_ids.*' => 'integer',
        ]);

        $errors = [];

        DB::transaction(function () use ($request, &$errors) {
            // 1. Defaults: form_id => workflow_id|'' (global policy row)
            foreach ($request->input('defaults', []) as $formId => $workflowId) {
                $form = DocumentForm::find((int) $formId);
                if (! $form) {
                    continue;
                }

                $existing = DocumentFormWorkflowPolicy::query()->with('ranges')
                    ->where('form_id', $form->id)
                    ->whereNull('department_id')
                    ->whereNull('org_unit_id')
                    ->whereNull('position_id')
                    ->first();

                if ($existing && $this->policyIsAdvanced($existing)) {
                    $errors["defaults.{$formId}"] = __('common.approval_routing_advanced_locked');

                    continue;
                }

                if ($workflowId === '' || $workflowId === null) {
                    $existing?->delete();

                    continue;
                }

                $workflow = ApprovalWorkflow::find((int) $workflowId);
                if (! $workflow || $workflow->document_type !== $form->document_type) {
                    continue;
                }

                DocumentFormWorkflowPolicy::updateOrCreate(
                    ['form_id' => $form->id, 'department_id' => null, 'org_unit_id' => null, 'position_id' => null],
                    ['workflow_id' => (int) $workflowId, 'use_amount_condition' => false]
                );
            }

            // 2. Exceptions: dept-only or position-only policy rows
            foreach ($request->input('exceptions', []) as $i => $entry) {
                $form = DocumentForm::find((int) $entry['form_id']);
                if (! $form) {
                    continue;
                }

                $deptId = $entry['scope'] === 'department' ? (int) $entry['department_id'] : null;
                $orgUnitId = $entry['scope'] === 'org_unit' ? (int) $entry['org_unit_id'] : null;
                $posId = $entry['scope'] === 'position' ? (int) $entry['position_id'] : null;

                $existing = DocumentFormWorkflowPolicy::query()->with('ranges')
                    ->where('form_id', $form->id)
                    ->where('department_id', $deptId)
                    ->where('org_unit_id', $orgUnitId)
                    ->where('position_id', $posId)
                    ->first();

                if ($existing && $this->policyIsAdvanced($existing)) {
                    $errors["exceptions.{$i}"] = __('common.approval_routing_advanced_locked');

                    continue;
                }

                $workflow = ApprovalWorkflow::find((int) $entry['workflow_id']);
                if (! $workflow || $workflow->document_type !== $form->document_type) {
                    continue;
                }

                DocumentFormWorkflowPolicy::updateOrCreate(
                    ['form_id' => $form->id, 'department_id' => $deptId, 'org_unit_id' => $orgUnitId, 'position_id' => $posId],
                    ['workflow_id' => $workflow->id, 'use_amount_condition' => false]
                );
            }

            // 3. Deletions: only simple rows belonging to active forms
            $deleteIds = array_map('intval', $request->input('deleted_policy_ids', []));
            if ($deleteIds !== []) {
                $candidates = DocumentFormWorkflowPolicy::query()->with('ranges')
                    ->whereIn('id', $deleteIds)
                    ->whereIn('form_id', DocumentForm::query()->where('is_active', true)->pluck('id'))
                    ->get();
                foreach ($candidates as $policy) {
                    if ($this->policyIsAdvanced($policy)) {
                        $errors["deleted.{$policy->id}"] = __('common.approval_routing_advanced_locked');

                        continue;
                    }
                    $policy->delete();
                }
            }
        });

        Setting::set('approval.allow_requester_override', $request->boolean('allow_requester_override'));

        if ($errors !== []) {
            return redirect()
                ->route('settings.approval-routing')
                ->withErrors($errors);
        }

        return redirect()
            ->route('settings.approval-routing')
            ->with('success', __('common.saved'));
    }

    private function policyIsAdvanced(DocumentFormWorkflowPolicy $policy): bool
    {
        return $policy->use_amount_condition
            || ! empty($policy->field_conditions)
            || $policy->ranges->isNotEmpty();
    }

    private array $authSettingKeys = [
        'auth_local_enabled',
        'auth_entra_enabled',
        'auth_ldap_enabled',
        'auth_local_super_admin_only',
        'auth_default_role',
        'entra_tenant_id',
        'entra_client_id',
        'ldap_host',
        'ldap_port',
        'ldap_base_dn',
        'ldap_bind_dn',
        'ldap_user_filter',
        'ldap_use_tls',
        'ldap_user_create_validation',
        'auth_password_help_url',
        'auth_directory_group_role_map',
    ];

    public function authSettings(): View
    {
        $settings = [];
        foreach ($this->authSettingKeys as $key) {
            $settings[$key] = Setting::get($key);
        }

        $mapRaw = (string) ($settings['auth_directory_group_role_map'] ?? '[]');
        $mapDecoded = json_decode($mapRaw, true);
        $settings['auth_directory_group_role_map'] = is_array($mapDecoded)
            ? json_encode($mapDecoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : '[]';

        $roles = \Spatie\Permission\Models\Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['name', 'display_name']);

        return view('settings.auth', [
            'settings' => $settings,
            'roles' => $roles,
            'entraEnvOk' => (bool) config('services.entra.client_secret'),
            'ldapEnvOk' => (bool) config('services.ldap.bind_password'),
        ]);
    }

    public function saveAuthSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'auth_default_role' => [
                'required',
                'string',
                'max:64',
                Rule::exists('roles', 'name')->where('guard_name', 'web'),
            ],
            'ldap_port' => 'required|integer|min:1|max:65535',
            'ldap_user_filter' => 'nullable|string|max:512',
            'ldap_user_create_validation' => 'required|in:disabled,required',
            'entra_tenant_id' => 'nullable|string|max:128',
            'entra_client_id' => 'nullable|string|max:128',
            'ldap_host' => 'nullable|string|max:255',
            'ldap_base_dn' => 'nullable|string|max:512',
            'ldap_bind_dn' => 'nullable|string|max:512',
            'auth_password_help_url' => [
                'nullable',
                'string',
                'max:2000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (! filter_var((string) $value, FILTER_VALIDATE_URL)) {
                        $fail(__('validation.url', ['attribute' => __('auth.settings_password_help_url_label')]));
                    }
                },
            ],
            'auth_directory_group_role_map' => [
                'nullable',
                'string',
                'max:65535',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $raw = trim((string) ($value ?? ''));
                    if ($raw === '') {
                        return;
                    }
                    $decoded = json_decode($raw, true);
                    if (! is_array($decoded)) {
                        $fail(__('auth.settings_group_role_map_invalid'));

                        return;
                    }
                    $roleNames = \Spatie\Permission\Models\Role::query()
                        ->where('guard_name', 'web')
                        ->pluck('name')
                        ->all();
                    $allowed = array_flip($roleNames);
                    foreach ($decoded as $i => $row) {
                        if (! is_array($row)) {
                            $fail(__('auth.settings_group_role_map_invalid'));

                            return;
                        }
                        $pattern = isset($row['pattern']) ? trim((string) $row['pattern']) : '';
                        $role = isset($row['role']) ? trim((string) $row['role']) : '';
                        if ($pattern === '' || $role === '') {
                            $fail(__('auth.settings_group_role_map_invalid'));

                            return;
                        }
                        if (strlen($pattern) > 2048 || strlen($role) > 64) {
                            $fail(__('auth.settings_group_role_map_invalid'));

                            return;
                        }
                        if (! isset($allowed[$role])) {
                            $fail(__('auth.settings_group_role_unknown', ['role' => $role]));

                            return;
                        }
                    }
                },
            ],
        ]);

        if ($request->input('ldap_user_create_validation', 'disabled') === 'required') {
            if (! $request->boolean('auth_ldap_enabled') || ! extension_loaded('ldap')) {
                return redirect()
                    ->route('settings.auth')
                    ->withErrors(['auth' => __('auth.settings_ldap_validation_requires_ldap')])
                    ->withInput();
            }
            $ldapHost = trim((string) $request->input('ldap_host', ''));
            $ldapBase = trim((string) $request->input('ldap_base_dn', ''));
            $ldapBind = trim((string) $request->input('ldap_bind_dn', ''));
            $ldapSecret = (string) config('services.ldap.bind_password', '');
            if ($ldapHost === '' || $ldapBase === '' || $ldapBind === '' || $ldapSecret === '') {
                return redirect()
                    ->route('settings.auth')
                    ->withErrors(['auth' => __('auth.settings_ldap_validation_requires_ldap')])
                    ->withInput();
            }
        }

        $boolKeys = [
            'auth_local_enabled',
            'auth_entra_enabled',
            'auth_ldap_enabled',
            'auth_local_super_admin_only',
            'ldap_use_tls',
        ];

        foreach ($boolKeys as $key) {
            Setting::set($key, $request->boolean($key) ? '1' : '0');
        }

        Setting::set('auth_default_role', $request->input('auth_default_role', 'employee'));
        Setting::set('entra_tenant_id', trim((string) $request->input('entra_tenant_id', '')));
        Setting::set('entra_client_id', trim((string) $request->input('entra_client_id', '')));
        Setting::set('ldap_host', trim((string) $request->input('ldap_host', '')));
        Setting::set('ldap_port', (string) (int) $request->input('ldap_port', 389));
        Setting::set('ldap_base_dn', trim((string) $request->input('ldap_base_dn', '')));
        Setting::set('ldap_bind_dn', trim((string) $request->input('ldap_bind_dn', '')));
        $filter = trim((string) $request->input('ldap_user_filter', '(mail=%s)'));
        Setting::set('ldap_user_filter', $filter !== '' ? $filter : '(mail=%s)');

        Setting::set('ldap_user_create_validation', $request->input('ldap_user_create_validation', 'disabled'));

        Setting::set('auth_password_help_url', trim((string) $request->input('auth_password_help_url', '')));

        $mapRaw = trim((string) $request->input('auth_directory_group_role_map', ''));
        if ($mapRaw === '') {
            Setting::set('auth_directory_group_role_map', '[]');
        } else {
            $decoded = json_decode($mapRaw, true);
            Setting::set(
                'auth_directory_group_role_map',
                json_encode(is_array($decoded) ? $decoded : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }

        if (! AuthModeService::anyMethodEnabled()) {
            return redirect()
                ->route('settings.auth')
                ->withErrors(['auth' => __('auth.settings_at_least_one_method')])
                ->withInput();
        }

        if (Setting::getBool('auth_entra_enabled') && ! AuthModeService::entraConfigured()) {
            return redirect()
                ->route('settings.auth')
                ->withErrors(['auth' => __('auth.settings_entra_incomplete')])
                ->withInput();
        }

        if (Setting::getBool('auth_ldap_enabled') && ! AuthModeService::ldapConfigured()) {
            return redirect()
                ->route('settings.auth')
                ->withErrors(['auth' => __('auth.settings_ldap_incomplete')])
                ->withInput();
        }

        return redirect()
            ->route('settings.auth')
            ->with('success', __('common.saved'));
    }
}
