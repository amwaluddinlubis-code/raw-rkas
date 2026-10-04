{{-- Modal pratinjau dokumen generik (dipakai workspace paket + tabel laporan).
     Satu-satunya instance modal di halaman SPJ; tombol pemicu memakai atribut
     data-template-preview (URL fallback), data-template-preview-pdf (URL PDF),
     data-template-name (judul), dan
     data-close-template-preview. Handler JS terdelegasi penuh di document
     pada resources/views/spj/index.blade.php. --}}
<div id="template-preview-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="template-preview-title">
    <div class="flex h-[min(92vh,1100px)] w-full max-w-[1600px] flex-col overflow-hidden rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-2xl">
        <header class="flex shrink-0 items-center justify-between gap-3 border-b border-[var(--ui-line)] px-4 py-3">
            <div><p class="text-xs font-bold uppercase tracking-wide" style="color: var(--theme-content-accent)">Pratinjau Dokumen</p><h2 id="template-preview-title" class="mt-0.5 font-bold" style="color: var(--ui-fg-strong)">Dokumen SPJ</h2></div>
            <div class="flex items-center gap-2">
                <a id="template-preview-download-pdf" href="#" download class="ui-btn ui-btn-danger hidden min-h-10 items-center gap-2 px-3" title="Unduh PDF">
                    <x-ui.icon name="pdf" size="sm" /><span>PDF</span>
                </a>
                <a id="template-preview-download-excel" href="#" download class="ui-btn ui-btn-success hidden min-h-10 items-center gap-2 px-3" title="Unduh Excel">
                    <x-ui.icon name="excel" size="sm" /><span>Excel</span>
                </a>
                <button type="button" data-close-template-preview class="ui-btn ui-btn-secondary min-h-10 px-4">Tutup</button>
            </div>
        </header>
        <div class="min-h-0 flex-1 overflow-auto bg-[var(--ui-surface-muted)]"><iframe id="template-preview-frame" name="template-preview-frame" title="Pratinjau dokumen SPJ" class="h-full min-h-[760px] w-full border-0 bg-transparent"></iframe></div>
    </div>
</div>
