<x-layouts.tailwind-app>
    <div class="flex flex-col gap-6">
        <x-page-header title="Monitor Kesehatan Importer" subtitle="Status run importer ARKAS terakhir per sekolah dan tabel sumber dana aktif. Read-only." kicker="Pengaturan" />
        <div class="grid gap-2 sm:grid-cols-3">
            <div class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-3 text-sm">Tabel stagnan (>7 hari): <strong>{{ number_format($stale_tables) }}</strong></div>
            <div class="rounded-xl border border-rose-300 bg-rose-50 p-3 text-sm text-rose-900">Tabel gagal: <strong>{{ number_format($failed_tables) }}</strong></div>
            <div class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-3 text-sm">Belum pernah sinkron: <strong>{{ number_format($never_run_tables) }}</strong></div>
        </div>
        <section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-4 shadow-sm overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead><tr class="text-[var(--ui-fg-muted)]"><th class="px-2 py-1.5 text-left">Sekolah</th><th class="px-2 py-1.5 text-left">Tabel</th><th class="px-2 py-1.5 text-left">Tahun</th><th class="px-2 py-1.5 text-left">Status</th><th class="px-2 py-1.5 text-right">Ditulis</th><th class="px-2 py-1.5 text-right">Dihapus</th><th class="px-2 py-1.5 text-left">Selesai</th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-t border-[var(--ui-line)]">
                            <td class="px-2 py-1">{{ $row['school'] }}</td>
                            <td class="px-2 py-1">{{ $row['table'] }}</td>
                            <td class="px-2 py-1">{{ $row['fiscal_year_id'] ?? '-' }}</td>
                            <td class="px-2 py-1">{{ $row['status'] }}{{ $row['stale'] ? ' · stagnan' : '' }}</td>
                            <td class="px-2 py-1 text-right tabular-nums">{{ number_format($row['records_written']) }}</td>
                            <td class="px-2 py-1 text-right tabular-nums">{{ number_format($row['records_removed']) }}</td>
                            <td class="px-2 py-1">{{ $row['finished_at']?->translatedFormat('d M Y H:i') ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    </div>
</x-layouts.tailwind-app>
