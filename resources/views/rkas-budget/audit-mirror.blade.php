<x-layouts.tailwind-app>
    <div class="flex flex-col gap-6">
        <x-page-header title="Audit Mirror ARKAS" subtitle="Jejak perubahan antar pengesahan, periode terhapus, dan kas yatim. Read-only pendukung KPA." kicker="Anggaran & Realisasi">
            <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4">
                <x-stat-item label="Baris ditambahkan" :value="number_format($shapes['added']['rows'], 0, ',', '.')" :hint="'Rp '.number_format($shapes['added']['amount'], 0, ',', '.')" value-class="text-emerald-700" icon="plus" icon-class="text-emerald-700" />
                <x-stat-item label="Baris dihapus" :value="number_format($shapes['removed']['rows'], 0, ',', '.')" :hint="'Rp '.number_format($shapes['removed']['amount'], 0, ',', '.')" value-class="text-rose-700" icon="trash" icon-class="text-rose-700" />
                <x-stat-item label="Baris berubah" :value="number_format($shapes['changed']['rows'], 0, ',', '.')" :hint="'Δ Rp '.number_format($shapes['changed']['delta'], 0, ',', '.')" value-class="text-[var(--theme-content-accent)]" icon="refresh" icon-class="text-[var(--theme-content-accent)]" />
                <x-stat-item label="Perlu perhatian" :value="number_format($soft_deleted_periods, 0, ',', '.')" :hint="'Periode terhapus · Kas yatim: '.number_format($orphan_kas_rows, 0, ',', '.')" value-class="text-amber-700" icon="warning" icon-class="text-amber-700" />
            </div>
        </x-page-header>
        <p class="text-xs text-[var(--ui-fg-muted)]">Revisi terakhir: {{ $latest_revision ?? '-' }} · Sebelumnya: {{ $previous_revision ?? '-' }}</p>
    </div>
</x-layouts.tailwind-app>
