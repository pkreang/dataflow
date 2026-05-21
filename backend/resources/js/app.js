import './bootstrap';
import Alpine from 'alpinejs';
import anchor from '@alpinejs/anchor';
import Chart from 'chart.js/auto';
import QRCode from 'qrcode';
import Sortable from 'sortablejs';
window.Chart = Chart;
window.Sortable = Sortable;

// ต้องกำหนดก่อน Alpine.start()
window.Alpine = Alpine;
Alpine.plugin(anchor);

// Pinned menus store — keeps ★ button state in sync across sidebar
// without reloading the page. Server-rendered pinned section still only
// updates on next navigation; the instant feedback is the star flip.
Alpine.store('pinnedMenus', {
    ids: [],
    pending: false,
    init() {
        const initial = window.__PINNED_MENU_IDS__;
        this.ids = Array.isArray(initial) ? initial.map(String) : [];
    },
    has(id) {
        return this.ids.includes(String(id));
    },
    async toggle(id) {
        if (this.pending) return;
        const key = String(id);
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        this.pending = true;
        const wasPinned = this.has(key);
        this.ids = wasPinned ? this.ids.filter(v => v !== key) : [...this.ids, key];
        try {
            const res = await fetch('/myprofile/pinned-menus/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ menu_key: key }),
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const body = await res.json();
            const nowPinned = !!body.pinned;
            const inList = this.has(key);
            if (nowPinned && !inList) this.ids = [...this.ids, key];
            if (!nowPinned && inList) this.ids = this.ids.filter(v => v !== key);
        } catch (e) {
            this.ids = wasPinned ? [...this.ids, key] : this.ids.filter(v => v !== key);
        } finally {
            this.pending = false;
        }
    },
});

// Theme store — app is light-only. Kept as a no-op so existing
// `$store.theme.toggle()` / `$store.theme.dark` references don't blow up.
Alpine.store('theme', {
    dark: false,
    init() {
        document.documentElement.classList.remove('dark');
    },
    toggle() { /* light-only */ },
    apply() {
        document.documentElement.classList.remove('dark');
    }
});

// Density store — comfortable (default) / compact. Mirrors theme precedence
// (server <meta> > localStorage > default) so a signed-in user's choice
// follows them across devices while a guest still gets per-browser toggle.
Alpine.store('density', {
    mode: 'comfortable',
    init() {
        const serverPref = document.querySelector('meta[name="user-density"]')?.content;
        const saved = localStorage.getItem('density');
        let effective = 'comfortable';
        if (serverPref === 'compact') {
            effective = 'compact';
        } else if (saved === 'compact' || saved === 'comfortable') {
            effective = saved;
        }
        this.mode = effective;
        this.apply();
    },
    toggle() {
        this.mode = this.mode === 'compact' ? 'comfortable' : 'compact';
        localStorage.setItem('density', this.mode);
        this.apply();
    },
    apply() {
        document.documentElement.classList.toggle('compact', this.mode === 'compact');
    }
});

// User index search component
Alpine.data('userIndex', (initialQuery = '') => ({
    query: initialQuery,
    loading: false,
    searchTimer: null,
    skeletonHtml: '',
    init() {
        const tpl = document.getElementById('users-skeleton-source');
        this.skeletonHtml = tpl ? tpl.innerHTML.trim() : '';
        this.$watch('query', (value) => {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.search(value), 300);
        });
    },
    search(value) {
        this.loading = true;
        const container = this.$refs.usersTable;
        const tbody = container?.querySelector('#users-tbody-data');
        const prevHtml = tbody ? tbody.innerHTML : '';
        if (tbody && this.skeletonHtml) {
            tbody.innerHTML = this.skeletonHtml;
        }
        const url = new URL(window.location);
        if (value) url.searchParams.set('search', value);
        else url.searchParams.delete('search');
        history.replaceState(null, '', url.toString());
        fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const newTbody = doc.querySelector('#users-tbody-data');
                if (tbody && newTbody) {
                    tbody.innerHTML = newTbody.innerHTML;
                } else if (tbody) {
                    tbody.innerHTML = prevHtml;
                } else {
                    const newTable = doc.getElementById('users-table');
                    if (newTable && container) container.innerHTML = newTable.innerHTML;
                }
                const newPagination = doc.getElementById('users-pagination');
                const currentPagination = document.getElementById('users-pagination');
                if (newPagination && currentPagination) {
                    currentPagination.outerHTML = newPagination.outerHTML;
                }
                this.loading = false;
            })
            .catch(() => {
                if (tbody) tbody.innerHTML = prevHtml;
                this.loading = false;
            });
    }
}));

