import { initializeDaisySelectDropdowns } from './daisy-select-dropdown';

const initializeSelectDropdownsForPage = (pageKey) => {
    const page = document.querySelector(`main[data-page="${pageKey}"]`);
    if (!page) return;

    initializeDaisySelectDropdowns(page);

    const observerKey = `${pageKey.replace(/[^a-zA-Z0-9]/g, '')}SelectObserverInitialized`;
    if (page.dataset[observerKey] === 'true') return;
    page.dataset[observerKey] = 'true';
    new MutationObserver(() => initializeDaisySelectDropdowns(page)).observe(page, {
        childList: true,
        subtree: true,
    });
};

export const initializeSpjSelectDropdowns = () => initializeSelectDropdownsForPage('spj');
export const initializeTransactionsSelectDropdowns = () => initializeSelectDropdownsForPage('transactions');
export const initializeRkasSelectDropdowns = () => initializeSelectDropdownsForPage('rkas');
export const initializeDashboardSelectDropdowns = () => initializeSelectDropdownsForPage('dashboard');
export const initializeEmployeesSelectDropdowns = () => initializeSelectDropdownsForPage('employees');
export const initializeStudentsSelectDropdowns = () => initializeSelectDropdownsForPage('students');
export const initializeReconciliationSelectDropdowns = () => initializeSelectDropdownsForPage('reconciliation');
export const initializeDatabaseSelectDropdowns = () => initializeSelectDropdownsForPage('database');
export const initializeAuditSelectDropdowns = () => initializeSelectDropdownsForPage('audit');
export const initializeDocumentsSelectDropdowns = () => initializeSelectDropdownsForPage('documents');
export const initializeSyncedDataSelectDropdowns = () => initializeSelectDropdownsForPage('synced-data');
export const initializeApplicationSelectDropdowns = () => initializeSelectDropdownsForPage('application');

export const initializePageSelectDropdowns = () => {
    const pageKey = document.querySelector('main[data-page]')?.dataset.page;
    const initializers = {
        spj: initializeSpjSelectDropdowns,
        transactions: initializeTransactionsSelectDropdowns,
        rkas: initializeRkasSelectDropdowns,
        dashboard: initializeDashboardSelectDropdowns,
        employees: initializeEmployeesSelectDropdowns,
        students: initializeStudentsSelectDropdowns,
        reconciliation: initializeReconciliationSelectDropdowns,
        database: initializeDatabaseSelectDropdowns,
        audit: initializeAuditSelectDropdowns,
        documents: initializeDocumentsSelectDropdowns,
        'synced-data': initializeSyncedDataSelectDropdowns,
        application: initializeApplicationSelectDropdowns,
    };

    initializers[pageKey]?.();
};
