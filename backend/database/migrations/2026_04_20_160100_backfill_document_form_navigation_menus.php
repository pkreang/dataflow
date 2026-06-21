<?php

use App\Models\DocumentForm;
use Illuminate\Database\Migrations\Migration;

/**
 * One-shot backfill: make the "เอกสาร" root + one child row per active form
 * exist in navigation_menus. Subsequent DocumentForm saves are handled by the
 * model observer added in the same migration batch.
 */
return new class extends Migration
{
    private const DOCUMENTS_PARENT_ID = 48;

    public function up(): void
    {
        // 1. Ensure the "เอกสาร" parent exists. Using raw upsert to bypass the
        //    model's cache-flush events (harmless, just unnecessary during migration).
        \DB::table('navigation_menus')->updateOrInsert(
            ['id' => self::DOCUMENTS_PARENT_ID],
            [
                'parent_id' => null,
                'label' => 'Documents',
                'label_en' => 'Documents',
                'label_th' => 'เอกสาร',
                'icon' => 'document',
                'route' => null,
                'permission' => null,
                'document_form_id' => null,
                'sort_order' => 9,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 2. Backfill children from every active DocumentForm.
        $forms = DocumentForm::where('is_active', true)->orderBy('name')->get();
        foreach ($forms as $index => $form) {
            \DB::table('navigation_menus')->updateOrInsert(
                ['document_form_id' => $form->id],
                [
                    'parent_id' => self::DOCUMENTS_PARENT_ID,
                    'label' => $form->name,
                    'label_en' => $form->name,
                    'label_th' => $form->name,
                    'icon' => 'document-text',
                    'route' => '/forms/'.$form->form_key.'/submissions',
                    'permission' => null,
                    'sort_order' => $index,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 3. Clear any cached menu tree built before this change.
        \Illuminate\Support\Facades\Cache::forget('navigation_menus_tree');
    }

    public function down(): void
    {
        // Remove child rows (document_form_id NOT NULL) — parent "เอกสาร" stays
        // because it's part of NavigationMenuSeeder now.
        \DB::table('navigation_menus')->whereNotNull('document_form_id')->delete();
    }
};
