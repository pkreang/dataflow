<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipe all transactional/demo data, keeping only super-admin users and companies.
 *
 *   php artisan data:reset
 */
class ResetDemoDataCommand extends Command
{
    protected $signature = 'data:reset {--force : Skip confirmation prompt}';

    protected $description = 'Delete all demo/transactional data. Keeps super-admin users and companies.';

    public function handle(): int
    {
        if (! $this->option('force')) {
            if (! $this->confirm('This will DELETE all org units, equipment, spare parts, approval instances, and non-admin users. Continue?')) {
                $this->info('Aborted.');

                return 0;
            }
        }

        $this->info('Disabling foreign key checks...');
        Schema::disableForeignKeyConstraints();

        $tables = [
            'approval_instance_steps',
            'approval_instances',
            'spare_part_requisition_items',
            'spare_part_transactions',
            'spare_parts',
            'equipment',
            'equipment_locations',
            'equipment_categories',
            'approval_workflow_stages',
            'approval_workflows',
            'org_unit_workflow_bindings',
            'document_form_org_units',
            'notification_preferences',
            'running_number_configs',
            'org_units',
            'positions',
            'branches',
            // RBAC — re-seeded below
            'model_has_permissions',
            'model_has_roles',
            'role_has_permissions',
            'roles',
            'permissions',
        ];

        foreach ($tables as $table) {
            DB::table($table)->truncate();
            $this->line("  Cleared: {$table}");
        }

        // Remove non-super-admin users
        $keepIds = User::where('is_super_admin', 1)->pluck('id');
        $deleted = User::whereNotIn('id', $keepIds)->count();
        User::whereNotIn('id', $keepIds)->delete();

        Schema::enableForeignKeyConstraints();

        $this->info("Deleted {$deleted} non-admin user(s).");
        $this->newLine();
        $this->info('Done. Database reset to clean state — admin user(s) and company retained.');

        return 0;
    }
}
