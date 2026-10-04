<x-layouts.tailwind-app>
    <div class="flex flex-col gap-6">
        <x-page-header title="Audit Mirror ARKAS" subtitle="Jejak perubahan antar pengesahan, periode terhapus, dan kas yatim. Read-only pendukung KPA." kicker="Anggaran & Realisasi" />
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-3 text-sm">Baris ditambahkan: <strong>{{ number_format($shapes['added']['rows']) }}</strong> · Rp {{ number_format($shapes['added']['amount'], 0, ',', '.') }}</div>
            <div class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-3 text-sm">Baris dihapus: <strong>{{ number_format($shapes['removed']['rows']) }}</strong> · Rp {{ number_format($shapes['removed']['amount'], 0, ',', '.') }}</div>
            <div class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-3 text-sm">Baris berubah: <strong>{{ number_format($shapes['changed']['rows']) }}</strong> · Δ Rp {{ number_format($shapes['changed']['delta'], 0, ',', '.') }}</div>
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">Periode soft-deleted: <strong>{{ number_format($soft_deleted_periods) }}</strong> · Kas yatim: <strong>{{ number_format($orphan_kas_rows) }}</strong></div>
        </div>
        <p class="text-xs text-[var(--ui-fg-muted)]">Revisi terakhir: {{ $latest_revision ?? '-' }} · Sebelumnya: {{ $previous_revision ?? '-' }}</p>
    </div>
</x-layouts.tailwind-app>
