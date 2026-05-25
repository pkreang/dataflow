<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'password_min_length' => '8',
            'password_max_length' => '255',
            'password_require_uppercase' => '1',
            'password_require_lowercase' => '1',
            'password_require_number' => '1',
            'password_require_special' => '1',
            'password_expires_days' => '0',
            'password_force_change_first_login' => '1',
            'password_prevent_reuse' => '0',
            'lockout_max_attempts' => '5',
            'lockout_duration_minutes' => '30',
            /** hybrid | department_scoped | organization_wide — see ApprovalFlowService */
            'approval_workflow_routing_mode' => 'hybrid',
            'auth_local_enabled' => '1',
            'auth_entra_enabled' => '0',
            'auth_ldap_enabled' => '0',
            'auth_local_super_admin_only' => '0',
            'auth_default_role' => 'viewer',
            'entra_tenant_id' => '',
            'entra_client_id' => '',
            'ldap_host' => '',
            'ldap_port' => '389',
            'ldap_base_dn' => '',
            'ldap_bind_dn' => '',
            'ldap_user_filter' => '(mail=%s)',
            'ldap_use_tls' => '0',
            /** disabled | required — require LDAP directory match when creating local users (web/API/import) */
            'ldap_user_create_validation' => 'disabled',
            'auth_password_help_url' => '',
            /** JSON array of {"pattern":"substring","role":"spatie_role_name"} for LDAP memberOf / Entra groups */
            'auth_directory_group_role_map' => '[]',
            /** single | multi — single hides "Add Company" button when 1 company exists */
            'company_mode' => 'single',
            /** Allow creating/editing/deleting branches (Companies + API); independent of branch_scoping */
            'branches.enabled' => '1',
            /** Branch scoping: filter lists by user.branch_id (super-admin & users without branch exempt) */
            'branch_scoping.enabled' => '0',
            'branch_scoping.equipment' => '1',
            'branch_scoping.spare_parts' => '1',
            /** Notification settings */
            'notifications.email_enabled' => '1',
            'notifications.approval_pending_email' => '1',
            'notifications.workflow_approved_email' => '1',
            'notifications.workflow_rejected_email' => '1',
            /** LINE Messaging API (LINE Official Account) — replaces LINE Notify (discontinued 2025-03-31) */
            'line_messaging.enabled' => '0',
            'line_messaging.channel_access_token' => '',
            'line_messaging.channel_id' => '',
            /** LINE Login — for account linking flow (users link their LINE userId via OAuth) */
            'line_login.channel_id' => '',
            'line_login.channel_secret' => '',
            'notifications.approval_pending_line' => '1',
            'notifications.workflow_approved_line' => '1',
            'notifications.workflow_rejected_line' => '1',
            'notifications.stock_low_line' => '1',
            /** Stock low — email */
            'notifications.stock_low_email' => '1',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
