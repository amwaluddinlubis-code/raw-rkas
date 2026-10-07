import { initializeDaisySelectDropdowns } from './daisy-select-dropdown';

// Satu initializer untuk semua halaman. Daisy-JS meng-enhance native
// single-select biasa (di luar x-ui.searchable-select yang sudah berupa
// komponen Blade); nilai tetap tersimpan pada select asli untuk
// kompatibilitas form dan Livewire.
//
// Menggantikan 12 alias per-halaman yang masing-masing memasang
// MutationObserver sendiri: cukup satu observer pada scope halaman aktif.
// Scope di-resolve ulang setiap panggilan (termasuk livewire:navigated)
// sehingga nilai data-page baru tetap terlayani tanpa tabel alias.
let observedScope = null;

const scopeForCurrentPage = () => document.querySelector('main[data-page]') || document;

const ensureScopeObserver = (scope) => {
    if (observedScope === scope) return;
    observedScope = scope;
    new MutationObserver(() => initializeDaisySelectDropdowns(scope)).observe(scope, {
        childList: true,
        subtree: true,
    });
};

export const initializePageSelectDropdowns = () => {
    const scope = scopeForCurrentPage();
    initializeDaisySelectDropdowns(scope);
    ensureScopeObserver(scope);
};
