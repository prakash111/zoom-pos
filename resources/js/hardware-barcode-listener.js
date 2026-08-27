/**
 * Global listener for USB/Bluetooth "keyboard-wedge" barcode scanners —
 * these emulate a physical keyboard, typing every character of the scanned
 * code in a rapid burst (well under the ~60ms/char threshold used below for
 * even a fast human typist) and finishing with Enter.
 *
 * If a normal text input/textarea/contenteditable is focused, this does
 * nothing and lets the browser's native typing handle it — wire:model on a
 * focused barcode field already receives the scan correctly on its own.
 * This only exists to catch a scan while nothing is focused (e.g. the
 * cashier is looking at the product grid, not the search box) and reuses
 * the same 'barcode-scanned' Livewire event the camera scanner dispatches
 * (see pos-scanner.js), so both feed the same #[On('barcode-scanned')] handlers.
 */
const MAX_INTERVAL_MS = 60;
const MIN_CODE_LENGTH = 4;

let buffer = '';
let lastKeyTime = 0;

function isTypingTarget(target) {
    if (!target) return false;
    const tag = target.tagName;
    return tag === 'INPUT' || tag === 'TEXTAREA' || target.isContentEditable;
}

document.addEventListener('keydown', (event) => {
    if (isTypingTarget(event.target)) {
        buffer = '';
        return;
    }

    const now = Date.now();
    if (now - lastKeyTime > MAX_INTERVAL_MS) {
        buffer = '';
    }
    lastKeyTime = now;

    if (event.key === 'Enter') {
        const code = buffer;
        buffer = '';

        if (code.length >= MIN_CODE_LENGTH) {
            window.Livewire?.dispatch('barcode-scanned', { code });
        }

        return;
    }

    if (event.key.length === 1) {
        buffer += event.key;
    }
});
