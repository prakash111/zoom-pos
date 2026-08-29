// ApexCharts used to load as a raw ~930KB unminified-by-Vite <script> tag,
// gated by a `request()->routeIs('tenant.reports.*')` check in the layout.
// This achieves the same "only on the reports screen" laziness but through
// Vite (content-hashed caching, real minification/tree-shaking) and by
// checking for the dashboard's own DOM marker instead of a route string —
// so it stays correct even if the reports screen is ever reached by another
// route name.
let apexChartsPromise = null;

export function loadApexCharts() {
    if (window.ApexCharts) return Promise.resolve(window.ApexCharts);

    apexChartsPromise ??= import('apexcharts').then((module) => {
        window.ApexCharts = module.default;
        window.dispatchEvent(new CustomEvent('apexcharts-ready'));
        return module.default;
    });

    return apexChartsPromise;
}

function loadApexChartsWhenPresent() {
    if (!document.querySelector('[data-apexcharts-dashboard]')) return;

    loadApexCharts().catch(() => {
        // Charts are a progressive enhancement — the rest of the reports
        // page (filters, tables, exports) must stay usable without them.
    });
}

document.addEventListener('DOMContentLoaded', loadApexChartsWhenPresent);
document.addEventListener('livewire:navigated', loadApexChartsWhenPresent);
