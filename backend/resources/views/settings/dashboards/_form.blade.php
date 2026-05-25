@php
    $isEdit = isset($dashboard);
    $routeNamespace = $routeNamespace ?? 'settings.dashboards';
    $action = $isEdit
        ? route($routeNamespace.'.update', $dashboard)
        : route($routeNamespace.'.store');
    $initialWidgets = $initialWidgets ?? [];
@endphp

<div x-data="dashboardBuilder({{ Js::from($initialWidgets) }}, {{ Js::from($dataSources) }})">
    @if ($errors->any())
        <div class="alert-error mb-4">
            <ul class="text-sm space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $action }}" class="space-y-5" novalidate>
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        {{-- Dashboard Metadata --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="form-label">{{ __('common.name') }} <span class="text-red-500">*</span></label>
                <input name="name" value="{{ old('name', $dashboard->name ?? '') }}" required
                       class="form-input mt-1" />
            </div>
            <div>
                <label class="form-label">Layout Columns</label>
                <select name="layout_columns" class="form-input mt-1">
                    @php $layoutCols = (int) old('layout_columns', $dashboard->layout_columns ?? 2); @endphp
                    <option value="1" @selected($layoutCols === 1)>1 Column</option>
                    <option value="2" @selected($layoutCols === 2)>2 Columns</option>
                    <option value="3" @selected($layoutCols === 3)>3 Columns</option>
                    <option value="4" @selected($layoutCols === 4)>4 Columns</option>
                </select>
            </div>
            @unless($hideVisibilityPicker ?? false)
            <div x-data="{ visibility: '{{ old('visibility', $dashboard->visibility ?? 'all') }}' }">
                <label class="form-label">Visibility <span class="text-red-500">*</span></label>
                <select name="visibility" x-model="visibility" class="form-input mt-1">
                    <option value="all">All Users</option>
                    <option value="permission">By Permission</option>
                </select>
                <div x-show="visibility === 'permission'" class="mt-2">
                    <label class="form-label">Required Permission</label>
                    <input name="required_permission" value="{{ old('required_permission', $dashboard->required_permission ?? '') }}"
                           placeholder="e.g. dashboard.view_custom"
                           class="form-input mt-1" />
                </div>
            </div>
            @else
            <div></div>
            @endunless
            <div class="flex items-end">
                <x-form.active-toggle
                    name="is_active"
                    :checked="old('is_active', $dashboard->is_active ?? true)"
                    label-class="block text-sm text-slate-600 dark:text-slate-300 mb-1" />
            </div>
        </div>

        <div>
            <label class="form-label">{{ __('common.remark') }}</label>
            <textarea name="description" rows="2" class="form-input mt-1">{{ old('description', $dashboard->description ?? '') }}</textarea>
        </div>

        {{-- Widgets Section --}}
        <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Widgets</h3>
            <div class="flex gap-2">
                <button type="button" @click="showPreview = true" class="btn-secondary">Preview</button>
                <button type="button" @click="addWidget()" class="btn-primary">+ Add Widget</button>
            </div>
        </div>

        <template x-for="(widget, idx) in widgets" :key="idx">
            <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/20 p-4 space-y-3">
                {{-- Widget header --}}
                <div class="flex justify-between items-center">
                    <p class="font-medium text-slate-800 dark:text-slate-200">Widget <span x-text="idx + 1"></span></p>
                    <div class="space-x-2">
                        <button type="button" @click="moveUp(idx)"
                                class="px-2 py-1 rounded bg-slate-200 dark:bg-slate-700 text-xs text-slate-700 dark:text-slate-300">{{ __('common.move_up') }}</button>
                        <button type="button" @click="moveDown(idx)"
                                class="px-2 py-1 rounded bg-slate-200 dark:bg-slate-700 text-xs text-slate-700 dark:text-slate-300">{{ __('common.move_down') }}</button>
                        <button type="button" @click="removeWidget(idx)"
                                class="px-2 py-1 rounded bg-red-600 text-white text-xs">{{ __('common.delete') }}</button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    {{-- Title --}}
                    <div class="md:col-span-2">
                        <label class="text-xs text-slate-500">Title</label>
                        <input x-model="widget.title" required
                               class="form-input mt-1" />
                    </div>

                    {{-- Col Span --}}
                    <div>
                        <label class="text-xs text-slate-500">Col Span</label>
                        <select x-model="widget.col_span" class="form-input mt-1">
                            <option value="0">Auto</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4 (Full)</option>
                        </select>
                    </div>

                    {{-- Data Source --}}
                    <div>
                        <label class="text-xs text-slate-500">Data Source</label>
                        <select x-model="widget.data_source" class="form-input mt-1">
                            <template x-for="[key, src] in Object.entries(dataSources)" :key="key">
                                <option :value="key" x-text="src.label_en"></option>
                            </template>
                        </select>
                    </div>

                    {{-- Widget Type --}}
                    <div>
                        <label class="text-xs text-slate-500">Widget Type</label>
                        <select x-model="widget.widget_type" class="form-input mt-1">
                            <option value="metric">Metric</option>
                            <option value="chart">Chart</option>
                            <option value="table">Table</option>
                        </select>
                    </div>
                </div>

                {{-- Metric config --}}
                <div x-show="widget.widget_type === 'metric'"
                     class="grid grid-cols-1 md:grid-cols-3 gap-3 border-t border-slate-100 dark:border-slate-700 pt-3">
                    <div>
                        <label class="text-xs text-slate-500">Aggregation</label>
                        <select x-model="widget.aggregation" class="form-input mt-1">
                            <option value="count">Count</option>
                            <option value="sum">Sum</option>
                            <option value="avg">Average</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Field</label>
                        <select x-model="widget.config_field" class="form-input mt-1">
                            <template x-for="[fkey, flabel] in Object.entries(getSourceFields(widget.data_source, 'aggregate_fields'))" :key="fkey">
                                <option :value="fkey" x-text="flabel"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Date Field</label>
                        <select x-model="widget.date_field" class="form-input mt-1">
                            <option value="">None</option>
                            <template x-for="[dkey, dlabel] in Object.entries(getSourceFields(widget.data_source, 'date_fields'))" :key="dkey">
                                <option :value="dkey" x-text="dlabel"></option>
                            </template>
                        </select>
                    </div>
                </div>

                {{-- Chart config --}}
                <div x-show="widget.widget_type === 'chart'"
                     class="grid grid-cols-1 md:grid-cols-3 gap-3 border-t border-slate-100 dark:border-slate-700 pt-3">
                    <div>
                        <label class="text-xs text-slate-500">Chart Type</label>
                        <select x-model="widget.chart_type" class="form-input mt-1">
                            <option value="bar">Bar</option>
                            <option value="line">Line</option>
                            <option value="pie">Pie</option>
                            <option value="donut">Donut</option>
                            <option value="area">Area</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Aggregation</label>
                        <select x-model="widget.aggregation" class="form-input mt-1">
                            <option value="count">Count</option>
                            <option value="sum">Sum</option>
                            <option value="avg">Average</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Field</label>
                        <select x-model="widget.config_field" class="form-input mt-1">
                            <template x-for="[fkey, flabel] in Object.entries(getSourceFields(widget.data_source, 'aggregate_fields'))" :key="fkey">
                                <option :value="fkey" x-text="flabel"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Group By</label>
                        <select x-model="widget.group_by" class="form-input mt-1">
                            <option value="">None</option>
                            <template x-for="[gkey, glabel] in Object.entries(getSourceFields(widget.data_source, 'group_by_fields'))" :key="gkey">
                                <option :value="gkey" x-text="glabel"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Date Field</label>
                        <select x-model="widget.date_field" class="form-input mt-1">
                            <option value="">None</option>
                            <template x-for="[dkey, dlabel] in Object.entries(getSourceFields(widget.data_source, 'date_fields'))" :key="dkey">
                                <option :value="dkey" x-text="dlabel"></option>
                            </template>
                        </select>
                    </div>
                </div>

                {{-- Chart preview (sample data) --}}
                <div x-show="widget.widget_type === 'chart'" class="border-t border-slate-100 dark:border-slate-700 pt-3">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('common.chart_preview_label') }}</p>
                        <p class="text-[10px] text-slate-400">{{ __('common.chart_preview_hint') }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 dark:bg-slate-900/40 p-3 border border-slate-200 dark:border-slate-700">
                        <div style="position: relative; height: 200px;">
                            <canvas
                                x-effect="
                                    const type = widget.widget_type;
                                    const chartType = widget.chart_type;
                                    if (type === 'chart') {
                                        $nextTick(() => renderChartPreview($el, chartType));
                                    }
                                "
                                style="max-height: 200px; width: 100%;"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Metric preview (sample value) --}}
                <div x-show="widget.widget_type === 'metric'" class="border-t border-slate-100 dark:border-slate-700 pt-3">
                    <p class="text-xs font-medium text-slate-600 dark:text-slate-300 mb-2">{{ __('common.chart_preview_label') }}</p>
                    <div class="rounded-lg bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/30 dark:to-indigo-900/30 p-5 border border-blue-200 dark:border-blue-800">
                        <p class="text-xs text-slate-600 dark:text-slate-300 mb-1" x-text="widget.title || 'Metric'"></p>
                        <p class="text-3xl font-bold text-blue-700 dark:text-blue-300" x-text="metricSampleValue(widget.aggregation)"></p>
                        <p class="text-[10px] text-slate-400 mt-2">{{ __('common.chart_preview_hint') }}</p>
                    </div>
                </div>

                {{-- Table config --}}
                <div x-show="widget.widget_type === 'table'"
                     class="border-t border-slate-100 dark:border-slate-700 pt-3 space-y-3">
                    <div>
                        <div class="flex items-center justify-between mb-2 gap-2">
                            <label class="text-xs text-slate-500">
                                Columns
                                <span class="ml-1 text-slate-400" x-text="`(${selectedColumnCount(widget)}/${Object.keys(getSourceFields(widget.data_source, 'display_columns')).length})`"></span>
                            </label>
                            <div class="flex gap-2">
                                <button type="button"
                                        @click="selectAllColumns(widget)"
                                        class="text-xs text-blue-600 dark:text-blue-400 hover:underline">{{ __('common.select_all') }}</button>
                                <span class="text-slate-300 dark:text-slate-600">·</span>
                                <button type="button"
                                        @click="clearAllColumns(widget)"
                                        class="text-xs text-slate-500 dark:text-slate-400 hover:underline">{{ __('common.clear_all') }}</button>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50/40 dark:bg-slate-900/20 p-3 max-h-[260px] overflow-y-auto">
                            <template x-for="[ckey, clabel] in Object.entries(getSourceFields(widget.data_source, 'display_columns'))" :key="ckey">
                                <label class="inline-flex items-center gap-2 min-w-0 cursor-pointer" :title="clabel">
                                    <input type="checkbox"
                                           :checked="isColumnSelected(widget, ckey)"
                                           @change="toggleColumn(widget, ckey)"
                                           class="rounded text-blue-600 shrink-0">
                                    <span class="text-sm text-slate-700 dark:text-slate-300 truncate" x-text="clabel"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                    <div class="md:w-1/3">
                        <label class="text-xs text-slate-500">Per Page</label>
                        <select x-model="widget.per_page" class="form-input mt-1">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>

                {{-- Hidden inputs --}}
                <input type="hidden" :name="`widgets[${idx}][title]`" :value="widget.title">
                <input type="hidden" :name="`widgets[${idx}][widget_type]`" :value="widget.widget_type">
                <input type="hidden" :name="`widgets[${idx}][data_source]`" :value="widget.data_source">
                <input type="hidden" :name="`widgets[${idx}][aggregation]`" :value="widget.aggregation">
                <input type="hidden" :name="`widgets[${idx}][config_field]`" :value="widget.config_field">
                <input type="hidden" :name="`widgets[${idx}][chart_type]`" :value="widget.chart_type">
                <input type="hidden" :name="`widgets[${idx}][group_by]`" :value="widget.group_by">
                <input type="hidden" :name="`widgets[${idx}][date_field]`" :value="widget.date_field">
                <input type="hidden" :name="`widgets[${idx}][table_columns]`" :value="widget.table_columns">
                <input type="hidden" :name="`widgets[${idx}][per_page]`" :value="widget.per_page">
                <input type="hidden" :name="`widgets[${idx}][col_span]`" :value="widget.col_span">
            </div>
        </template>

        <div x-show="widgets.length === 0" class="rounded-lg border-2 border-dashed border-slate-300 dark:border-slate-600 p-8 text-center text-sm text-slate-500 dark:text-slate-400">
            No widgets yet. Click "Add Widget" to get started.
        </div>

        <div class="flex flex-wrap justify-end gap-3 pt-2">
            <a href="{{ route($routeNamespace.'.index') }}" class="btn-secondary">
                {{ __('common.cancel') }}
            </a>
            <button type="submit" class="btn-primary">
                {{ __('common.save') }}
            </button>
        </div>
    </form>

    {{-- Preview Modal --}}
    <div x-show="showPreview" x-cloak
         class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="showPreview = false">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-4xl max-h-[80vh] overflow-y-auto p-6"
             @click.outside="showPreview = false">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Dashboard Preview</h3>
                <button @click="showPreview = false" type="button"
                        class="p-1 rounded text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="grid gap-4" :class="`grid-cols-2`">
                <template x-for="(widget, idx) in widgets" :key="idx">
                    <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/30 p-4"
                         :class="colSpanClass(widget.col_span)">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-medium text-slate-900 dark:text-slate-100" x-text="widget.title"></p>
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                  :class="{
                                      'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400': widget.widget_type === 'metric',
                                      'bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-400': widget.widget_type === 'chart',
                                      'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400': widget.widget_type === 'table',
                                  }"
                                  x-text="widget.widget_type">
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Source: <span x-text="dataSources[widget.data_source]?.label_en || widget.data_source"></span>
                        </p>
                        <template x-if="widget.widget_type === 'chart'">
                            <p class="text-xs text-slate-400 mt-1" x-text="widget.chart_type + ' chart'"></p>
                        </template>
                    </div>
                </template>
                <div x-show="widgets.length === 0" class="col-span-2 text-center text-sm text-slate-500 py-8">
                    No widgets to preview.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function dashboardBuilder(initialWidgets, dataSources) {
    return {
        widgets: initialWidgets || [],
        dataSources: dataSources || {},
        showPreview: false,

        addWidget() {
            const firstSource = Object.keys(this.dataSources)[0] || '';
            this.widgets.push({
                title: 'New Widget',
                widget_type: 'metric',
                data_source: firstSource,
                aggregation: 'count',
                config_field: 'id',
                chart_type: 'bar',
                group_by: '',
                date_field: '',
                table_columns: '[]',
                per_page: 10,
                col_span: 0,
            });
        },

        removeWidget(idx) {
            this.widgets.splice(idx, 1);
        },

        moveUp(idx) {
            if (idx > 0) {
                [this.widgets[idx - 1], this.widgets[idx]] = [this.widgets[idx], this.widgets[idx - 1]];
                this.widgets = [...this.widgets];
            }
        },

        moveDown(idx) {
            if (idx < this.widgets.length - 1) {
                [this.widgets[idx + 1], this.widgets[idx]] = [this.widgets[idx], this.widgets[idx + 1]];
                this.widgets = [...this.widgets];
            }
        },

        getSourceFields(source, type) {
            return this.dataSources[source]?.[type] || {};
        },

        isColumnSelected(widget, colKey) {
            try {
                const cols = JSON.parse(widget.table_columns || '[]');
                return Array.isArray(cols) && cols.includes(colKey);
            } catch {
                return false;
            }
        },

        toggleColumn(widget, colKey) {
            try {
                let cols = JSON.parse(widget.table_columns || '[]');
                if (!Array.isArray(cols)) cols = [];
                const idx = cols.indexOf(colKey);
                if (idx === -1) {
                    cols.push(colKey);
                } else {
                    cols.splice(idx, 1);
                }
                widget.table_columns = JSON.stringify(cols);
            } catch {
                widget.table_columns = JSON.stringify([colKey]);
            }
        },

        selectAllColumns(widget) {
            const keys = Object.keys(this.getSourceFields(widget.data_source, 'display_columns'));
            widget.table_columns = JSON.stringify(keys);
        },

        clearAllColumns(widget) {
            widget.table_columns = '[]';
        },

        selectedColumnCount(widget) {
            try {
                const cols = JSON.parse(widget.table_columns || '[]');
                return Array.isArray(cols) ? cols.length : 0;
            } catch {
                return 0;
            }
        },

        colSpanClass(n) {
            const map = { 1: 'col-span-1', 2: 'col-span-2', 3: 'col-span-3', 4: 'col-span-4' };
            return map[parseInt(n)] || '';
        },

        metricSampleValue(aggregation) {
            switch (aggregation) {
                case 'sum': return '฿128,500';
                case 'avg': return '42.7';
                case 'min': return '8';
                case 'max': return '186';
                default: return '247';
            }
        },

        renderChartPreview(canvas, chartType) {
            if (!canvas || !window.Chart) return;
            // Tear down a previous instance attached to this canvas
            const prev = Chart.getChart(canvas);
            if (prev) prev.destroy();

            const sampleLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
            const sampleSeries = [12, 19, 14, 25, 22, 30];
            const pieLabels = ['Pending', 'Approved', 'Rejected', 'Draft'];
            const pieSeries = [42, 86, 14, 23];
            const palette = [
                'rgba(59, 130, 246, 0.85)',
                'rgba(16, 185, 129, 0.85)',
                'rgba(244, 63, 94, 0.85)',
                'rgba(245, 158, 11, 0.85)',
                'rgba(139, 92, 246, 0.85)',
                'rgba(20, 184, 166, 0.85)',
            ];
            const borderPalette = palette.map(c => c.replace('0.85', '1'));

            const isCircle = chartType === 'pie' || chartType === 'donut';
            const isArea = chartType === 'area';
            const cjsType = isCircle ? (chartType === 'donut' ? 'doughnut' : 'pie')
                          : (isArea ? 'line' : chartType);

            const dataset = isCircle ? {
                data: pieSeries,
                backgroundColor: palette,
                borderColor: '#fff',
                borderWidth: 2,
            } : {
                label: 'Sample',
                data: sampleSeries,
                backgroundColor: cjsType === 'bar' ? palette[0] : (isArea ? 'rgba(59, 130, 246, 0.25)' : 'rgba(59, 130, 246, 0.5)'),
                borderColor: borderPalette[0],
                borderWidth: 2,
                fill: isArea,
                tension: 0.35,
                pointRadius: 3,
            };

            new Chart(canvas, {
                type: cjsType,
                data: {
                    labels: isCircle ? pieLabels : sampleLabels,
                    datasets: [dataset],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 400 },
                    plugins: {
                        legend: { display: isCircle, position: 'right', labels: { boxWidth: 10, font: { size: 10 } } },
                        tooltip: { enabled: true },
                    },
                    scales: isCircle ? {} : {
                        x: { ticks: { font: { size: 10 } }, grid: { display: false } },
                        y: { ticks: { font: { size: 10 } }, beginAtZero: true },
                    },
                },
            });
        },
    };
}
</script>