/** Thai subdistrict autocomplete: fills subdistrict, district, province, postal after pick (all fields stay editable) */
Alpine.data('thaiSubdistrictPicker', (config) => ({
    searchUrl: config.searchUrl,
    query: '',
    open: false,
    loading: false,
    results: [],
    timer: null,
    highlighted: -1,
    init() {
        this.$nextTick(() => {
            const sub = this.$refs.subdistrict;
            if (sub?.value) {
                this.query = sub.value;
            }
        });
    },
    onSubdistrictInput(e) {
        this.query = e.target.value;
        this.onInput();
    },
    onInput() {
        this.open = true;
        this.highlighted = -1;
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.fetchResults(), 280);
    },
    async fetchResults() {
        const q = (this.query || '').trim();
        if (q.length < 2) {
            this.results = [];
            this.loading = false;
            return;
        }
        this.loading = true;
        this.results = [];
        try {
            const url = new URL(this.searchUrl, window.location.origin);
            url.searchParams.set('q', q);
            const res = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const body = await res.json();
            this.results = Array.isArray(body.data) ? body.data : [];
        } catch {
            this.results = [];
        } finally {
            this.loading = false;
        }
    },
    labelLine(item) {
        return [item.t, item.a, item.p, item.z].join(' » ');
    },
    select(item) {
        this.$refs.subdistrict.value = item.t;
        this.$refs.district.value = item.a;
        this.$refs.province.value = item.p;
        this.$refs.postal.value = item.z;
        this.query = item.t;
        this.open = false;
        this.results = [];
        this.highlighted = -1;
    },
    onFocus() {
        if (this.results.length) {
            this.open = true;
        }
    },
    onBlurSoon() {
        setTimeout(() => {
            this.open = false;
        }, 180);
    },
    onKeydown(e) {
        if (!this.open || !this.results.length) {
            return;
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            this.highlighted = Math.min(this.highlighted + 1, this.results.length - 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            this.highlighted = Math.max(this.highlighted - 1, 0);
        } else if (e.key === 'Enter' && this.highlighted >= 0) {
            e.preventDefault();
            this.select(this.results[this.highlighted]);
        } else if (e.key === 'Escape') {
            this.open = false;
        }
    },
}));

/**
 * Evaluate visibility rules for dynamic form fields.
 * Rules format: [{ field: "field_key", operator: "equals", value: "urgent" }, ...]
 * Multiple rules are ANDed (all must be true for the field to show).
 *
 * Server is authoritative — this mirror must stay in sync with
 * DocumentFormSubmissionController::evaluateRulesPhp. See
 * tests/Feature/EvaluateRulesPhpTest.php for the matrix of expected behavior.
 */
window.evaluateVisibilityRules = function (rules, formData) {
    if (!rules || !rules.length) return true;
    const isEmpty = function (v) {
        // Match PHP: only null/undefined/empty-string/empty-array are empty.
        // Notably '0' is NOT empty (it's a real value the user entered).
        if (v === null || v === undefined) return true;
        if (Array.isArray(v)) return v.length === 0;
        return v === '';
    };
    return rules.every(function (rule) {
        const fieldValue = formData[rule.field];
        const ruleValue = rule.value;
        switch (rule.operator) {
            case 'equals':
                // Match PHP: array values check membership (multi_select / checkbox).
                if (Array.isArray(fieldValue)) {
                    return fieldValue.map(String).includes(String(ruleValue));
                }
                return String(fieldValue ?? '') === String(ruleValue);
            case 'not_equals':
                if (Array.isArray(fieldValue)) {
                    return !fieldValue.map(String).includes(String(ruleValue));
                }
                return String(fieldValue ?? '') !== String(ruleValue);
            case 'in':
                return Array.isArray(ruleValue) && ruleValue.map(String).includes(String(fieldValue ?? ''));
            case 'not_in':
                return Array.isArray(ruleValue) && !ruleValue.map(String).includes(String(fieldValue ?? ''));
            case 'greater_than':
                return !isNaN(Number(fieldValue)) && !isNaN(Number(ruleValue))
                    && Number(fieldValue) > Number(ruleValue);
            case 'less_than':
                return !isNaN(Number(fieldValue)) && !isNaN(Number(ruleValue))
                    && Number(fieldValue) < Number(ruleValue);
            case 'is_empty':
                return isEmpty(fieldValue);
            case 'is_not_empty':
                return !isEmpty(fieldValue);
            default:
                return false;
        }
    });
};

