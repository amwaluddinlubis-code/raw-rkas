import { createModalShell, openModalShell, closeModalShell, wireModalDismiss } from './ui-modal';

const ACTION_CELL_SELECTOR = '.transaction-action-cell';
const MODAL_ID = 'transaction-action-modal';
let modalTrigger = null;

const transactionLinks = (cell) => ({
    packageLink: Array.from(cell.querySelectorAll('a[href]')).find((link) => link.getAttribute('title') === 'Buka Paket SPJ') || null,
    detailLink: Array.from(cell.querySelectorAll('a[href]')).find((link) => link.getAttribute('title') === 'Buka detail') || null,
});

const ensureModal = () => {
    let modal = document.getElementById(MODAL_ID);
    if (modal) return modal;

    modal = createModalShell({ id: MODAL_ID, labelledby: 'transaction-action-modal-title', overlayClass: 'z-[80]' });
    modal.innerHTML = `
        <div data-transaction-action-panel class="w-full max-w-md rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-5 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide" style="color: var(--theme-content-accent)">Transaksi</p>
                    <h2 id="transaction-action-modal-title" class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">Aksi Transaksi</h2>
                    <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Pilih halaman yang ingin dibuka. Data sumber dan uraian item dikelola di Detail Transaksi; data dokumen dikelola di Paket SPJ.</p>
                </div>
                <button type="button" data-transaction-action-close class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[var(--ui-line)] text-lg text-[var(--ui-fg-muted)] hover:bg-[var(--ui-surface-soft)]" aria-label="Tutup modal">×</button>
            </div>

            <div class="mt-5 grid gap-3">
                <a data-transaction-action-detail href="#" class="flex items-center justify-between gap-3 rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-4 py-3 text-sm font-bold text-[var(--ui-fg-strong)] transition hover:bg-[var(--ui-surface-soft)]">
                    <span>
                        <span class="block">Detail Transaksi</span>
                        <span class="mt-0.5 block text-xs font-normal text-[var(--ui-fg-muted)]">Periksa source ARKAS/BKU dan uraian item untuk SPJ.</span>
                    </span>
                    <span aria-hidden="true">→</span>
                </a>
                <a data-transaction-action-package href="#" class="flex items-center justify-between gap-3 rounded-lg border border-[color-mix(in_srgb,var(--theme-content-accent)_35%,var(--ui-line))] bg-[color-mix(in_srgb,var(--theme-accent-soft)_55%,var(--ui-surface-base))] px-4 py-3 text-sm font-bold text-[var(--theme-content-accent)] transition hover:bg-[color-mix(in_srgb,var(--theme-accent-soft)_75%,var(--ui-surface-base))]">
                    <span>
                        <span class="block">Paket SPJ</span>
                        <span class="mt-0.5 block text-xs font-normal text-[var(--theme-content-accent)]">Siapkan atau buka workspace dokumen SPJ transaksi ini.</span>
                    </span>
                    <span aria-hidden="true">→</span>
                </a>
            </div>

            <div class="mt-5 flex justify-end border-t border-[var(--ui-line)] pt-4">
                <button type="button" data-transaction-action-close class="ui-btn ui-btn-secondary px-4 py-2 text-sm">Tutup</button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    const close = () => {
        closeModalShell(modal);
        modalTrigger?.focus();
        modalTrigger = null;
    };

    wireModalDismiss(modal, { closeSelector: '[data-transaction-action-close]', onClose: close, trapFocus: true });
    modal.querySelectorAll('[data-transaction-action-detail], [data-transaction-action-package]').forEach((link) => {
        link.addEventListener('click', close);
    });

    return modal;
};

const openModalForCell = (cell) => {
    const modal = ensureModal();
    const modalDetail = modal.querySelector('[data-transaction-action-detail]');
    const modalPackage = modal.querySelector('[data-transaction-action-package]');
    const detailUrl = cell.dataset.detailUrl || '';
    const packageUrl = cell.dataset.packageUrl || '';

    if (modalDetail instanceof HTMLAnchorElement) {
        modalDetail.href = detailUrl || '#';
        modalDetail.hidden = !detailUrl;
    }
    if (modalPackage instanceof HTMLAnchorElement) {
        modalPackage.href = packageUrl || '#';
        modalPackage.hidden = !packageUrl;
    }

    modalTrigger = cell.querySelector('[data-transaction-action-trigger]');
    openModalShell(modal, '[data-transaction-action-close]');
};

const bindTrigger = (button, cell) => {
    if (!(button instanceof HTMLButtonElement) || button.dataset.transactionActionBound === 'true') return;

    button.dataset.transactionActionBound = 'true';
    button.addEventListener('click', () => openModalForCell(cell));
};

const initializeActionCell = (cell) => {
    if (!(cell instanceof HTMLElement) || cell.dataset.transactionActionModalBound === 'true') return;

    const existingButton = cell.querySelector('[data-transaction-action-trigger]');
    const { packageLink, detailLink } = transactionLinks(cell);
    const packageUrl = existingButton?.dataset.packageUrl || packageLink?.href || cell.dataset.packageUrl || '';
    const detailUrl = existingButton?.dataset.detailUrl || detailLink?.href || cell.dataset.detailUrl || '';

    if (!packageUrl && !detailUrl) return;

    cell.dataset.transactionActionModalBound = 'true';
    cell.dataset.packageUrl = packageUrl;
    cell.dataset.detailUrl = detailUrl;

    packageLink?.remove();
    detailLink?.remove();

    if (existingButton instanceof HTMLButtonElement) {
        bindTrigger(existingButton, cell);
        return;
    }

    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.transactionActionTrigger = 'true';
    button.dataset.detailUrl = detailUrl;
    button.dataset.packageUrl = packageUrl;
    button.className = 'transaction-action-button transaction-action-edit';
    button.setAttribute('title', 'Tampilkan aksi transaksi');
    button.setAttribute('aria-haspopup', 'dialog');
    button.innerHTML = '<span aria-hidden="true">⋯</span><span>Aksi</span>';
    bindTrigger(button, cell);
    cell.appendChild(button);
};

const initializeTransactionActionModal = (root = document) => {
    root.querySelectorAll?.(ACTION_CELL_SELECTOR).forEach(initializeActionCell);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initializeTransactionActionModal(), { once: true });
} else {
    initializeTransactionActionModal();
}

document.addEventListener('livewire:navigated', () => initializeTransactionActionModal());

new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (!(node instanceof Element)) return;
            if (node.matches(ACTION_CELL_SELECTOR)) initializeActionCell(node);
            initializeTransactionActionModal(node);
        });
    });
}).observe(document.body, { childList: true, subtree: true });
