<div x-data="notificationBell()" x-init="init()" class="relative">
    <button @click="toggle()" type="button"
            class="relative table-action-btn transition-colors
                   text-slate-500 dark:text-slate-400"
            aria-label="{{ __('notifications.notifications') }}">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        <span x-show="count > 0" x-cloak
              x-text="count > 99 ? '99+' : count"
              class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] flex items-center justify-center
                     text-[10px] font-bold text-white bg-red-500 rounded-full px-1"></span>
    </button>

    <div x-show="open" @click.outside="open = false" x-cloak
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute right-0 top-10 w-80 z-50
                bg-white dark:bg-slate-800
                border border-slate-200 dark:border-slate-700
                rounded-[12px] shadow-[var(--shadow-lg)] overflow-hidden">

        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('notifications.notifications') }}</h3>
            <form method="POST" action="{{ route('notifications.read-all') }}" x-show="count > 0" novalidate>
                @csrf
                <button type="submit" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                    {{ __('notifications.mark_all_read') }}
                </button>
            </form>
        </div>

        <div class="max-h-80 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700">
            <template x-if="items.length === 0">
                <div class="px-4 py-8 text-center text-sm text-slate-400 dark:text-slate-500">
                    {{ __('notifications.no_notifications') }}
                </div>
            </template>
            <template x-for="item in items" :key="item.id">
                <a :href="'/notifications/' + item.id + '/read'"
                   @click.prevent="goToNotification(item)"
                   class="flex gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
                   :class="{ 'bg-blue-50/50 dark:bg-blue-900/10': !item.read_at }">
                    <div class="shrink-0 mt-0.5">
                        <template x-if="item.data?.icon === 'check-circle'">
                            <svg class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </template>
                        <template x-if="item.data?.icon === 'x-circle'">
                            <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </template>
                        <template x-if="!item.data?.icon || item.data?.icon === 'clipboard-check'">
                            <svg class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                            </svg>
                        </template>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate" x-text="item.data?.title"></p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 mt-0.5" x-text="item.data?.body"></p>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1" x-text="timeAgo(item.created_at)"></p>
                    </div>
                    <div x-show="!item.read_at" class="shrink-0 mt-2">
                        <span class="block w-2 h-2 rounded-full bg-blue-500"></span>
                    </div>
                </a>
            </template>
        </div>

        <div class="px-4 py-2.5 border-t border-slate-100 dark:border-slate-700 text-center">
            <a href="{{ route('notifications.index') }}"
               class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">
                {{ __('notifications.view_all') }}
            </a>
        </div>
    </div>
</div>

<script>
function notificationBell() {
    return {
        open: false,
        count: 0,
        items: [],
        pollInterval: null,

        init() {
            this.fetchCount();
            this.pollInterval = setInterval(() => this.fetchCount(), 30000);
        },

        toggle() {
            this.open = !this.open;
            if (this.open) this.fetchItems();
        },

        async fetchCount() {
            try {
                const res = await fetch('{{ route("notifications.unread-count") }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.count = data.count;
                }
            } catch (e) {}
        },

        async fetchItems() {
            try {
                const res = await fetch('{{ route("notifications.index", ['unread' => 1]) }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.items = (data.data || []).slice(0, 5);
                }
            } catch (e) {}
        },

        async goToNotification(item) {
            // Submit form to mark as read and redirect
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/notifications/' + item.id + '/read';
            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = document.querySelector('meta[name="csrf-token"]').content;
            form.appendChild(csrf);
            document.body.appendChild(form);
            form.submit();
        },

        timeAgo(dateStr) {
            if (!dateStr) return '';
            const now = new Date();
            const date = new Date(dateStr);
            const diff = Math.floor((now - date) / 1000);
            if (diff < 60) return '{{ __("notifications.just_now") }}';
            if (diff < 3600) return Math.floor(diff / 60) + ' {{ __("notifications.minutes_ago") }}';
            if (diff < 86400) return Math.floor(diff / 3600) + ' {{ __("notifications.hours_ago") }}';
            return Math.floor(diff / 86400) + ' {{ __("notifications.days_ago") }}';
        }
    };
}
</script>
