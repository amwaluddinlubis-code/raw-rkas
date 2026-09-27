// Staged goods receipt form (tab Penomoran): the item select only offers
// items with remaining quantity; the received value is computed from the
// source unit price and the quantity input is guarded by the remainder.
const formatNumber = (value) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 4 }).format(value);

const formatAccounting = (value) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(value);

const setAmount = (form, raw) => {
    const display = form.querySelector('[data-receipt-amount-display]');
    const hidden = form.querySelector('[data-receipt-amount]');
    if (display instanceof HTMLInputElement) {
        display.value = raw === '' || raw === null ? '' : formatAccounting(raw);
    }
    if (hidden instanceof HTMLInputElement) {
        hidden.value = raw === '' || raw === null ? '' : String(raw);
    }
};

const updateReceiptForm = (form) => {
    const itemSelect = form.querySelector('[data-receipt-item]');
    const qtyInput = form.querySelector('[data-receipt-qty]');
    const hint = form.querySelector('[data-receipt-hint]');
    if (!(itemSelect instanceof HTMLSelectElement) || !(qtyInput instanceof HTMLInputElement)) return;

    const option = itemSelect.selectedOptions[0];
    const remaining = Number(option?.dataset.remaining ?? NaN);
    const unit = String(option?.dataset.unit ?? '');
    const unitPrice = Number(option?.dataset.unitPrice ?? NaN);

    if (!option || option.value === '' || Number.isNaN(remaining)) {
        qtyInput.removeAttribute('max');
        setAmount(form, '');
        if (hint instanceof HTMLElement) {
            hint.textContent = '';
            hint.classList.remove('text-rose-700');
        }

        return;
    }

    qtyInput.max = String(remaining);
    const qty = Number(qtyInput.value || 0);
    setAmount(form, qty > 0 && !Number.isNaN(unitPrice) ? Math.round(qty * unitPrice * 100) / 100 : '');
    if (hint instanceof HTMLElement) {
        const over = qty > remaining;
        hint.textContent = over
            ? `Melebihi sisa ${formatNumber(remaining)}${unit ? ` ${unit}` : ''} — kurangi jumlah.`
            : `Sisa dapat diterima: ${formatNumber(remaining)}${unit ? ` ${unit}` : ''}.`;
        hint.classList.toggle('text-rose-700', over);
    }
};

const bootReceiptForms = (root = document) => {
    const forms = root instanceof HTMLElement && root.matches('[data-receipt-form]')
        ? [root]
        : Array.from(root.querySelectorAll?.('[data-receipt-form]') ?? []);

    forms.forEach((form) => {
        if (form.dataset.receiptFormBooted === 'true') return;
        form.dataset.receiptFormBooted = 'true';

        form.addEventListener('input', (event) => {
            if (event.target?.closest('[data-receipt-item], [data-receipt-qty]')) updateReceiptForm(form);
        });
        form.addEventListener('change', (event) => {
            if (event.target?.closest('[data-receipt-item]')) {
                const qtyInput = form.querySelector('[data-receipt-qty]');
                if (qtyInput instanceof HTMLInputElement) qtyInput.value = '';
                updateReceiptForm(form);
            }
        });
        updateReceiptForm(form);
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => bootReceiptForms(document), { once: true });
} else {
    bootReceiptForms(document);
}

document.addEventListener('livewire:navigated', () => bootReceiptForms(document));

export { bootReceiptForms };
