@extends('layouts.app')

@section('title', __('common.document_calendar'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.forms_index_title'), 'url' => route('forms.index')],
        ['label' => __('common.document_calendar')],
    ]" />
@endsection

@section('content')
<div x-data="formCalendar()" x-init="init()" class="space-y-4">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100" x-text="monthLabel"></h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ __('common.document_calendar_desc') }}</p>
            @if($canSeeTeam)
                <p class="text-xs text-blue-600 dark:text-blue-400 mt-0.5">{{ __('common.calendar_team_view') }}</p>
            @endif
        </div>
        <div class="flex items-center gap-2">
            {{-- Form filter --}}
            <select x-model="formKey" @change="loadEvents()" class="form-input text-sm py-1.5">
                <option value="">{{ __('common.calendar_all_forms') }}</option>
                @foreach($forms as $form)
                    <option value="{{ $form->form_key }}">{{ $form->name }}</option>
                @endforeach
            </select>
            {{-- Month navigation --}}
            <button @click="goPrev()" class="btn-secondary px-3 py-1.5 text-sm">&larr;</button>
            <button @click="goNext()" class="btn-secondary px-3 py-1.5 text-sm">&rarr;</button>
        </div>
    </div>

    <div class="flex gap-4">
        {{-- Calendar grid --}}
        <div class="flex-1 min-w-0">
            <div class="card overflow-hidden">
                {{-- Day-of-week header --}}
                <div class="grid grid-cols-7 border-b border-slate-200 dark:border-slate-700">
                    @foreach(['จ','อ','พ','พฤ','ศ','ส','อา'] as $dow)
                        <div class="py-2 text-center text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $dow }}</div>
                    @endforeach
                </div>

                {{-- Loading overlay --}}
                <div x-show="loading" class="grid grid-cols-7">
                    @for($i = 0; $i < 35; $i++)
                        <div class="h-20 border-b border-r border-slate-100 dark:border-slate-800 animate-pulse bg-slate-50 dark:bg-slate-800/30"></div>
                    @endfor
                </div>

                {{-- Day cells --}}
                <div x-show="!loading" class="grid grid-cols-7">
                    <template x-for="day in daysInGrid" :key="day.date">
                        <div
                            @click="selectDay(day.date)"
                            :class="{
                                'bg-slate-50 dark:bg-slate-800/20 text-slate-400': !day.isCurrentMonth,
                                'ring-2 ring-inset ring-blue-500': day.isToday,
                                'cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/20': (events[day.date] ?? []).length > 0,
                                'bg-blue-50/60 dark:bg-blue-900/20': selectedDay === day.date
                            }"
                            class="relative min-h-[5rem] p-1.5 border-b border-r border-slate-100 dark:border-slate-800 transition-colors">

                            {{-- Day number --}}
                            <span
                                :class="{'font-bold text-blue-600 dark:text-blue-400': day.isToday}"
                                class="text-xs text-slate-600 dark:text-slate-400 leading-none"
                                x-text="day.dayNum"></span>

                            {{-- Event dots / count badges --}}
                            <div class="mt-1 flex flex-wrap gap-0.5">
                                <template x-for="(ev, idx) in (events[day.date] ?? []).slice(0, 3)" :key="idx">
                                    <span
                                        :class="{
                                            'bg-amber-400': ev.status === 'pending',
                                            'bg-green-500': ev.status === 'approved',
                                            'bg-red-500':   ev.status === 'rejected',
                                            'bg-blue-400':  ev.status === 'submitted',
                                        }"
                                        class="inline-block w-2 h-2 rounded-full"
                                        :title="ev.user_name + ' — ' + ev.form_name"></span>
                                </template>
                                <template x-if="(events[day.date] ?? []).length > 3">
                                    <span class="text-[10px] text-slate-500 leading-none" x-text="'+' + ((events[day.date] ?? []).length - 3)"></span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Legend --}}
            <div class="flex flex-wrap gap-4 mt-3 text-xs text-slate-500 dark:text-slate-400">
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-amber-400 inline-block"></span>{{ __('common.calendar_legend_pending') }}</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-green-500 inline-block"></span>{{ __('common.calendar_legend_approved') }}</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-red-500 inline-block"></span>{{ __('common.calendar_legend_rejected') }}</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-blue-400 inline-block"></span>{{ __('common.calendar_legend_submitted') }}</span>
            </div>
        </div>

        {{-- Day detail panel --}}
        <div x-show="selectedDay !== null" x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 translate-x-2"
             x-transition:enter-end="opacity-100 translate-x-0"
             class="w-72 shrink-0">
            <div class="card p-4 sticky top-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200" x-text="selectedDayLabel"></h3>
                    <button @click="selectedDay = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg leading-none">&times;</button>
                </div>

                <template x-if="selectedItems.length === 0">
                    <p class="text-sm text-slate-400">{{ __('common.calendar_no_events') }}</p>
                </template>

                <div class="space-y-2">
                    <template x-for="ev in selectedItems" :key="ev.id">
                        <a :href="ev.url"
                           class="block rounded-lg border border-slate-200 dark:border-slate-700 p-3 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate" x-text="ev.ref_no"></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate" x-text="ev.form_name"></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate" x-text="ev.user_name"></p>
                                </div>
                                <span
                                    :class="{
                                        'badge-yellow': ev.status === 'pending',
                                        'badge-green':  ev.status === 'approved',
                                        'badge-red':    ev.status === 'rejected',
                                        'badge-blue':   ev.status === 'submitted',
                                    }"
                                    class="shrink-0 text-xs"
                                    x-text="statusLabel(ev.status)"></span>
                            </div>
                        </a>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function formCalendar() {
    const today = new Date();

    return {
        year:  today.getFullYear(),
        month: today.getMonth() + 1, // 1-based
        formKey: '',
        events: {},
        selectedDay: null,
        selectedItems: [],
        loading: false,

        thaiMonths: ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
                     'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'],

        init() {
            this.loadEvents();
        },

        get monthLabel() {
            return this.thaiMonths[this.month - 1] + ' ' + (this.year + 543);
        },

        get selectedDayLabel() {
            if (!this.selectedDay) return '';
            const d = new Date(this.selectedDay);
            return d.getDate() + ' ' + this.thaiMonths[d.getMonth()] + ' ' + (d.getFullYear() + 543);
        },

        get daysInGrid() {
            const days = [];
            const todayStr = today.toISOString().slice(0, 10);

            // First day of month (0=Sun..6=Sat → remap to Mon-first: 0=Mon..6=Sun)
            const first = new Date(this.year, this.month - 1, 1);
            // getDay(): 0=Sun, 1=Mon .. 6=Sat → Mon-first offset
            let startOffset = (first.getDay() + 6) % 7;

            // Pad from previous month
            for (let i = startOffset - 1; i >= 0; i--) {
                const d = new Date(this.year, this.month - 1, -i);
                days.push({ date: this.toISO(d), dayNum: d.getDate(), isCurrentMonth: false, isToday: false });
            }

            // Current month
            const daysInMonth = new Date(this.year, this.month, 0).getDate();
            for (let n = 1; n <= daysInMonth; n++) {
                const d = new Date(this.year, this.month - 1, n);
                const dateStr = this.toISO(d);
                days.push({ date: dateStr, dayNum: n, isCurrentMonth: true, isToday: dateStr === todayStr });
            }

            // Pad to complete last row (multiple of 7)
            let pad = 1;
            while (days.length % 7 !== 0) {
                const d = new Date(this.year, this.month, pad++);
                days.push({ date: this.toISO(d), dayNum: d.getDate(), isCurrentMonth: false, isToday: false });
            }

            return days;
        },

        toISO(d) {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        },

        async loadEvents() {
            this.loading = true;
            this.selectedDay = null;
            try {
                const params = new URLSearchParams({
                    year: this.year,
                    month: this.month,
                    form_key: this.formKey,
                });
                const resp = await fetch(`{{ route('forms.calendar.events') }}?${params}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await resp.json();
                this.events = data.days ?? {};
            } catch (e) {
                this.events = {};
            } finally {
                this.loading = false;
            }
        },

        selectDay(dateStr) {
            const items = this.events[dateStr] ?? [];
            if (items.length === 0 && this.selectedDay === dateStr) {
                this.selectedDay = null;
                return;
            }
            this.selectedDay = dateStr;
            this.selectedItems = items;
        },

        goNext() {
            if (this.month === 12) { this.year++; this.month = 1; }
            else { this.month++; }
            this.loadEvents();
        },

        goPrev() {
            if (this.month === 1) { this.year--; this.month = 12; }
            else { this.month--; }
            this.loadEvents();
        },

        statusLabel(status) {
            const map = {
                pending:   '{{ __('common.calendar_legend_pending') }}',
                approved:  '{{ __('common.calendar_legend_approved') }}',
                rejected:  '{{ __('common.calendar_legend_rejected') }}',
                submitted: '{{ __('common.calendar_legend_submitted') }}',
            };
            return map[status] ?? status;
        },
    };
}
</script>
@endsection
