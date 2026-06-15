<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\DemoPeopleSeeder;
use Database\Seeders\IndustryTemplateSeeder;
use Database\Seeders\PositionDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Purge every user except is_super_admin, then ensure school template + demo workflow actors.
 *
 * Test accounts (password demo1234): one submitter per SCH_* dept + two approvers.
 *   employee@demo.com, admin.staff@demo.com, finance@demo.com, facility@demo.com — employee
 *   manager@demo.com — approver (ขั้น 1), gm@demo.com — approver (ขั้น 2)
 *
 *   php artisan school:workflow-test-users
 *   php artisan school:workflow-test-users --force
 */
class SchoolWorkflowTestUsersCommand extends Command
{
    protected $signature = 'school:workflow-test-users {--force : Skip confirmation}';

    protected $description = 'Delete all users except super-admins, then seed school workflow test users (demo1234).';

    public function handle(): int
    {
        if (! $this->option('force')) {
            if (! $this->confirm('This will DELETE all users that are not super-admin, then create school demo users. Continue?')) {
                $this->info('Aborted.');

                return self::SUCCESS;
            }
        }

        $superIds = User::query()->where('is_super_admin', true)->pluck('id');
        if ($superIds->isEmpty()) {
            $this->error('No user with is_super_admin = true found. Aborting to avoid locking you out.');

            return self::FAILURE;
        }

        $deleted = DB::transaction(fn () => $this->purgeNonSuperAdminUsers($superIds));
        $this->info("Removed {$deleted} non-super-admin user(s).");

        $this->call(PositionDemoSeeder::class);
        $this->call(IndustryTemplateSeeder::class);
        $this->call(DemoPeopleSeeder::class);

        $this->newLine();
        $this->info('School workflow test users ready (password: demo1234):');
        $this->line('  employee@demo.com      — submit (ฝ่ายวิชาการ)');
        $this->line('  admin.staff@demo.com   — submit (ฝ่ายธุรการ)');
        $this->line('  finance@demo.com       — submit (ฝ่ายการเงิน)');
        $this->line('  facility@demo.com    — submit (ฝ่ายอาคารฯ)');
        $this->line('  manager@demo.com       — first approver');
        $this->line('  gm@demo.com            — second approver');

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int|string>  $superIds
     */
    private function purgeNonSuperAdminUsers($superIds): int
    {
        $victimIds = User::query()->whereNotIn('id', $superIds)->pluck('id');
        if ($victimIds->isEmpty()) {
            return 0;
        }

        DB::table('document_form_submissions')->whereIn('user_id', $victimIds)->update(['user_id' => null]);
        DB::table('approval_instances')->whereIn('requester_user_id', $victimIds)->update(['requester_user_id' => null]);
        DB::table('approval_instance_steps')->whereIn('acted_by_user_id', $victimIds)->update(['acted_by_user_id' => null]);
        DB::table('report_dashboards')->whereIn('created_by', $victimIds)->update(['created_by' => null]);

        DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $victimIds)
            ->delete();

        DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->whereIn('model_id', $victimIds)
            ->delete();
        DB::table('model_has_permissions')
            ->where('model_type', User::class)
            ->whereIn('model_id', $victimIds)
            ->delete();

        $count = User::query()->whereIn('id', $victimIds)->count();
        User::query()->whereIn('id', $victimIds)->forceDelete();

        return $count;
    }
}
