/**
 * Public marketing site runtime.
 *
 * Ships the small pieces the public pages (landing themes + CMS pages) need
 * without the authenticated app bundle or Livewire:
 *   - Alpine (the layout's dropdowns / toggles)
 *   - NProgress-driven SPA navigation between public pages (instant page
 *     swaps, loading bar) — a ~2 KB fetch-and-swap navigator, no Livewire.
 *   - smooth in-page anchor scrolling
 */
import Alpine from 'alpinejs';
import NProgress from 'nprogress';

window.Alpine = Alpine;
Alpine.start();

NProgress.configure({ showSpinner: false, trickleSpeed: 120, minimum: 0.12, speed: 300 });

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

/* ----------------------------------------------------------------------------
 * Smooth-scroll same-page anchors.
 * ------------------------------------------------------------------------- */
function scrollToHash(hash) {
    const target = hash && document.getElementById(hash.slice(1));
    if (!target) return false;
    target.scrollIntoView({
        behavior: prefersReducedMotion.matches ? 'auto' : 'smooth',
        block: 'start',
    });
    return true;
}

/* ----------------------------------------------------------------------------
 * Lightweight SPA navigation for the public site.
 *
 * Intercepts same-origin <a> clicks, fetches the destination, and swaps only
 * <main> — the layout <header>/<footer> and the stylesheet are identical
 * across public pages. Anything that would change the shell (a different
 * stylesheet, a non-public body, a download / target / cross-origin link)
 * falls through to a normal browser navigation.
 * ------------------------------------------------------------------------- */
const parser = new DOMParser();
let navToken = 0;

function currentStyleHrefs() {
    return Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .map((l) => l.getAttribute('href'))
        .sort()
        .join('|');
}

function shellMatches(doc) {
    if (!doc.body || !doc.body.classList.contains('public-site')) return false;
    const incoming = Array.from(doc.querySelectorAll('link[rel="stylesheet"]'))
        .map((l) => l.getAttribute('href'))
        .sort()
        .join('|');
    return incoming === currentStyleHrefs();
}

async function spaNavigate(url, { push = true } = {}) {
    const token = ++navToken;
    NProgress.start();

    let doc;
    try {
        const res = await fetch(url, {
            headers: { 'X-Requested-With': 'spa-navigation' },
            credentials: 'same-origin',
        });
        if (!res.ok || !(res.headers.get('content-type') || '').includes('text/html')) {
            throw new Error('non-html');
        }
        doc = parser.parseFromString(await res.text(), 'text/html');
    } catch {
        window.location.href = url; // network / non-HTML — hand off to the browser
        return;
    }
    if (token !== navToken) return; // superseded by a newer click

    if (!shellMatches(doc)) {
        window.location.href = url; // different layout / stylesheet — full load
        return;
    }

    const nextMain = doc.querySelector('main');
    const currentMain = document.querySelector('main');
    if (!nextMain || !currentMain) {
        window.location.href = url;
        return;
    }

    document.title = doc.title;
    const desc = document.querySelector('meta[name="description"]');
    const nextDesc = doc.querySelector('meta[name="description"]');
    if (desc && nextDesc) desc.setAttribute('content', nextDesc.getAttribute('content') || '');

    currentMain.replaceWith(nextMain);
    window.Alpine?.initTree(nextMain);

    if (push) history.pushState({ spa: true }, '', url);

    const hash = new URL(url, location.href).hash;
    if (!scrollToHash(hash)) window.scrollTo({ top: 0, behavior: 'auto' });

    NProgress.done();
    window.dispatchEvent(new CustomEvent('spa:navigated', { detail: { url } }));
}

document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    const link = event.target.closest('a[href]');
    if (!link) return;
    if (link.target && link.target !== '_self') return;
    if (link.hasAttribute('download') || link.dataset.noSpa === 'true') return;

    const href = link.getAttribute('href');
    if (!href || href.startsWith('mailto:') || href.startsWith('tel:')) return;

    let dest;
    try {
        dest = new URL(link.href, location.href);
    } catch {
        return;
    }
    if (dest.origin !== location.origin) return;

    // Pure in-page anchor on the current document → just scroll.
    if (dest.pathname === location.pathname && dest.search === location.search && dest.hash) {
        if (scrollToHash(dest.hash)) {
            event.preventDefault();
            history.replaceState(null, '', dest.hash);
        }
        return;
    }

    event.preventDefault();
    spaNavigate(dest.href);
});

window.addEventListener('popstate', () => {
    spaNavigate(location.href, { push: false });
});
