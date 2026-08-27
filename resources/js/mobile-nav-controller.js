/**
 * Mobile Navigation Controller
 * Native Android-Style Slide-to-Center Tab Navigation & Synchronized Horizontal Auto-Focus
 */

/**
 * Slide the selected tab/element directly into the horizontal middle of its container.
 * Uses hardware-accelerated smooth scrolling.
 *
 * @param {HTMLElement} element
 */
export function scrollTabToCenter(element) {
    if (!element) return;
    const container = element.parentElement;
    if (!container) return;

    const containerWidth = container.clientWidth;
    let itemLeft = element.offsetLeft;

    // In case the element's offsetParent is not the direct container, compute relative offset
    if (element.offsetParent && element.offsetParent !== container) {
        const containerRect = container.getBoundingClientRect();
        const itemRect = element.getBoundingClientRect();
        itemLeft = itemRect.left - containerRect.left + container.scrollLeft;
    }

    const itemWidth = element.offsetWidth;

    // Calculate exact offset to place active tab in the horizontal middle
    const targetScrollLeft = itemLeft - (containerWidth / 2) + (itemWidth / 2);

    container.scrollTo({
        left: targetScrollLeft,
        behavior: 'smooth'
    });
}

/**
 * Automatically find and center the active tab across all scrollable tab and nav containers.
 * Matches by aria-selected="true", window.location.pathname, or active indicator classes.
 */
export function syncActiveTabs() {
    setTimeout(() => {
        const containers = document.querySelectorAll(
            '.dockable-nav-container, .pos-categories-row, .pos-categories-container, .tab-scroll-container, [data-dock-scroll-container]'
        );

        containers.forEach((container) => {
            // 1. Identify active tab with aria-selected="true"
            let activeElement = container.querySelector('[aria-selected="true"]');

            // 2. If not found, match link against window.location.pathname
            if (!activeElement) {
                const currentPath = window.location.pathname;
                const links = container.querySelectorAll('a.dockable-nav-item, a[href]');
                for (const link of links) {
                    try {
                        const linkUrl = new URL(link.href, window.location.origin);
                        if (linkUrl.pathname === currentPath) {
                            activeElement = link;
                            break;
                        }
                    } catch (e) {}
                }
            }

            // 3. Fallback: Identify elements with active utility classes
            if (!activeElement) {
                activeElement = container.querySelector(
                    '.active, [data-active="true"], .bg-blue-600, .bg-\\[\\#a3e635\\]'
                );
            }

            if (activeElement) {
                scrollTabToCenter(activeElement);
            }
        });
    }, 60);
}

/**
 * Initialize the Mobile Nav Controller and global event listeners.
 */
export function initMobileNavController() {
    if (typeof window === 'undefined') return;

    // On Click Event Listener: Attach global delegated click listener for tab switches
    document.addEventListener('click', (e) => {
        const targetTab = e.target.closest('.dockable-nav-item, .pos-category-btn, .pos-categories-container button, button[role="tab"]');
        if (targetTab) {
            scrollTabToCenter(targetTab);
        }
    });

    // On Page Load / Route Change: Sync active tabs with minor 60ms delay
    window.addEventListener('DOMContentLoaded', () => syncActiveTabs());
    document.addEventListener('livewire:navigated', () => syncActiveTabs());
    document.addEventListener('turbo:load', () => syncActiveTabs());
    window.addEventListener('popstate', () => syncActiveTabs());

    // Alpine Lifecycle synchronization
    document.addEventListener('alpine:init', () => {
        syncActiveTabs();
    });

    // Custom events for dynamic reactive updates
    window.addEventListener('sync-active-tabs', () => syncActiveTabs());
    window.addEventListener('dock-nav-changed', () => syncActiveTabs());
}

if (typeof window !== 'undefined') {
    window.scrollTabToCenter = scrollTabToCenter;
    window.syncActiveTabs = syncActiveTabs;
    initMobileNavController();
}