Alpine.data('dynamicForm', (initialPayload) => ({
    // fp is the reactive payload — accessed directly in x-show expressions
    // e.g. x-show="fp['priority'] === 'ฉุกเฉิน'"
    fp: initialPayload || {},
    /**
     * Mirror of the server-side conditional-required check. The field
     * gets a red asterisk in real time when its required_rules evaluate
     * true against the current payload. Server is still the authority —
     * this is only for UX feedback.
     */
    requiredRulesActive(rules) {
        return Array.isArray(rules) && rules.length > 0
            && window.evaluateVisibilityRules(rules, this.fp);
    },
    init() {
        const self = this;

        const parseKey = (name) => {
            const m = /^(?:fields|form_payload)\[([^\]]+)\]/.exec(name || '');
            return m ? m[1] : null;
        };

        const handler = (e) => {
            const el = e.target;
            if (!el || !el.name) return;
            const key = parseKey(el.name);
            if (!key) return;

            let val;
            if (el.type === 'checkbox') {
                if (el.name.endsWith('[]')) {
                    val = [...self.$el.querySelectorAll('[name="' + el.name + '"]:checked')].map(c => c.value);
                } else {
                    val = el.checked ? (el.value || '1') : '';
                }
            } else {
                val = el.value;
            }

            // Direct reactive set on Alpine proxy
            self.fp[key] = val;
        };

        this.$el.addEventListener('change', handler);
        this.$el.addEventListener('input', handler);
    }
}));

// Render any <canvas data-qr-payload="..." data-qr-size="..."> elements
// on the page. Idempotent — `data-qr-rendered=1` flag prevents double draws
// if multiple lifecycle events fire (DOMContentLoaded + manual call).
window.renderFormQrCodes = function () {
    document.querySelectorAll('canvas[data-qr-payload]').forEach((canvas) => {
        if (canvas.dataset.qrRendered === '1') return;
        const payload = canvas.dataset.qrPayload;
        if (!payload) return;
        const size = parseInt(canvas.dataset.qrSize || '128', 10);
        QRCode.toCanvas(canvas, payload, { width: size, margin: 1 }, (err) => {
            if (!err) canvas.dataset.qrRendered = '1';
        });
    });
};
document.addEventListener('DOMContentLoaded', () => window.renderFormQrCodes());

// Group repeater (subform) — used by the `group` field type. Manages a
// reactive array of row-objects bound to nested `fields[key][i][innerKey]`
// inputs; min/max guard add/remove buttons in the dynamic-field component.
Alpine.data('groupRepeater', (init) => ({
    rows: Array.isArray(init?.rows) && init.rows.length ? init.rows : [{}],
    minRows: Number(init?.minRows ?? 0),
    maxRows: Number(init?.maxRows ?? 200),
    addRow() {
        if (this.rows.length >= this.maxRows) return;
        this.rows.push({});
    },
    removeRow(idx) {
        if (this.rows.length <= this.minRows) return;
        this.rows.splice(idx, 1);
        if (this.rows.length === 0) this.rows.push({});
    },
}));

