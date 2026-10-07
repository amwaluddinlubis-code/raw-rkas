// Registry init terpusat untuk UI workspace SPJ: menggantikan pola
// `if loading → DOMContentLoaded else run` + `livewire:navigated` +
// `MutationObserver(documentElement)` yang diulang di tiap modul.
// Semantik per modul dipertahankan persis: tiap callback berjalan dalam
// rAF-nya sendiri (isolasi error seperti task terpisah), DOMContentLoaded
// sekali, dan satu observer bersama (bukan satu per modul).
//
// Modul dengan observe:false (butuh pemicu persis DOMContentLoaded +
// navigated saja) tetap mendaftarkan listenernya sendiri lewat registry.

let sharedObserver = null;
const observedSchedules = new Set();

const ensureSharedObserver = (schedule) => {
    observedSchedules.add(schedule);
    if (sharedObserver) return;

    sharedObserver = new MutationObserver((mutations) => {
        if (mutations.some((mutation) => mutation.addedNodes.length > 0)) {
            observedSchedules.forEach((run) => run());
        }
    });
    sharedObserver.observe(document.documentElement, { childList: true, subtree: true });
};

const onSpjUiReady = (callback, { observe = true } = {}) => {
    const schedule = () => window.requestAnimationFrame(() => callback());

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', schedule, { once: true });
    } else {
        schedule();
    }
    document.addEventListener('livewire:navigated', schedule);

    if (observe) ensureSharedObserver(schedule);
};

export { onSpjUiReady };
