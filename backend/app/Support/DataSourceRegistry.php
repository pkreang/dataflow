<?php

namespace App\Support;

use App\Models\ApprovalInstance;
use App\Models\Company;
use App\Models\Department;
use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Models\Equipment;
use App\Models\SparePart;
use App\Models\SparePartTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class DataSourceRegistry
{
    /**
     * All queryable data source definitions for report dashboard widgets.
     * Includes static hardcoded sources + dynamic per-DocumentForm sources.
     */
    public static function sources(): array
    {
        return array_merge(
            static::staticSources(),
            static::perFormSources()
        );
    }

    /**
     * Hardcoded data sources — approval-instance and model-backed.
     */
    protected static function staticSources(): array
    {
        return [
            'repair_requests' => [
                'label_en' => 'Repair Requests',
                'label_th' => 'ใบแจ้งซ่อม',
                'model' => null,
                'base_query' => fn () => ApprovalInstance::where('document_type', 'repair_request'),
                'aggregate_fields' => [
                    'id' => 'Count',
                ],
                'group_by_fields' => [
                    'status' => 'Status',
                    'department_id' => 'Department',
                    'requester_user_id' => 'Requester',
                ],
                'filter_fields' => [
                    'status' => 'Status',
                    'department_id' => 'Department',
                    'requester_user_id' => 'Requester',
                ],
                'date_fields' => [
                    'created_at' => 'Created At',
                    'updated_at' => 'Updated At',
                ],
                'display_columns' => [
                    'reference_no' => 'Ref No',
                    'status' => 'Status',
                    'department_id' => 'Department',
                    'requester_user_id' => 'Requester',
                    'created_at' => 'Created At',
                ],
            ],

            'school_eforms' => [
                'label_en' => 'School eForm approvals',
                'label_th' => 'การอนุมัติแบบฟอร์มโรงเรียน',
                'model' => null,
                'base_query' => fn () => ApprovalInstance::query()->whereIn('document_type', [
                    'school_leave_request',
                    'school_procurement',
                    'school_activity',
                ]),
                'aggregate_fields' => [
                    'id' => 'Count',
                ],
                'group_by_fields' => [
                    'status' => 'Status',
                    'department_id' => 'Department',
                    'requester_user_id' => 'Requester',
                ],
                'filter_fields' => [
                    'status' => 'Status',
                    'department_id' => 'Department',
                    'requester_user_id' => 'Requester',
                ],
                'date_fields' => [
                    'created_at' => 'Created At',
                    'updated_at' => 'Updated At',
                ],
                'display_columns' => [
                    'reference_no' => 'Ref No',
                    'status' => 'Status',
                    'department_id' => 'Department',
                    'requester_user_id' => 'Requester',
                    'created_at' => 'Created At',
                ],
            ],

            'school_eforms_pending' => [
                'label_en' => 'School eForms – pending',
                'label_th' => 'แบบฟอร์มโรงเรียน – รออนุมัติ',
                'model' => null,
                'base_query' => fn () => ApprovalInstance::query()
                    ->whereIn('document_type', [
                        'school_leave_request',
                        'school_procurement',
                        'school_activity',
                    ])
                    ->where('status', 'pending'),
                'aggregate_fields' => [
                    'id' => 'Count',
                ],
                'group_by_fields' => [
                    'status' => 'Status',
                    'department_id' => 'Department',
                ],
                'filter_fields' => [
                    'department_id' => 'Department',
                ],
                'date_fields' => [
                    'created_at' => 'Created At',
                    'updated_at' => 'Updated At',
                ],
                'display_columns' => [
                    'reference_no' => 'Ref No',
                    'status' => 'Status',
                    'created_at' => 'Created At',
                ],
            ],

            'pm_am_plans' => [
                'label_en' => 'PM/AM Plans',
                'label_th' => 'แผน PM/AM',
                'model' => null,
                'base_query' => fn () => ApprovalInstance::where('document_type', 'pm_am_plan'),
                'aggregate_fields' => [
                    'id' => 'Count',
                ],
                'group_by_fields' => [
                    'status' => 'Status',
                    'department_id' => 'Department',
                    'requester_user_id' => 'Requester',
                ],
                'filter_fields' => [
                    'status' => 'Status',
                    'department_id' => 'Department',
                    'requester_user_id' => 'Requester',
                ],
                'date_fields' => [
                    'created_at' => 'Created At',
                    'updated_at' => 'Updated At',
                ],
                'display_columns' => [
                    'reference_no' => 'Ref No',
                    'status' => 'Status',
                    'department_id' => 'Department',
                    'requester_user_id' => 'Requester',
                    'created_at' => 'Created At',
                ],
            ],

            'equipment' => [
                'label_en' => 'Equipment',
                'label_th' => 'อุปกรณ์',
                'model' => Equipment::class,
                'base_query' => null,
                'aggregate_fields' => [
                    'id' => 'Count',
                ],
                'group_by_fields' => [
                    'status' => 'Status',
                    'equipment_category_id' => 'Category',
                    'equipment_location_id' => 'Location',
                    'company_id' => 'Company',
                ],
                'filter_fields' => [
                    'status' => 'Status',
                    'is_active' => 'Active',
                    'equipment_category_id' => 'Category',
                    'company_id' => 'Company',
                ],
                'date_fields' => [
                    'created_at' => 'Created At',
                    'installed_date' => 'Installed Date',
                ],
                'display_columns' => [
                    'name' => 'Name',
                    'code' => 'Code',
                    'status' => 'Status',
                    'equipment_category_id' => 'Category',
                    'equipment_location_id' => 'Location',
                    'created_at' => 'Created At',
                ],
            ],

            'spare_parts' => [
                'label_en' => 'Spare Parts',
                'label_th' => 'อะไหล่',
                'model' => SparePart::class,
                'base_query' => null,
                'aggregate_fields' => [
                    'id' => 'Count',
                    'current_stock' => 'Current Stock',
                    'unit_cost' => 'Unit Cost',
                    'min_stock' => 'Min Stock',
                ],
                'group_by_fields' => [
                    'equipment_category_id' => 'Category',
                    'company_id' => 'Company',
                ],
                'filter_fields' => [
                    'is_active' => 'Active',
                    'equipment_category_id' => 'Category',
                    'company_id' => 'Company',
                ],
                'date_fields' => [
                    'created_at' => 'Created At',
                ],
                'display_columns' => [
                    'code' => 'Code',
                    'name' => 'Name',
                    'current_stock' => 'Stock',
                    'min_stock' => 'Min Stock',
                    'unit_cost' => 'Unit Cost',
                    'created_at' => 'Created At',
                ],
            ],

            'spare_part_transactions' => [
                'label_en' => 'Spare Part Transactions',
                'label_th' => 'รายการอะไหล่',
                'model' => SparePartTransaction::class,
                'base_query' => null,
                'aggregate_fields' => [
                    'id' => 'Count',
                    'quantity' => 'Quantity',
                    'unit_cost' => 'Unit Cost',
                ],
                'group_by_fields' => [
                    'transaction_type' => 'Transaction Type',
                    'spare_part_id' => 'Spare Part',
                    'performed_by_user_id' => 'Performed By',
                ],
                'filter_fields' => [
                    'transaction_type' => 'Transaction Type',
                    'spare_part_id' => 'Spare Part',
                    'performed_by_user_id' => 'Performed By',
                ],
                'date_fields' => [
                    'created_at' => 'Created At',
                ],
                'display_columns' => [
                    'spare_part_id' => 'Spare Part',
                    'transaction_type' => 'Type',
                    'quantity' => 'Quantity',
                    'unit_cost' => 'Unit Cost',
                    'created_at' => 'Date',
                ],
            ],

            'users' => [
                'label_en' => 'Users',
                'label_th' => 'ผู้ใช้',
                'model' => User::class,
                'base_query' => null,
                'aggregate_fields' => [
                    'id' => 'Count',
                ],
                'group_by_fields' => [
                    'department_id' => 'Department',
                    'company_id' => 'Company',
                ],
                'filter_fields' => [
                    'is_active' => 'Active',
                    'department_id' => 'Department',
                    'company_id' => 'Company',
                ],
                'date_fields' => [
                    'created_at' => 'Created At',
                ],
                'display_columns' => [
                    'name' => 'Name',
                    'email' => 'Email',
                    'department_id' => 'Department',
                    'created_at' => 'Created At',
                ],
            ],

            'departments' => [
                'label_en' => 'Departments',
                'label_th' => 'แผนก',
                'model' => Department::class,
                'base_query' => null,
                'aggregate_fields' => [
                    'id' => 'Count',
                ],
                'group_by_fields' => [],
                'filter_fields' => [],
                'date_fields' => [
                    'created_at' => 'Created At',
                ],
                'display_columns' => [
                    'name' => 'Name',
                    'code' => 'Code',
                    'created_at' => 'Created At',
                ],
            ],

            'companies' => [
                'label_en' => 'Organizations',
                'label_th' => 'องค์กร',
                'model' => Company::class,
                'base_query' => null,
                'aggregate_fields' => [
                    'id' => 'Count',
                ],
                'group_by_fields' => [],
                'filter_fields' => [],
                'date_fields' => [
                    'created_at' => 'Created At',
                ],
                'display_columns' => [
                    'name' => 'Name',
                    'tax_id' => 'Tax ID',
                    'created_at' => 'Created At',
                ],
            ],
        ];
    }

    /**
     * Auto-generate a source for each active DocumentForm so admins can build reports
     * directly from form fields without writing code. Aggregates/group-by/filters are
     * derived from field metadata (numeric types → aggregate, select/radio/date/dept
     * → group_by, is_searchable → filter). Cached 5 min to avoid reading fields every
     * request. Source key prefix `form:{form_key}` never collides with static sources.
     */
    protected static function perFormSources(): array
    {
        if (! Schema::hasTable('document_forms')) {
            return [];
        }

        // Cache only the data — Closures can't be serialized, so base_query is
        // attached after the cached array is restored.
        $cached = Cache::remember('datasource_registry_form_sources', 300, function () {
            $sources = [];
            $forms = DocumentForm::query()
                ->where('is_active', true)
                ->with('fields')
                ->get();

            foreach ($forms as $form) {
                $aggregate = ['id' => 'Count'];
                $groupBy = [
                    'status' => 'Submission status',
                    'department_id' => 'Department',
                    'user_id' => 'Requester',
                ];
                $filter = [
                    'status' => 'Status',
                    'department_id' => 'Department',
                ];
                $display = [
                    'reference_no' => 'Ref No',
                    'status' => 'Status',
                    'department_id' => 'Department',
                    'user_id' => 'Requester',
                    'created_at' => 'Created',
                ];
                $dateFields = [
                    'created_at' => 'Created At',
                    'updated_at' => 'Updated At',
                ];

                $hasFdata = $form->hasDedicatedTable();
                if ($hasFdata) {
                    foreach ($form->fields as $f) {
                        $key = $f->field_key;
                        if (in_array($f->field_type, ['number', 'currency'], true)) {
                            $aggregate[$key] = $f->label.' ('.$f->field_type.')';
                        }
                        if (in_array($f->field_type, ['select', 'radio', 'date', 'datetime', 'lookup'], true)) {
                            $groupBy[$key] = $f->label;
                        }
                        if ($f->is_searchable) {
                            $filter[$key] = $f->label;
                        }
                        if (in_array($f->field_type, ['date', 'datetime'], true)) {
                            $dateFields[$key] = $f->label;
                        }
                        if (! in_array($f->field_type, ['section', 'signature', 'file', 'image', 'auto_number'], true)) {
                            $display[$key] = $f->label;
                        }
                    }
                }

                $sources['form:'.$form->form_key] = [
                    'label_en' => 'Form: '.$form->name,
                    'label_th' => 'ฟอร์ม: '.$form->name,
                    'model' => null,
                    'source_type' => 'form',
                    'form_id' => $form->id,
                    'form_key' => $form->form_key,
                    'submission_table' => $form->submission_table,
                    'has_fdata' => $hasFdata,
                    'aggregate_fields' => $aggregate,
                    'group_by_fields' => $groupBy,
                    'filter_fields' => $filter,
                    'date_fields' => $dateFields,
                    'display_columns' => $display,
                ];
            }

            return $sources;
        });

        // Attach the base_query Closure after read — closures aren't serializable.
        $sources = [];
        foreach ($cached as $key => $config) {
            $hasFdata = $config['has_fdata'] ?? false;
            $submissionTable = $config['submission_table'] ?? null;
            $formId = $config['form_id'] ?? null;
            $config['base_query'] = $hasFdata && $submissionTable
                ? fn () => \Illuminate\Support\Facades\DB::table($submissionTable)
                : fn () => DocumentFormSubmission::where('form_id', $formId);
            $sources[$key] = $config;
        }

        return $sources;
    }

    /**
     * Forget the per-form cache. Invoked from DocumentForm model boot events so adding
     * or editing a form surfaces as a new report data source within the same session.
     */
    public static function flushFormSourcesCache(): void
    {
        Cache::forget('datasource_registry_form_sources');
    }

    /**
     * Get the list of valid source keys.
     */
    public static function sourceKeys(): array
    {
        return array_keys(static::sources());
    }

    /**
     * Returns a base query builder for the given source. Most sources return
     * an Eloquent Builder; form sources backed by a dedicated fdata_* table
     * return a Query\Builder (column names align with field keys directly).
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public static function query(string $source)
    {
        $config = static::sources()[$source] ?? null;

        if (! $config) {
            throw new \InvalidArgumentException("Unknown data source: [{$source}]");
        }

        if (isset($config['base_query']) && $config['base_query'] instanceof \Closure) {
            return ($config['base_query'])();
        }

        if (! empty($config['model'])) {
            return $config['model']::query();
        }

        throw new \InvalidArgumentException("Data source [{$source}] has no model or base_query configured.");
    }

    /**
     * Returns source config array or null if not found.
     */
    public static function get(string $source): ?array
    {
        return static::sources()[$source] ?? null;
    }
}
