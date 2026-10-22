// Pratinjau template & pratinjau massal paket SPJ (modal + hitungan pilihan
// massal maks. 20). Diekstrak dari <script> inline spj/index.blade.php agar
// index ramping dan perilaku ter-bundle bersama modul SPJ lain. Delegasi
// penuh di document dipertahankan persis: modal tetap berfungsi setelah
// navigasi SPA mengganti isi body (tanpa listener pada elemen).
    (() => {
        // Delegasi penuh di document agar modal tetap berfungsi setelah
        // navigasi SPA mengganti isi body (tanpa listener pada elemen).
        const templatePreview = (action, button) => {
            const modal = document.getElementById('template-preview-modal');
            if (!modal) return;
            const frame = document.getElementById('template-preview-frame');
            const title = document.getElementById('template-preview-title');
            const downloadPdf = document.getElementById('template-preview-download-pdf');
            const downloadExcel = document.getElementById('template-preview-download-excel');
            if (action === 'close') {
                modal.classList.add('hidden'); modal.classList.remove('flex');
                if (frame) frame.src = 'about:blank';
                [downloadPdf, downloadExcel].forEach((link) => {
                    if (!link) return;
                    link.href = '#';
                    link.classList.add('hidden');
                });
                return;
            }
            if (!button || !frame) return;
            if (title) title.textContent = button.dataset.templateName || 'Pratinjau Template';
            frame.src = button.dataset.templatePreviewPdf || button.dataset.templatePreview;
            [[downloadPdf, button.dataset.templateDownloadPdf], [downloadExcel, button.dataset.templateDownloadExcel]].forEach(([link, url]) => {
                if (!link) return;
                link.classList.toggle('hidden', !url);
                if (url) link.href = url;
            });
            modal.classList.remove('hidden'); modal.classList.add('flex');
        };
        const updateBulkSelection = () => {
            const selectedCount = document.querySelectorAll('[data-bulk-package]:checked').length;
            const status = document.querySelector('[data-bulk-selection-status]');
            const submit = document.querySelector('[data-bulk-submit]');
            const overLimit = selectedCount > 20;

            if (status) {
                const message = selectedCount === 0
                    ? '0 dari 20 paket dipilih. Pilih minimal 1 paket.'
                    : overLimit
                        ? `${selectedCount} paket dipilih. Maksimal 20; batalkan pilihan ${selectedCount - 20} paket.`
                        : `${selectedCount} dari 20 paket dipilih. Siap untuk Bulk Preview.`;
                if (status.textContent.trim() !== message) status.textContent = message;
                status.classList.toggle('text-rose-700', overLimit);
                status.classList.toggle('text-[var(--ui-fg-muted)]', !overLimit);
            }
            if (submit) submit.disabled = selectedCount === 0 || overLimit;

            return selectedCount;
        };
        document.addEventListener('click', (event) => {
            if (event.target.closest('[data-close-template-preview]')) {
                templatePreview('close');
                return;
            }
            const modal = document.getElementById('template-preview-modal');
            if (modal && !modal.classList.contains('hidden') && event.target === modal) {
                templatePreview('close');
                return;
            }
            const button = event.target.closest('[data-template-preview]');
            if (!button || button.closest('[inert]')) return;
            templatePreview('open', button);
        });
        document.addEventListener('change', (event) => {
            const selectAll = event.target.closest('[data-bulk-select-all]');
            if (selectAll) {
                const report = selectAll.closest('table');
                report?.querySelectorAll('[data-bulk-package]').forEach((checkbox) => { checkbox.checked = selectAll.checked; });
                updateBulkSelection();
                return;
            }
            if (event.target.matches('[data-bulk-package]')) {
                const report = event.target.closest('table');
                const boxes = [...(report?.querySelectorAll('[data-bulk-package]') || [])];
                const selectAllBox = report?.querySelector('[data-bulk-select-all]');
                if (selectAllBox) {
                    selectAllBox.checked = boxes.length > 0 && boxes.every((checkbox) => checkbox.checked);
                    selectAllBox.indeterminate = boxes.some((checkbox) => checkbox.checked) && !selectAllBox.checked;
                }
                updateBulkSelection();
            }
        });
        document.addEventListener('submit', (event) => {
            const form = event.target.closest('[data-bulk-preview-form]');
            if (!form) return;
            const selectedCount = updateBulkSelection();
            if (selectedCount === 0 || selectedCount > 20) {
                event.preventDefault();
                return;
            }
            const modal = document.getElementById('template-preview-modal');
            const title = document.getElementById('template-preview-title');
            const frame = document.getElementById('template-preview-frame');
            if (title) title.textContent = `Pratinjau Massal ${selectedCount} Paket SPJ`;
            if (frame) frame.src = 'about:blank';
            modal?.classList.remove('hidden');
            modal?.classList.add('flex');
        });
        updateBulkSelection();
        const bulkReportRoot = document.querySelector('[data-bulk-report-root]');
        if (bulkReportRoot && 'MutationObserver' in window) {
            new MutationObserver(updateBulkSelection).observe(bulkReportRoot, { childList: true, subtree: true });
        }
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape') templatePreview('close'); });
    })();
