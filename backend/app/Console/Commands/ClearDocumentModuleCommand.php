<?php

namespace App\Console\Commands;

use App\Models\ApprovalInstance;
use App\Models\ApprovalWorkflow;
use App\Models\DocumentForm;
use App\Models\DocumentType;
use App\Models\NavigationMenu;
use App\Models\ReportDashboardWidget;
use App\Models\RunningNumberConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Removes document forms, repair_request / pm_am_plan workflows & instances,
 * related document types and dashboard widgets, and drops matching sidebar menus.
 */
class ClearDocumentModuleCommand extends Command
{
    protected $signature = 'document-module:clear {--force : Skip confirmation}';

    protected $description = 'Purge document forms plus repair/PM data and related navigation menus.';

    private const DOC_TYPES = ['repair_request', 'pm_am_plan'];

    private const WIDGET_SOURCES = ['repair_requests', 'pm_am_plans'];

    /** Top-level menus that cascade-delete their children (except id 32 handled separately). */
    private const MENU_ROOT_IDS_TO_DELETE = [10, 16, 17, 60];

    /** Settings menus kept so admins can reconfigure forms after a clear. */
    private const MENU_LEAF_IDS_TO_DELETE = [];

    public function handle(): int
    {
        if (! $this->option('force')) {
            if (! $this->confirm('This deletes all document forms, repair & PM/AM approvals/workflows/types, related widgets, and repair/maintenance menus. Continue?')) {
                $this->info('Aborted.');

                return self::SUCCESS;
            }
        }

        DB::transaction(function (): void {
            if (NavigationMenu::query()->whereKey(32)->exists()) {
                NavigationMenu::query()->whereKey(32)->update([
                    'parent_id' => null,
                    'sort_order' => 2,
                ]);
            }

            $inst = ApprovalInstance::query()->whereIn('document_type', self::DOC_TYPES)->delete();
            $this->line("Deleted {$inst} approval instance(s) (repair / PM-AM).");

            $bindings = DB::table('org_unit_workflow_bindings')
                ->whereIn('document_type', self::DOC_TYPES)
                ->delete();
            $this->line("Deleted {$bindings} org unit workflow binding(s).");

            $workflows = ApprovalWorkflow::query()->whereIn('document_type', self::DOC_TYPES)->delete();
            $this->line("Deleted {$workflows} approval workflow(s).");

            $forms = DocumentForm::query()->delete();
            $this->line("Deleted {$forms} document form(s).");

            $types = DocumentType::query()->whereIn('code', self::DOC_TYPES)->delete();
            $this->line("Deleted {$types} document type(s).");

            $widgets = ReportDashboardWidget::query()
                ->whereIn('data_source', self::WIDGET_SOURCES)
                ->delete();
            $this->line("Deleted {$widgets} dashboard widget(s).");

            $running = RunningNumberConfig::query()
                ->whereIn('document_type', self::DOC_TYPES)
                ->delete();
            $this->line("Deleted {$running} running number config(s).");

            foreach (self::MENU_LEAF_IDS_TO_DELETE as $id) {
                NavigationMenu::query()->whereKey($id)->delete();
            }

            foreach (self::MENU_ROOT_IDS_TO_DELETE as $id) {
                NavigationMenu::query()->whereKey($id)->delete();
            }
        });

        Cache::forget('navigation_menus_tree');
        Cache::forget('document_types_active');

        $this->info('Navigation cache cleared. Re-seed menus with: php artisan db:seed --class=NavigationMenuSeeder');

        return self::SUCCESS;
    }
}
