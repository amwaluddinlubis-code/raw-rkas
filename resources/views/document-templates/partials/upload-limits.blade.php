<section x-data="{ open: true }" class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] shadow-sm">
    <div class="flex flex-col gap-3 border-b border-[var(--ui-line)] px-4 py-3 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm font-bold text-[var(--ui-fg-strong)]">Status batas upload server</p>
            <p class="mt-1 text-xs leading-5 text-[var(--ui-fg-muted)]">PHP upload_max_filesize = <span class="font-mono font-bold">{{ $uploadLimits['upload_max_filesize'] ?? '-' }}</span> · post_max_size = <span class="font-mono font-bold">{{ $uploadLimits['post_max_size'] ?? '-' }}</span> · batas efektif ≈ <span class="font-bold">{{ $uploadLimits['effective_max_upload'] ?? '-' }}</span>.</p>
        </div>
        <x-ui.panel-toggle panel="Status batas upload server" controls="document-templates-upload-limits" />
    </div>
    <div x-show="open" id="document-templates-upload-limits" class="px-4 py-3">
        <p class="max-w-3xl text-xs leading-5 text-[var(--ui-fg-muted)]">Jika upload ditolak sebelum Laravel menerima file, halaman menampilkan batas PHP pada form yang benar.</p>
    </div>
</section>
