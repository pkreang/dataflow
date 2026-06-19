<?php

namespace App\Console\Commands;

use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Services\FormSchemaService;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class BackfillDedicatedTableCommand extends Command
{
    protected $signature = 'forms:backfill-dedicated-table
        {form_key : Form key (matches document_forms.form_key)}
        {--dry-run : Count and validate only, no writes}
        {--force : Re-backfill submissions that already have fdata_row_id (deletes old fdata row first)}
        {--batch=200 : Chunk size for memory safety}';

    protected $description = 'Backfill fdata_* rows from existing document_form_submissions.payload (run after enabling a dedicated table on a form that already has submissions).';

    public function handle(FormSchemaService $schema): int
    {
        $formKey = (string) $this->argument('form_key');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $batch = max(1, (int) $this->option('batch'));

        $form = DocumentForm::where('form_key', $formKey)->with('fields')->first();
        if (! $form) {
            $this->error("Form with form_key=[{$formKey}] not found.");

            return self::FAILURE;
        }

        if (! $form->hasDedicatedTable()) {
            $this->error("Form [{$formKey}] has no submission_table set. Enable dedicated table via /settings/document-forms first.");

            return self::FAILURE;
        }

        $schema->ensureTableExists($form);

        $query = DocumentFormSubmission::where('form_id', $form->id);
        if (! $force) {
            $query->whereNull('fdata_row_id');
        }

        $total = $query->count();
        if ($total === 0) {
            $this->info('No submissions to backfill.');

            return self::SUCCESS;
        }

        $prefix = $dryRun ? '[DRY-RUN] ' : '';
        $this->info("{$prefix}Backfilling {$total} submission(s) for form [{$formKey}] → {$form->submission_table}");

        $stats = ['inserted' => 0, 'skipped_empty' => 0, 'failed' => 0];
        $errors = [];

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById($batch, function ($chunk) use ($form, $schema, $dryRun, $force, &$stats, &$errors, $bar) {
            foreach ($chunk as $submission) {
                $payload = $submission->payload;
                if (empty($payload)) {
                    $stats['skipped_empty']++;
                    $bar->advance();

                    continue;
                }

                $meta = [
                    'user_id' => $submission->user_id,
                    'status' => $submission->status,
                    'reference_no' => $submission->reference_no,
                    'approval_instance_id' => $submission->approval_instance_id,
                ];

                if ($dryRun) {
                    $stats['inserted']++;
                    $bar->advance();

                    continue;
                }

                try {
                    DB::transaction(function () use ($form, $submission, $payload, $meta, $schema, $force) {
                        if ($force && $submission->fdata_row_id) {
                            $schema->deleteRow($form, $submission->fdata_row_id);
                        }
                        $newId = $schema->insertRow($form, $payload, $meta);
                        $submission->update(['fdata_row_id' => $newId]);
                    });
                    $stats['inserted']++;
                } catch (QueryException $e) {
                    $stats['failed']++;
                    if (count($errors) < 5) {
                        $errors[] = "Submission #{$submission->id}: ".$e->getMessage();
                    }
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $label = $dryRun ? 'Would process' : 'Processed';
        $this->info("{$label}: {$stats['inserted']}");

        if ($stats['skipped_empty']) {
            $this->line("Skipped (empty payload): {$stats['skipped_empty']}");
        }

        if ($stats['failed']) {
            $this->warn("Failed: {$stats['failed']}");
            foreach ($errors as $err) {
                $this->line("  - {$err}");
            }
        }

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