// Cascading lookup component for document form fields
window.cascadingLookup = function (source, dependsOnKey, foreignKey, initialValue) {
    return {
        items: [],
        selected: initialValue || '',
        loading: false,
        init() {
            // Find parent field by name pattern form_payload[dependsOnKey]
            const parentEl = document.querySelector(`[name="form_payload[${dependsOnKey}]"]`);
            if (!parentEl) return;

            parentEl.addEventListener('change', () => this.fetchItems(parentEl.value));

            // Load initial if parent has value
            if (parentEl.value) this.fetchItems(parentEl.value);
        },
        async fetchItems(parentValue) {
            if (!parentValue) {
                this.items = [];
                this.selected = '';
                return;
            }
            this.loading = true;
            try {
                const resp = await fetch(`/lookup?source=${encodeURIComponent(source)}&filters[${encodeURIComponent(foreignKey)}]=${encodeURIComponent(parentValue)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const json = await resp.json();
                this.items = json.data || [];
                if (!this.items.find(i => String(i.value) === String(this.selected))) {
                    this.selected = '';
                }
            } finally {
                this.loading = false;
            }
        }
    };
};

// Dashboard widget component
Alpine.data('dashboardWidget', (widgetId, dashboardId, widgetType) => ({
    widgetId,
    dashboardId,
    widgetType,
    loading: true,
    error: null,
    data: {},
    chartInstance: null,
    currentPage: 1,

    buildFilterParams() {
        const params = new URLSearchParams();
        const dateFrom = document.getElementById('filter-date-from')?.value;
        const dateTo = document.getElementById('filter-date-to')?.value;
        const deptId = document.getElementById('filter-department')?.value;
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
        if (deptId) params.append('department_id', deptId);
        return params;
    },

    async downloadCsv() {
        const params = this.buildFilterParams();
        const apiToken = document.querySelector('meta[name="api-token"]')?.content || '';
        const url = `/api/v1/dashboards/${this.dashboardId}/widgets/${this.widgetId}/export?${params}`;
        try {
            const resp = await fetch(url, {
                headers: {
                    'Accept': 'text/csv',
                    'Authorization': `Bearer ${apiToken}`,
                },
            });
            if (!resp.ok) {
                throw new Error(`HTTP ${resp.status}`);
            }
            const blob = await resp.blob();
            const disposition = resp.headers.get('Content-Disposition') || '';
            const match = disposition.match(/filename="?([^"]+)"?/i);
            const filename = match ? match[1] : `widget-${this.widgetId}.csv`;
            this._triggerBlobDownload(blob, filename);
        } catch (e) {
            alert('Download failed');
        }
    },

    _triggerBlobDownload(blob, filename) {
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(() => URL.revokeObjectURL(link.href), 1000);
    },

    async loadData() {
        this.loading = true;
        this.error = null;

        const params = this.buildFilterParams();
        if (this.currentPage > 1) params.append('page', this.currentPage);

        const apiToken = document.querySelector('meta[name="api-token"]')?.content || '';

        try {
            const resp = await fetch(
                `/api/v1/dashboards/${this.dashboardId}/widgets/${this.widgetId}/data?${params}`,
                {
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${apiToken}`,
                    }
                }
            );

            if (!resp.ok) {
                throw new Error(`HTTP ${resp.status}`);
            }

            this.data = await resp.json();

            if (this.widgetType === 'chart') {
                this.$nextTick(() => this.renderChart());
            }
        } catch (e) {
            this.error = 'Failed to load data';
        } finally {
            this.loading = false;
        }
    },

    renderChart() {
        const canvas = document.getElementById(`chart-${this.widgetId}`);
        if (!canvas || !window.Chart) return;

        if (this.chartInstance) {
            this.chartInstance.destroy();
            this.chartInstance = null;
        }

        const chartType = canvas.dataset.chartType || 'bar';

        const isCircle = ['pie','doughnut'].includes(chartType === 'donut' ? 'doughnut' : chartType);
        const labels = this.data.labels || [];
        const seriesData = (this.data.datasets?.[0]?.data) || [];
        const palette = [
            '#3B82F6','#10B981','#F59E0B','#EF4444','#8B5CF6',
            '#06B6D4','#F97316','#84CC16','#EC4899','#6366F1',
        ];

        // For bar/line charts grouped by category, provide one color per bar
        // and use the labels as legend entries so users can map color → category.
        let dataset;
        if (isCircle) {
            dataset = {
                data: seriesData,
                backgroundColor: palette,
                borderColor: '#fff',
                borderWidth: 2,
            };
        } else {
            dataset = {
                label: '',
                data: seriesData,
                backgroundColor: labels.map((_, i) => palette[i % palette.length]),
                borderColor: labels.map((_, i) => palette[i % palette.length]),
                borderWidth: 1,
            };
        }

        this.chartInstance = new window.Chart(canvas, {
            type: chartType === 'donut' ? 'doughnut' : chartType,
            data: { labels, datasets: [dataset] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: isCircle ? 'right' : 'bottom',
                        labels: {
                            boxWidth: 12,
                            font: { size: 11 },
                            // For bar/line, the dataset has 1 entry — synthesize per-label legend
                            // entries so each color maps to its category.
                            generateLabels: isCircle ? undefined : (chart) => {
                                const ds = chart.data.datasets[0] || {};
                                return (chart.data.labels || []).map((label, i) => ({
                                    text: label,
                                    fillStyle: Array.isArray(ds.backgroundColor) ? ds.backgroundColor[i] : ds.backgroundColor,
                                    strokeStyle: Array.isArray(ds.borderColor) ? ds.borderColor[i] : ds.borderColor,
                                    index: i,
                                }));
                            },
                        },
                    },
                },
                scales: isCircle ? {} : {
                    x: { ticks: { font: { size: 10 } } },
                    y: { ticks: { font: { size: 10 } }, beginAtZero: true },
                },
            }
        });
    },

    prevPage() {
        if (this.currentPage > 1) {
            this.currentPage--;
            this.loadData();
        }
    },

    nextPage() {
        if (this.data.pagination && this.currentPage < this.data.pagination.last_page) {
            this.currentPage++;
            this.loadData();
        }
    }
}));

