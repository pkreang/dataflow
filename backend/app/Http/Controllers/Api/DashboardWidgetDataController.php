<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportDashboard;
use App\Models\ReportDashboardWidget;
use App\Support\DataSourceRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class DashboardWidgetDataController extends Controller
{
    public function show(Request $request, ReportDashboard $dashboard, ReportDashboardWidget $widget): JsonResponse
    {
        $ctx = $this->prepareContext($request, $dashboard, $widget);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$query, $config, $source] = $ctx;

        return match ($widget->widget_type) {
            'metric' => $this->metricData($query, $config, $source),
            'chart' => $this->chartData($query, $config, $source),
            'table' => $this->tableData($query, $config, $source, $request),
            default => response()->json(['error' => __('api.unknown_widget_type')], 422),
        };
    }

    public function exportWidget(Request $request, ReportDashboard $dashboard, ReportDashboardWidget $widget): StreamedResponse|JsonResponse
    {
        $ctx = $this->prepareContext($request, $dashboard, $widget);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$query, $config, $source] = $ctx;

        if (! in_array($widget->widget_type, ['table', 'chart'], true)) {
            return response()->json(['error' => __('api.widget_type_not_exportable')], 422);
        }

        $csv = $this->buildWidgetCsv($query, $config, $source, $widget);
        $filename = $this->csvFilename($dashboard, $widget);

        return response()->streamDownload(function () use ($csv) {
            echo "\xEF\xBB\xBF"; // UTF-8 BOM so Excel opens Thai correctly
            echo $csv;
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportDashboard(Request $request, ReportDashboard $dashboard): StreamedResponse|JsonResponse
    {
        // Permission check once (widget-level check is redundant inside the same dashboard)
        if ($dashboard->visibility === 'permission' && $dashboard->required_permission) {
            $user = $request->user();
            if (! $user) {
                abort(401);
            }
            $isSuperAdmin = $user->is_super_admin ?? false;
            if (! $isSuperAdmin && ! $user->hasPermissionTo($dashboard->required_permission)) {
                abort(403);
            }
        }

        $exportable = $dashboard->widgets()
            ->whereIn('widget_type', ['table', 'chart'])
            ->orderBy('sort_order')
            ->get();

        if ($exportable->isEmpty()) {
            return response()->json(['error' => __('api.no_exportable_widgets')], 422);
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'dash-export-');
        $zip = new ZipArchive();
        if ($zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
            return response()->json(['error' => 'zip-create-failed'], 500);
        }

        foreach ($exportable as $w) {
            $ctx = $this->prepareContext($request, $dashboard, $w);
            if ($ctx instanceof JsonResponse) {
                continue; // skip invalid data sources silently inside zip
            }
            [$query, $config, $source] = $ctx;
            $csv = "\xEF\xBB\xBF".$this->buildWidgetCsv($query, $config, $source, $w);
            $zip->addFromString($this->csvFilename($dashboard, $w), $csv);
        }
        $zip->close();

        $zipName = Str::slug($dashboard->name ?: 'dashboard').'-'.now()->format('Ymd-His').'.zip';

        return response()->streamDownload(function () use ($tmpZip) {
            readfile($tmpZip);
            @unlink($tmpZip);
        }, $zipName, ['Content-Type' => 'application/zip']);
    }

    /**
     * Shared setup for both data and export: verify ownership, permission, build query.
     *
     * @return array{0: \Illuminate\Database\Eloquent\Builder, 1: array, 2: array}|JsonResponse
     */
    private function prepareContext(Request $request, ReportDashboard $dashboard, ReportDashboardWidget $widget)
    {
        if ($widget->dashboard_id !== $dashboard->id) {
            abort(404);
        }

        if ($dashboard->visibility === 'permission' && $dashboard->required_permission) {
            $user = $request->user();
            if (! $user) {
                abort(401);
            }
            $isSuperAdmin = $user->is_super_admin ?? false;
            if (! $isSuperAdmin && ! $user->hasPermissionTo($dashboard->required_permission)) {
                abort(403);
            }
        }

        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'org_unit_id' => 'nullable|integer',
        ]);

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $orgUnitId = $request->query('org_unit_id');

        $config = $widget->config ?? [];
        $source = DataSourceRegistry::get($widget->data_source);
        if (! $source) {
            return response()->json(['error' => __('api.unknown_data_source')], 422);
        }

        try {
            $query = DataSourceRegistry::query($widget->data_source);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => __('api.unknown_data_source')], 422);
        }

        $dateField = $config['date_field'] ?? null;
        if ($dateField && isset($source['date_fields'][$dateField])) {
            if ($dateFrom) {
                $query->whereDate($dateField, '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate($dateField, '<=', $dateTo);
            }

            // Built-in date presets ("this month", "last 30 days") let seeded
            // widgets bake in a window without requiring the user to pick dates.
            // Skipped when the request supplies an explicit date range — the
            // dashboard's date picker always wins so power users can override.
            if (! $dateFrom && ! $dateTo && ! empty($config['date_preset'])) {
                $this->applyDatePreset($query, $dateField, $config['date_preset']);
            }
        }

        if ($orgUnitId && isset($source['filter_fields']['org_unit_id'])) {
            $query->where('org_unit_id', $orgUnitId);
        }

        // Static filters captured at design-time (e.g. status=draft, requester
        // ={current_user}) — applied AFTER the request-driven filters so they
        // act as a baseline scope the user can't escape from the dashboard UI.
        $this->applyConfiguredFilters($query, $config, $source, $request);

        return [$query, $config, $source];
    }

    /**
     * Apply config['filters'] to the query, whitelisted against the source's
     * filter_fields. Supports the {current_user} token which resolves to the
     * authenticated user's id at request time so a single seeded dashboard can
     * scope KPIs ("งานของฉัน") per viewer instead of needing one per user.
     *
     * Tokens that can't be resolved (e.g. {current_user} on a request with no
     * authenticated user) skip the filter rather than produce a 500 — keeps
     * widget render resilient when a guest endpoint slips through.
     */
    private function applyConfiguredFilters($query, array $config, array $source, Request $request): void
    {
        $filters = $config['filters'] ?? [];
        if (! is_array($filters) || empty($filters)) {
            return;
        }

        $allowed = $source['filter_fields'] ?? [];

        foreach ($filters as $field => $rawValue) {
            if (! isset($allowed[$field])) {
                continue;
            }

            $value = $this->resolveFilterValue($rawValue, $request);
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }
    }

    /**
     * Resolve a filter value, expanding runtime tokens. Currently only
     * {current_user} → authenticated user id; null when unresolvable so the
     * caller can drop the clause entirely.
     */
    private function resolveFilterValue($value, Request $request)
    {
        if ($value === '{current_user}') {
            return $request->user()?->id;
        }
        if (is_array($value)) {
            $resolved = [];
            foreach ($value as $v) {
                $r = $this->resolveFilterValue($v, $request);
                if ($r !== null) {
                    $resolved[] = $r;
                }
            }

            return $resolved ?: null;
        }

        return $value === '' ? null : $value;
    }

    /**
     * Apply a fixed-window date preset relative to "now". Unknown presets are
     * a no-op so renaming/removing a preset doesn't break stored widgets.
     */
    private function applyDatePreset($query, string $dateField, string $preset): void
    {
        $now = \Illuminate\Support\Carbon::now();

        switch ($preset) {
            case 'today':
                $query->whereDate($dateField, $now->toDateString());
                break;
            case 'this_week':
                $query->whereBetween($dateField, [
                    $now->copy()->startOfWeek()->toDateTimeString(),
                    $now->copy()->endOfWeek()->toDateTimeString(),
                ]);
                break;
            case 'this_month':
                $query->whereYear($dateField, $now->year)
                    ->whereMonth($dateField, $now->month);
                break;
            case 'last_30_days':
                $query->whereDate($dateField, '>=', $now->copy()->subDays(30)->toDateString());
                break;
            case 'this_year':
                $query->whereYear($dateField, $now->year);
                break;
            // unknown preset → silently ignored
        }
    }

    private function buildWidgetCsv($query, array $config, array $source, ReportDashboardWidget $widget): string
    {
        return $widget->widget_type === 'table'
            ? $this->buildTableCsv($query, $config, $source)
            : $this->buildChartCsv($query, $config, $source);
    }

    private function buildTableCsv($query, array $config, array $source): string
    {
        $columns = $config['columns'] ?? array_keys($source['display_columns'] ?? []);
        $allowedColumns = array_keys($source['display_columns'] ?? []);
        $selectColumns = count($columns) ? array_values(array_intersect($columns, $allowedColumns)) : $allowedColumns;
        if (empty($selectColumns)) {
            $selectColumns = ['id'];
        }
        if (! in_array('id', $selectColumns, true)) {
            array_unshift($selectColumns, 'id');
        }

        // Export all rows (no pagination). Cap defensively to avoid runaway exports.
        $rows = (clone $query)
            ->select($selectColumns)
            ->orderByDesc('id')
            ->limit(10000)
            ->get();

        $labels = array_map(
            fn ($col) => (string) ($source['display_columns'][$col] ?? $col),
            $selectColumns
        );

        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $labels);
        foreach ($rows as $row) {
            fputcsv($fh, array_map(
                fn ($col) => $this->csvCell($row->{$col} ?? null),
                $selectColumns
            ));
        }
        rewind($fh);
        $out = stream_get_contents($fh);
        fclose($fh);

        return $out;
    }

    private function buildChartCsv($query, array $config, array $source): string
    {
        $groupBy = $config['group_by'] ?? null;
        $aggregation = $config['aggregation'] ?? 'count';
        $field = $config['field'] ?? 'id';

        $allowedFields = array_keys($source['aggregate_fields'] ?? []);
        if (! in_array($field, $allowedFields, true)) {
            $field = 'id';
        }
        $allowedGroupBy = array_keys($source['group_by_fields'] ?? []);
        if (! $groupBy || ! in_array($groupBy, $allowedGroupBy, true)) {
            // No valid group_by → export headers only
            $fh = fopen('php://temp', 'r+');
            fputcsv($fh, ['Label', 'Value']);
            rewind($fh);
            $out = stream_get_contents($fh);
            fclose($fh);

            return $out;
        }

        $results = $query
            ->select($groupBy, DB::raw(match ($aggregation) {
                'sum' => "SUM({$field}) as agg_value",
                'avg' => "AVG({$field}) as agg_value",
                default => 'COUNT(*) as agg_value',
            }))
            ->groupBy($groupBy)
            ->orderByDesc('agg_value')
            ->limit(1000)
            ->get();

        $labelHeader = (string) ($source['group_by_fields'][$groupBy] ?? $groupBy);
        $valueHeader = match ($aggregation) {
            'sum' => 'Sum',
            'avg' => 'Avg',
            default => 'Count',
        };

        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, [$labelHeader, $valueHeader]);
        foreach ($results as $row) {
            fputcsv($fh, [
                $this->csvCell($row->{$groupBy} ?? 'N/A'),
                (float) $row->agg_value,
            ]);
        }
        rewind($fh);
        $out = stream_get_contents($fh);
        fclose($fh);

        return $out;
    }

    private function csvCell($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    private function csvFilename(ReportDashboard $dashboard, ReportDashboardWidget $widget): string
    {
        $dashSlug = Str::slug($dashboard->name ?: 'dashboard');
        $widgetSlug = Str::slug($widget->title ?: ('widget-'.$widget->id));
        $date = now()->format('Ymd-His');

        return "{$dashSlug}-{$widgetSlug}-{$date}.csv";
    }

    private function metricData($query, array $config, array $source): JsonResponse
    {
        $aggregation = $config['aggregation'] ?? 'count';
        $field = $config['field'] ?? 'id';

        // Whitelist field against source's aggregate_fields
        $allowedFields = array_keys($source['aggregate_fields'] ?? []);
        if (! in_array($field, $allowedFields, true)) {
            $field = 'id';
            $aggregation = 'count';
        }

        $value = match ($aggregation) {
            'count' => $query->count(),
            'sum' => $query->sum($field),
            'avg' => round((float) $query->avg($field), 2),
            default => $query->count(),
        };

        return response()->json(['value' => $value]);
    }

    private function chartData($query, array $config, array $source): JsonResponse
    {
        $groupBy = $config['group_by'] ?? null;
        $aggregation = $config['aggregation'] ?? 'count';
        $field = $config['field'] ?? 'id';

        // Whitelist field and groupBy against source definition
        $allowedFields = array_keys($source['aggregate_fields'] ?? []);
        if (! in_array($field, $allowedFields, true)) {
            $field = 'id';
        }

        $allowedGroupBy = array_keys($source['group_by_fields'] ?? []);
        if ($groupBy && ! in_array($groupBy, $allowedGroupBy, true)) {
            return response()->json(['labels' => [], 'datasets' => [['data' => []]]]);
        }

        if (! $groupBy) {
            return response()->json(['labels' => [], 'datasets' => [['data' => []]]]);
        }

        $results = $query
            ->select($groupBy, DB::raw(match ($aggregation) {
                'sum' => "SUM({$field}) as agg_value",
                'avg' => "AVG({$field}) as agg_value",
                default => 'COUNT(*) as agg_value',
            }))
            ->groupBy($groupBy)
            ->orderByDesc('agg_value')
            ->limit(20)
            ->get();

        $rawLabels = $results->pluck($groupBy)->map(fn ($v) => $v ?? null)->toArray();
        $labels = $this->resolveLabels($groupBy, $rawLabels);
        $data = $results->pluck('agg_value')->map(fn ($v) => (float) $v)->toArray();

        return response()->json([
            'labels' => $labels,
            'datasets' => [['data' => $data]],
        ]);
    }

    /**
     * Resolve raw FK values (e.g. org_unit_id=3) to human-readable labels
     * (e.g. "ฝ่ายควบคุมคุณภาพ") for chart axis/legend display. Falls back to the
     * raw value if no mapping is found.
     */
    private function resolveLabels(string $groupBy, array $rawLabels): array
    {
        $map = $this->labelMapFor($groupBy);

        if ($map === null) {
            return array_map(fn ($v) => (string) ($v ?? 'N/A'), $rawLabels);
        }

        return array_map(function ($v) use ($map) {
            if ($v === null) return 'N/A';
            return (string) ($map[$v] ?? $v);
        }, $rawLabels);
    }

    private function labelMapFor(string $column): ?array
    {
        return match ($column) {
            'org_unit_id' => $this->lookupTable('org_units', 'name'),
            'user_id', 'requester_user_id', 'assignee_user_id' => $this->userNameLookup(),
            'workflow_id' => $this->lookupTable('approval_workflows', 'name'),
            default => null,
        };
    }

    private function lookupTable(string $table, string $labelCol): array
    {
        return DB::table($table)->pluck($labelCol, 'id')->toArray();
    }

    private function userNameLookup(): array
    {
        return DB::table('users')
            ->select('id', DB::raw("TRIM(CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,''))) as full_name"))
            ->pluck('full_name', 'id')
            ->toArray();
    }

    private function tableData($query, array $config, array $source, Request $request): JsonResponse
    {
        $columns = $config['columns'] ?? array_keys($source['display_columns'] ?? []);
        $perPage = min(max(1, (int) ($config['per_page'] ?? 10)), 100);
        $page = max(1, (int) ($request->query('page', 1)));

        // Select only configured columns (whitelist via source display_columns)
        $allowedColumns = array_keys($source['display_columns'] ?? []);
        $selectColumns = count($columns)
            ? array_intersect($columns, $allowedColumns)
            : $allowedColumns;

        if (empty($selectColumns)) {
            $selectColumns = ['id'];
        }

        // Add primary key if not present (needed for pagination)
        if (! in_array('id', $selectColumns)) {
            array_unshift($selectColumns, 'id');
        }

        $total = $query->count();
        $rows = (clone $query)
            ->select($selectColumns)
            ->orderByDesc('id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(function ($row) use ($selectColumns) {
                // Eloquent Models expose only(); stdClass rows from DB::table() do not.
                $arr = is_object($row) && method_exists($row, 'only')
                    ? $row->only($selectColumns)
                    : array_intersect_key((array) $row, array_flip($selectColumns));
                return $arr;
            })
            ->toArray();

        // Resolve FK columns (org_unit_id → name, user_id → full name, etc.)
        // so the table shows human-readable values instead of raw integer IDs.
        $maps = [];
        foreach ($selectColumns as $col) {
            $m = $this->labelMapFor($col);
            if ($m !== null) {
                $maps[$col] = $m;
            }
        }
        if (! empty($maps)) {
            foreach ($rows as &$row) {
                foreach ($maps as $col => $map) {
                    if (isset($row[$col])) {
                        $row[$col] = $map[$row[$col]] ?? $row[$col];
                    }
                }
            }
            unset($row);
        }

        return response()->json([
            'columns' => $selectColumns,
            'column_labels' => array_map(
                fn ($col) => $source['display_columns'][$col] ?? $col,
                $selectColumns
            ),
            'rows' => $rows,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / max($perPage, 1)),
            ],
        ]);
    }
}
