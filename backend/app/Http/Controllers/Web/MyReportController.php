<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\ReportDashboard;
use App\Support\DataSourceRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MyReportController extends Controller
{
    use HasPerPage;

    private const ROUTE_NS = 'my-reports';

    public function index(Request $request): View
    {
        $userId = (int) (session('user.id') ?? 0);
        $perPage = $this->resolvePerPage($request, 'my_reports_per_page');

        $dashboards = ReportDashboard::query()
            ->where('created_by', $userId)
            ->withCount('widgets')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('my-reports.index', [
            'dashboards' => $dashboards,
            'perPage' => $perPage,
            'routeNamespace' => self::ROUTE_NS,
        ]);
    }

    public function create(): View
    {
        $dataSources = DataSourceRegistry::sources();

        return view('my-reports.create', [
            'dataSources' => $dataSources,
            'routeNamespace' => self::ROUTE_NS,
        ]);
    }

    public function edit(ReportDashboard $dashboard): View
    {
        $this->authorizeOwnership($dashboard);
        $dashboard->load('widgets');
        $dataSources = DataSourceRegistry::sources();

        $initialWidgets = $dashboard->widgets->map(function ($w) {
            $config = $w->config ?? [];

            return [
                'title'         => $w->title,
                'widget_type'   => $w->widget_type,
                'data_source'   => $w->data_source,
                'aggregation'   => $config['aggregation'] ?? 'count',
                'config_field'  => $config['field'] ?? 'id',
                'chart_type'    => $config['chart_type'] ?? 'bar',
                'group_by'      => $config['group_by'] ?? '',
                'date_field'    => $config['date_field'] ?? '',
                'table_columns' => json_encode($config['columns'] ?? []),
                'per_page'      => $config['per_page'] ?? 10,
                'col_span'      => $w->col_span,
            ];
        })->values()->toArray();

        return view('my-reports.edit', [
            'dashboard' => $dashboard,
            'dataSources' => $dataSources,
            'initialWidgets' => $initialWidgets,
            'routeNamespace' => self::ROUTE_NS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(array_merge([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'layout_columns' => 'nullable|integer|in:1,2,3,4',
            'is_active'      => 'nullable|boolean',
        ], $this->widgetRules()));

        $userId = (int) (session('user.id') ?? 0);

        $dashboardId = DB::transaction(function () use ($validated, $userId) {
            $dashboard = ReportDashboard::create([
                'name'                => $validated['name'],
                'description'         => $validated['description'] ?? null,
                'layout_columns'      => (int) ($validated['layout_columns'] ?? 2),
                'visibility'          => 'permission',
                'required_permission' => '__owner_only__',
                'is_active'           => (bool) ($validated['is_active'] ?? true),
                'created_by'          => $userId,
            ]);

            foreach ($validated['widgets'] as $index => $widget) {
                $dashboard->widgets()->create([
                    'title'       => $widget['title'],
                    'widget_type' => $widget['widget_type'],
                    'data_source' => $widget['data_source'],
                    'config'      => $this->parseWidgetConfig($widget),
                    'col_span'    => (int) ($widget['col_span'] ?? 0),
                    'sort_order'  => $index + 1,
                ]);
            }

            return $dashboard->id;
        });

        return redirect()->route('my-reports.index')->with('success', __('common.saved'));
    }

    public function update(Request $request, ReportDashboard $dashboard): RedirectResponse
    {
        $this->authorizeOwnership($dashboard);

        if ($request->has('toggle_active')) {
            $dashboard->update(['is_active' => ! $dashboard->is_active]);
            return redirect()->route('my-reports.index')->with('success', __('common.saved'));
        }

        $validated = $request->validate(array_merge([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'layout_columns' => 'nullable|integer|in:1,2,3,4',
            'is_active'      => 'nullable|boolean',
        ], $this->widgetRules()));

        DB::transaction(function () use ($validated, $dashboard) {
            $dashboard->update([
                'name'           => $validated['name'],
                'description'    => $validated['description'] ?? null,
                'layout_columns' => (int) ($validated['layout_columns'] ?? 2),
                'is_active'      => (bool) ($validated['is_active'] ?? true),
            ]);

            $dashboard->widgets()->delete();

            foreach ($validated['widgets'] as $index => $widget) {
                $dashboard->widgets()->create([
                    'title'       => $widget['title'],
                    'widget_type' => $widget['widget_type'],
                    'data_source' => $widget['data_source'],
                    'config'      => $this->parseWidgetConfig($widget),
                    'col_span'    => (int) ($widget['col_span'] ?? 0),
                    'sort_order'  => $index + 1,
                ]);
            }
        });

        return redirect()->route('my-reports.edit', $dashboard)->with('success', __('common.updated'));
    }

    public function destroy(ReportDashboard $dashboard): RedirectResponse
    {
        $this->authorizeOwnership($dashboard);
        $dashboard->delete();

        return redirect()->route('my-reports.index')->with('success', __('common.deleted'));
    }

    private function authorizeOwnership(ReportDashboard $dashboard): void
    {
        $userId = (int) (session('user.id') ?? 0);
        if ((int) $dashboard->created_by !== $userId) {
            throw new NotFoundHttpException();
        }
    }

    private function widgetRules(): array
    {
        $sourceKeys = implode(',', DataSourceRegistry::sourceKeys());

        return [
            'widgets'               => 'required|array|min:1',
            'widgets.*.title'       => 'required|string|max:255',
            'widgets.*.widget_type' => 'required|in:metric,chart,table',
            'widgets.*.data_source' => "required|string|in:{$sourceKeys}",
            'widgets.*.col_span'    => 'nullable|integer|min:0|max:4',
        ];
    }

    private function parseWidgetConfig(array $widget): array
    {
        $type = $widget['widget_type'];

        if ($type === 'metric') {
            return [
                'aggregation' => $widget['aggregation'] ?? 'count',
                'field'       => $widget['config_field'] ?? 'id',
                'date_field'  => $widget['date_field'] ?? null,
                'filters'     => [],
            ];
        }

        if ($type === 'chart') {
            return [
                'chart_type'  => $widget['chart_type'] ?? 'bar',
                'aggregation' => $widget['aggregation'] ?? 'count',
                'field'       => $widget['config_field'] ?? 'id',
                'group_by'    => $widget['group_by'] ?? null,
                'date_field'  => $widget['date_field'] ?? null,
                'filters'     => [],
            ];
        }

        return [
            'columns'    => json_decode($widget['table_columns'] ?? '[]', true) ?: [],
            'date_field' => $widget['date_field'] ?? null,
            'filters'    => [],
            'per_page'   => (int) ($widget['per_page'] ?? 10),
        ];
    }
}
