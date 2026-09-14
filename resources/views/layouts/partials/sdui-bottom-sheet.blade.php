{{--
    Browser renderer for the same bottom_sheet schema consumed by Flutter.
    Component types and actions are selected by the Laravel response; this
    view is only the existing web registry/renderer for those server models.
--}}
<div
    x-data="{
        visible: false,
        loading: false,
        dispatching: false,
        error: '',
        schema: { title: '', components: [] },
        history: [],

        async show(endpoint, remember = false) {
            if (!endpoint) return;
            if (remember && this.schema?.components?.length) {
                this.history.push(JSON.parse(JSON.stringify(this.schema)));
            } else if (!remember) {
                this.history = [];
            }

            this.visible = true;
            this.loading = true;
            this.error = '';
            document.documentElement.classList.add('overflow-hidden');

            try {
                const response = await fetch(endpoint, {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || payload.error || 'Unable to load this panel.');
                this.schema = payload.schema || payload;
                if (typeof payload.unread_count !== 'undefined') {
                    window.dispatchEvent(new CustomEvent('sdui-unread-count', { detail: { count: payload.unread_count } }));
                }
            } catch (exception) {
                this.error = exception.message || 'Unable to load this panel.';
            } finally {
                this.loading = false;
            }
        },

        close() {
            this.visible = false;
            this.history = [];
            document.documentElement.classList.remove('overflow-hidden');
        },

        back() {
            const previous = this.history.pop();
            if (previous) this.schema = previous;
        },

        async selectFormat(component, option) {
            const endpoint = component?.action?.endpoint;
            if (!endpoint) return;
            const url = new URL(endpoint, window.location.origin);
            url.searchParams.set(component.param_name || 'format', option.value);
            await this.show(url.toString(), false);
        },

        iconFor(name) {
            return {
                receipt_long: '🧾', person_pin: '📌', request_quote: '📑',
                point_of_sale: '🛍️', notifications_active: '🔔',
                notifications_none: '🔔', sms: '💬', chat: '🟢',
                email: '✉️', mark_email_read: '✉️', webhook: '🔗', send: '📤'
            }[name] || '•';
        },

        formatTimestamp(value) {
            if (!value) return '';
            const date = new Date(value);
            return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
        },

        async execute(component) {
            const action = component?.action || component?.on_tap || {};
            const type = String(action.type || component?.action_type || '').toUpperCase();

            if (type === 'OPEN_URL') {
                window.open(action.url || component.url, '_blank', 'noopener,noreferrer');
                return;
            }
            if (type === 'OPEN_BOTTOM_SHEET') {
                await this.show(action.endpoint || component.route, true);
                return;
            }
            if (type === 'NAVIGATE_TO') {
                window.location.assign(action.route || component.route);
                return;
            }
            if (type !== 'SUBMIT_FORM') return;

            this.dispatching = true;
            this.error = '';
            try {
                const response = await fetch(action.endpoint || component.endpoint, {
                    method: action.method || component.method || 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''
                    },
                    body: JSON.stringify(action.data || action.payload || component.data || {})
                });
                const payload = await response.json();
                if (!response.ok || payload.success === false) {
                    throw new Error(payload.error || payload.message || 'Dispatch failed.');
                }
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { type: 'success', message: payload.message || action.feedback || 'Document dispatched.' }
                }));
                if (payload.url || payload.whatsapp_url) {
                    window.open(payload.url || payload.whatsapp_url, '_blank', 'noopener,noreferrer');
                }
            } catch (exception) {
                this.error = exception.message || 'Dispatch failed.';
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { type: 'error', message: this.error }
                }));
            } finally {
                this.dispatching = false;
            }
        }
    }"
    x-on:open-sdui-sheet.window="show($event.detail?.endpoint || $event.detail)"
    x-on:keydown.escape.window="if (visible) close()"
    x-cloak
