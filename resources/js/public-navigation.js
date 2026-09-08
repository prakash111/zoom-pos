/**
 * Public marketing site runtime.
 *
 * The public pages (landing themes, CMS pages) no longer load Livewire, so
 * this bundle carries the one small dependency their layout needs — Alpine —
 * plus progressive-enhancement niceties (smooth in-page anchor scroll). It is
 * deliberately tiny: Alpine core only, no plugins, deferred.
 */
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

/* Smooth-scroll same-page anchors without the layout-thrash of CSS
   `scroll-behavior: smooth` on the whole document. Respects reduced motion. */
const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey) return;

    const link = event.target.closest('a[href^="#"]');
    if (!link) return;

    const id = link.getAttribute('href').slice(1);
    if (!id) return;

    const target = document.getElementById(id);
    if (!target) return;

    event.preventDefault();
    target.scrollIntoView({
        behavior: prefersReducedMotion.matches ? 'auto' : 'smooth',
        block: 'start',
    });
    history.replaceState(null, '', `#${id}`);
});
