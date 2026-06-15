<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rename role `viewer` -> `employee`.
 *
 * The role is the default for every JIT/SSO-provisioned user — it is the
 * baseline "ordinary employee" bundle, not a read-only spectator, so the
 * machine name now says so. Row is renamed in place (same id), therefore
 * model_has_roles / role_has_permissions stay untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->renameRole('viewer', 'employee', 'Employee',
            'Read-only access to business modules; default role for new users');
    }

    public function down(): void
    {
        $this->renameRole('employee', 'viewer', 'Viewer',
            'Read-only access to business modules');
    }

    private function renameRole(string $from, string $to, string $displayName, string $description): void
    {
        DB::table('roles')
            ->where('guard_name', 'web')
            ->where('name', $from)
            ->update([
                'name' => $to,
                'display_name' => $displayName,
                'description' => $description,
            ]);

        DB::table('settings')
            ->where('key', 'auth_default_role')
            ->where('value', $from)
            ->update(['value' => $to]);

        // Directory group -> role mappings store role names in a JSON array
        $mapRow = DB::table('settings')->where('key', 'auth_directory_group_role_map')->first();
        if ($mapRow && $mapRow->value) {
            $map = json_decode($mapRow->value, true);
            if (is_array($map)) {
                $changed = false;
                foreach ($map as &$entry) {
                    if (($entry['role'] ?? null) === $from) {
                        $entry['role'] = $to;
                        $changed = true;
                    }
                }
                unset($entry);
                if ($changed) {
                    DB::table('settings')
                        ->where('key', 'auth_directory_group_role_map')
                        ->update(['value' => json_encode($map)]);
                }
            }
        }

        // Safety net — no known rows, but role-based steps reference roles by name
        DB::table('approval_workflow_stages')
            ->where('approver_type', 'role')
            ->where('approver_ref', $from)
            ->update(['approver_ref' => $to]);
        DB::table('approval_instance_steps')
            ->where('approver_type', 'role')
            ->where('approver_ref', $from)
            ->update(['approver_ref' => $to]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