// Download the entire dashboard as a ZIP of CSVs. Invoked from report view button.
window.downloadDashboardZip = async function (dashboardId) {
    const params = new URLSearchParams();
    const dateFrom = document.getElementById('filter-date-from')?.value;
    const dateTo = document.getElementById('filter-date-to')?.value;
    const deptId = document.getElementById('filter-department')?.value;
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    if (deptId) params.append('department_id', deptId);

    const apiToken = document.querySelector('meta[name="api-token"]')?.content || '';
    try {
        const resp = await fetch(`/api/v1/dashboards/${dashboardId}/export?${params}`, {
            headers: {
                'Accept': 'application/zip',
                'Authorization': `Bearer ${apiToken}`,
            },
        });
        if (!resp.ok) {
            throw new Error(`HTTP ${resp.status}`);
        }
        const blob = await resp.blob();
        const disposition = resp.headers.get('Content-Disposition') || '';
        const match = disposition.match(/filename="?([^"]+)"?/i);
        const filename = match ? match[1] : `dashboard-${dashboardId}.zip`;
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(() => URL.revokeObjectURL(link.href), 1000);
    } catch (e) {
        alert('Download failed');
    }
};

// ต้อง start หลังสุด
Alpine.start();

// Sync theme store with FOUC
Alpine.store('theme').init();

// Sync density store with FOUC
Alpine.store('density').init();

// Seed pinned menus from server-side payload
Alpine.store('pinnedMenus').init();

/** Required `.form-input` — show error ring on blur (before submit). */
document.addEventListener(
    'focusout',
    (e) => {
        const el = e.target;
        if (!(el instanceof HTMLElement)) return;
        if (!el.classList.contains('form-input') || !el.hasAttribute('required')) return;
        if (el instanceof HTMLInputElement && ['checkbox', 'radio', 'button', 'submit', 'file', 'hidden'].includes(el.type)) {
            return;
        }
        const v = (el.value || '').trim();
        if (!v) el.classList.add('form-input-error');
    },
    true
);

document.addEventListener('input', (e) => {
    const el = e.target;
    if (!(el instanceof HTMLInputElement) && !(el instanceof HTMLTextAreaElement)) return;
    if (!el.classList.contains('form-input')) return;
    if ((el.value || '').trim()) el.classList.remove('form-input-error');
});

document.addEventListener('change', (e) => {
    const el = e.target;
    if (!(el instanceof HTMLSelectElement)) return;
    if (!el.classList.contains('form-input') || !el.hasAttribute('required')) return;
    if ((el.value || '').trim()) el.classList.remove('form-input-error');
});

/** Primary form submit — disable + spinner + loading label (skip with data-no-submit-loading on form or button). */
document.addEventListener(
    'submit',
    (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.hasAttribute('data-no-submit-loading')) return;
        const btn = e.submitter;
        if (!(btn instanceof HTMLButtonElement) || btn.type !== 'submit') return;
        if (!btn.matches('.btn-primary, .btn-secondary, .btn-danger')) return;
        if (btn.hasAttribute('data-no-submit-loading')) return;
        if (btn.disabled) return;
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        btn.classList.add('opacity-60', 'cursor-not-allowed');
        if (btn.querySelector('[data-submit-spinner]')) return;
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'animate-spin h-4 w-4 shrink-0');
        svg.setAttribute('data-submit-spinner', '');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.innerHTML =
            '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>';
        btn.insertBefore(svg, btn.firstChild);
        const loadingText = document.body?.dataset?.submitLoadingText;
        if (!loadingText) return;
        const textNode = [...btn.childNodes].find(
            (n) => n.nodeType === Node.TEXT_NODE && n.textContent.trim() !== ''
        );
        if (textNode) {
            btn.dataset.submitOrigText = textNode.textContent;
            textNode.textContent = loadingText;
        }
    },
    true
);