>
    <div
        x-show="visible"
        x-transition.opacity
        class="fixed inset-0 z-[100000] bg-slate-950/65 backdrop-blur-sm"
        x-on:click="close()"
        aria-hidden="true"
    ></div>

    <section
        x-show="visible"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-y-full"
        x-transition:enter-end="translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-y-0"
        x-transition:leave-end="translate-y-full"
        class="fixed inset-x-0 bottom-0 z-[100001] mx-auto flex max-h-[92dvh] w-full max-w-5xl flex-col overflow-hidden rounded-t-3xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900"
        data-theme-surface="theme.surface"
        data-theme-canvas="theme.canvas"
        role="dialog"
        aria-modal="true"
        aria-labelledby="sdui-sheet-title"
    >
        <div class="mx-auto mt-2 h-1.5 w-12 shrink-0 rounded-full bg-slate-300 dark:bg-slate-700" data-theme-divider="theme.divider"></div>

        <header class="flex shrink-0 items-center gap-3 border-b border-slate-200 px-4 py-3 sm:px-6 dark:border-slate-700" data-theme-divider="theme.divider">
            <button
                x-show="history.length"
                type="button"
                x-on:click="back()"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                aria-label="Back"
            >←</button>
            <div class="min-w-0 flex-1">
                <h2 id="sdui-sheet-title" class="truncate text-base font-black text-slate-900 dark:text-slate-100" x-text="schema.title || 'Preview'"></h2>
                <p x-show="schema.header?.subtitle" class="truncate text-xs text-slate-500 dark:text-slate-400" x-text="schema.header?.subtitle"></p>
            </div>
            <button
                type="button"
                x-on:click="close()"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-lg text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                aria-label="Close"
            >&times;</button>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 dark:bg-slate-950" data-theme-canvas="theme.canvas">
            <div x-show="loading" class="flex min-h-52 flex-col items-center justify-center gap-3 text-sm font-bold text-slate-500 dark:text-slate-400">
                <span class="h-8 w-8 animate-spin rounded-full border-4 border-slate-200 border-t-blue-600 dark:border-slate-700 dark:border-t-blue-400"></span>
                <span>Loading…</span>
            </div>

            <div x-show="error" class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-700 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-300" x-text="error"></div>

            <div x-show="!loading" class="space-y-4">
                <template x-for="(component, index) in (schema.components || [])" :key="component.id || component.type + '-' + index">
                    <div>
                        <template x-if="component.type === 'segmented_tabs'">
                            <div class="flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1.5 dark:border-slate-700 dark:bg-slate-900" data-theme-surface="theme.surface" data-theme-divider="theme.divider">
                                <template x-for="option in component.options" :key="option.value">
                                    <button
                                        type="button"
                                        x-on:click="selectFormat(component, option)"
                                        class="shrink-0 rounded-xl px-3 py-2 text-xs font-extrabold transition"
                                        :class="component.active_value === option.value ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
                                        x-text="option.label"
                                    ></button>
                                </template>
                            </div>
                        </template>

                        <template x-if="component.type === 'document_preview_card'">
                            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900" data-theme-surface="theme.surface" data-theme-divider="theme.divider">
                                <div class="overflow-auto rounded-xl bg-slate-100 p-2 dark:bg-slate-950" data-theme-canvas="theme.canvas">
                                    <iframe
                                        :src="component.render_url"
                                        :title="'Document preview: ' + component.format"
                                        class="mx-auto block h-[54dvh] min-h-[420px] w-full rounded-lg border-0 bg-white"
                                        :style="component.format === 'thermal_58mm' ? 'max-width: 360px' : (component.format === 'thermal_80mm' ? 'max-width: 460px' : (component.format === 'slip' ? 'max-width: 390px' : 'max-width: 820px'))"
                                    ></iframe>
                                </div>
                                <div class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4" x-show="component.summary">
                                    <div class="rounded-xl bg-slate-50 p-2.5 dark:bg-slate-800"><span class="block text-slate-400">Client</span><strong class="text-slate-800 dark:text-slate-100" x-text="component.summary?.client_name"></strong></div>
                                    <div class="rounded-xl bg-slate-50 p-2.5 dark:bg-slate-800"><span class="block text-slate-400">Phone</span><strong class="text-slate-800 dark:text-slate-100" x-text="component.summary?.client_phone"></strong></div>
                                    <div class="rounded-xl bg-slate-50 p-2.5 dark:bg-slate-800"><span class="block text-slate-400">Total</span><strong class="text-slate-800 dark:text-slate-100" x-text="component.summary?.total_amount"></strong></div>
                                    <div class="rounded-xl bg-slate-50 p-2.5 dark:bg-slate-800"><span class="block text-slate-400">Items</span><strong class="text-slate-800 dark:text-slate-100" x-text="component.summary?.item_count"></strong></div>
                                </div>
                            </article>
                        </template>

                        <template x-if="component.type === 'section_header'">
                            <div class="pt-2">
                                <h3 class="text-sm font-black text-slate-900 dark:text-slate-100" x-text="component.title"></h3>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400" x-text="component.subtitle"></p>
                            </div>
                        </template>

                        <template x-if="component.type === 'list_tile' || component.type === 'notification_item'">
                            <button
                                type="button"
                                x-on:click="execute(component)"
                                :disabled="dispatching"
                                class="flex w-full items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3.5 text-left transition hover:border-blue-300 hover:bg-blue-50 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-blue-700 dark:hover:bg-blue-950/30"
                                data-theme-surface="theme.surface"
                                data-theme-divider="theme.divider"
                            >
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-lg dark:bg-slate-800" x-text="iconFor(component.leading?.icon || component.icon)"></span>
                                <span class="min-w-0 flex-1">
                                    <strong class="block truncate text-sm text-slate-900 dark:text-slate-100" x-text="component.title"></strong>
                                    <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400" x-text="component.subtitle"></span>
                                    <span x-show="component.timestamp" class="mt-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-400" x-text="formatTimestamp(component.timestamp)"></span>
                                </span>
                                <span class="shrink-0 text-slate-400">›</span>
                            </button>
                        </template>

                        <template x-if="component.type === 'empty_state'">
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900" data-theme-surface="theme.surface" data-theme-divider="theme.divider">
                                <div class="mb-2 text-3xl" x-text="iconFor(component.icon)"></div>
                                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300" x-text="component.message"></p>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </section>
</div>
