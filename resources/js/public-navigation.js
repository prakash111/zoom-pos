/**
 * Lightweight SPA navigation for the public website.
 * Livewire is already present for the contact form, so reuse its navigator
 * without loading the much larger authenticated application bundle.
 */
document.addEventListener('click', (event) => {
    if (
        event.defaultPrevented
        || event.button !== 0
        || event.metaKey
        || event.ctrlKey
        || event.shiftKey
        || event.altKey
    ) return;

    const link = event.target.closest('a[href]');
    if (!link || (link.target && link.target !== '_self') || link.hasAttribute('download')) return;
    if (link.dataset.noSpa === 'true' || link.dataset.navigateIgnore === 'true') return;

    const href = link.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return;

    let destination;
    try {
        destination = new URL(link.href, window.location.href);
    } catch {
        return;
    }

    if (destination.origin !== window.location.origin) return;

    const current = new URL(window.location.href);
    if (
        destination.pathname === current.pathname
        && destination.search === current.search
        && destination.hash
    ) return;

    if (window.Livewire?.navigate) {
        event.preventDefault();
        window.Livewire.navigate(destination.href);
    }
});
