import { reconcileActionIcons } from './legacy-action-icon-migrator';

const actionSelector = 'button, a[href]';

// Thin pass di atas implementasi kanonis di legacy-action-icon-migrator:
// modul ini hanya mengumpulkan aksi yang relevan lalu mendelegasikan
// aturan dedupe yang sama (bukan mendefinisikan ulang).
const dedupeActionIcons = (root = document) => {
    const actions = [];

    if (root instanceof HTMLElement) {
        const parentAction = root.closest(actionSelector);
        if (parentAction) actions.push(parentAction);
        if (root.matches(actionSelector)) actions.push(root);
    }

    root.querySelectorAll?.(actionSelector).forEach((action) => actions.push(action));
    [...new Set(actions)].forEach((action) => reconcileActionIcons(action));
};

const boot = () => dedupeActionIcons(document);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}

document.addEventListener('livewire:navigated', boot);

new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (node instanceof HTMLElement) dedupeActionIcons(node);
        });
    });
}).observe(document.body, { childList: true, subtree: true });

export { dedupeActionIcons };
