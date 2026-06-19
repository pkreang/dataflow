<?php

namespace App\Console\Commands;

use App\Models\ApprovalInstance;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class ResetTestingUserLayerCommand extends Command
{
    protected $signature = 'testing:reset-user-layer
                            {--keep=* : Email addresses to preserve (repeatable); default admin@example.com}
                            {--dry-run : List users that would be removed without deleting}
                            {--force : Skip confirmation (for scripts / non-interactive)}';

    protected $description = 'Remove users (except --keep emails) and related test data while keeping companies, org units, and positions.';

    public function handle(): int
    {
        $keep = collect($this->option('keep'))
            ->filter()
            ->map(fn (string $e) => strtolower(trim($e)))
            ->unique()
            ->values()
            ->all();

        if ($keep === []) {
            $keep = ['admin@example.com'];
        }

        $usersToRemove = User::query()
            ->withTrashed()
            ->get()
            ->filter(fn (User $u) => ! in_array(strtolower((string) $u->email), $keep, true));

        if ($usersToRemove->isEmpty()) {
            $this->info('No users to remove (all match --keep list).');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'email', 'deleted_at'],
            $usersToRemove->map(fn (User $u) => [
                $u->id,
                $u->email,
                $u->deleted_at?->toDateTimeString() ?? '—',
            ])->all()
        );

        if ($this->option('dry-run')) {
            $this->warn('Dry run: no changes made.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Permanently delete these users and their related rows?', true)) {
            $this->info('Aborted.');

            return self::INVALID;
        }

        $ids = $usersToRemove->pluck('id')->all();
        $userClass = User::class;

        DB::transaction(function () use ($ids, $userClass): void {
            PersonalAccessToken::query()
                ->where('tokenable_type', $userClass)
                ->whereIn('tokenable_id', $ids)
                ->delete();

            $pivotRole = config('permission.table_names.model_has_roles', 'model_has_roles');
            $pivotPerm = config('permission.table_names.model_has_permissions', 'model_has_permissions');

            DB::table($pivotRole)
                ->where('model_type', $userClass)
                ->whereIn('model_id', $ids)
                ->delete();

            DB::table($pivotPerm)
                ->where('model_type', $userClass)
                ->whereIn('model_id', $ids)
                ->delete();

            DB::table('notification_preferences')->whereIn('user_id', $ids)->delete();

            DB::table('notifications')
                ->where('notifiable_type', $userClass)
                ->whereIn('notifiable_id', $ids)
                ->delete();

            ApprovalInstance::query()->whereIn('requester_user_id', $ids)->delete();

            DB::table('document_form_submissions')->whereIn('user_id', $ids)->delete();

            if (DB::getSchemaBuilder()->hasTable('sessions')) {
                DB::table('sessions')->whereIn('user_id', $ids)->delete();
            }

            User::query()->withTrashed()->whereIn('id', $ids)->get()->each->forceDelete();
        });

        $this->info('Done. Companies, org units, positions, and role definitions were not modified.');

        return self::SUCCESS;
    }
}
