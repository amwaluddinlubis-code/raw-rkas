// Vendor memory for the package form (Data Umum Dokumen).
//
// When the operator types a vendor name, the latest tenant transaction with
// the same vendor is fetched and its vendor_owner / receipt_recipient_name
// are offered as recommendations. Only EMPTY fields are filled; operator
// data is never overwritten. The source (no_bukti + date) is shown so the
// operator can verify before saving.
const VENDOR_MIN_LENGTH = 3;
const DEBOUNCE_MS = 500;

const fillIfEmpty = (input, value, hint, sourceLabel) => {
    if (!(input instanceof HTMLInputElement)) return;
    if (input.value.trim() !== '' || !String(value || '').trim()) return;

    input.value = String(value).trim();
    input.dispatchEvent(new Event('input', { bubbles: true }));
    if (hint instanceof HTMLElement) {
        hint.textContent = `Rekomendasi dari ${sourceLabel} — boleh diubah.`;
        hint.classList.remove('hidden');
    }
};

const clearHint = (hint) => {
    if (hint instanceof HTMLElement) {
        hint.textContent = '';
        hint.classList.add('hidden');
    }
};

const fetchRecommendation = async (root) => {
    const url = root.dataset.vendorRecommendUrl;
    const transactionId = root.dataset.vendorTransactionId;
    const vendorInput = root.querySelector('[data-vendor-name-input]');
    if (!url || !transactionId || !(vendorInput instanceof HTMLInputElement)) return;

    const vendor = vendorInput.value.trim();
    const ownerInput = root.querySelector('[data-vendor-owner-input]');
    const recipientInput = root.querySelector('[data-vendor-recipient-input]');
    const ownerHint = root.querySelector('[data-vendor-owner-hint]');
    const recipientHint = root.querySelector('[data-vendor-recipient-hint]');
    clearHint(ownerHint);
    clearHint(recipientHint);
    if (vendor.length < VENDOR_MIN_LENGTH) return;
    if (ownerInput instanceof HTMLInputElement && ownerInput.value.trim() !== ''
        && recipientInput instanceof HTMLInputElement && recipientInput.value.trim() !== '') {
        return;
    }

    let data = null;
    try {
        const response = await fetch(
            `${url}?${new URLSearchParams({ vendor, transaction_id: transactionId })}`,
            { headers: { Accept: 'application/json' } },
        );
        if (!response.ok) return;
        data = await response.json();
    } catch {
        return;
    }
    if (!data || data.found !== true) return;

    const sourceLabel = [data.no_bukti, data.transaction_date].filter(Boolean).join(' · ') || 'transaksi terakhir';
    fillIfEmpty(ownerInput, data.vendor_owner, ownerHint, sourceLabel);
    fillIfEmpty(recipientInput, data.receipt_recipient_name, recipientHint, sourceLabel);
};

const bootVendorRecommendation = (root = document) => {
    const roots = root instanceof HTMLElement && root.matches('[data-vendor-recommendation]')
        ? [root]
        : Array.from(root.querySelectorAll?.('[data-vendor-recommendation]') ?? []);

    roots.forEach((section) => {
        if (section.dataset.vendorRecommendationBooted === 'true') return;
        section.dataset.vendorRecommendationBooted = 'true';

        const vendorInput = section.querySelector('[data-vendor-name-input]');
        if (!(vendorInput instanceof HTMLInputElement)) return;

        let timer = null;
        vendorInput.addEventListener('input', () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => fetchRecommendation(section), DEBOUNCE_MS);
        });

        // Prefilled drafts (e.g. synced vendor_name) get a recommendation on load.
        window.setTimeout(() => fetchRecommendation(section), DEBOUNCE_MS);
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => bootVendorRecommendation(document), { once: true });
} else {
    bootVendorRecommendation(document);
}

document.addEventListener('livewire:navigated', () => bootVendorRecommendation(document));

export { bootVendorRecommendation };
