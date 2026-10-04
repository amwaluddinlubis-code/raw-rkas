<x-layouts.tailwind-app>
    <x-page-header title="Perbandingan Revisi RKAS"
        subtitle="Bandingkan perubahan pagu, volume, dan alokasi bulanan pada dua snapshot revisi yang sama konteksnya."
        kicker="Penganggaran RKAS">
        <x-slot:actions>
            <a href="{{ route('rkas-budget.index') }}" class="ui-btn ui-btn-secondary px-3 py-2 text-sm">
                <x-ui.icon name="arrow-left" size="sm" /> Kembali ke RKAS
            </a>
        </x-slot:actions>
    </x-page-header>

    <section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-4 shadow-sm sm:p-5">
        <div class="mb-4">
            <h2 class="font-bold text-[var(--ui-fg-strong)]">Pilih dua revisi</h2>
            <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">{{ $year }} · {{ $fundName ?: 'Sumber dana aktif' }}. Perbandingan hanya mencakup pagu dan alokasi RKAS; realisasi BKU tidak dicampur lintas revisi.</p>
        </div>
        <form method="GET" action="{{ route('rkas-budget.revisions.compare') }}" class="grid gap-3 md:grid-cols-[1fr_auto_1fr_auto] md:items-end">
            <x-ui.field label="Dari revisi" for="revision-from" required>
                <x-ui.select id="revision-from" name="from" required>
                    <option value="">Pilih revisi awal</option>
                    @foreach ($revisions as $revisionOption)
                        <option value="{{ $revisionOption['id'] }}" @selected($selectedFrom === $revisionOption['id'])>
                            {{ $revisionOption['status'] === 'pending' ? 'Pengajuan' : 'Pengesahan' }} ke-{{ $revisionOption['seq'] }} · {{ $revisionOption['dateLabel'] }} · Rp {{ number_format($revisionOption['amount'], 0, ',', '.') }}
                        </option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <span class="hidden pb-2 text-sm text-[var(--ui-fg-muted)] md:block" aria-hidden="true">ke</span>
            <x-ui.field label="Ke revisi" for="revision-to" required>
                <x-ui.select id="revision-to" name="to" required>
                    <option value="">Pilih revisi tujuan</option>
                    @foreach ($revisions as $revisionOption)
                        <option value="{{ $revisionOption['id'] }}" @selected($selectedTo === $revisionOption['id'])>
                            {{ $revisionOption['status'] === 'pending' ? 'Pengajuan' : 'Pengesahan' }} ke-{{ $revisionOption['seq'] }} · {{ $revisionOption['dateLabel'] }} · Rp {{ number_format($revisionOption['amount'], 0, ',', '.') }}
                        </option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.button type="submit" icon="report" class="mb-0.5">Bandingkan</x-ui.button>
        </form>
    </section>

    @if (count($revisions) < 2)
        <x-ui.empty-state title="Belum ada dua revisi untuk dibandingkan"
            description="Sinkronkan data ARKAS atau pilih konteks tahun dan sumber dana yang memiliki lebih dari satu revisi." icon="info" />
    @elseif ($comparison)
        <section aria-label="Summary" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => 'Pagu revisi awal', 'value' => $comparison['totals']['from']],
                ['label' => 'Pagu revisi tujuan', 'value' => $comparison['totals']['to']],
                ['label' => 'Selisih pagu', 'value' => $comparison['totals']['delta']],
            ] as $summary)
                <div class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-4 py-3 shadow-sm">
                    <p class="text-xs font-semibold text-[var(--ui-fg-muted)]">{{ $summary['label'] }}</p>
                    <p class="mt-1 text-lg font-bold tabular-nums text-[var(--ui-fg-strong)]">Rp {{ number_format($summary['value'], 0, ',', '.') }}</p>
                </div>
            @endforeach
            <div class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-4 py-3 shadow-sm">
                <p class="text-xs font-semibold text-[var(--ui-fg-muted)]">Perubahan pos</p>
                <p class="mt-1 text-lg font-bold tabular-nums text-[var(--ui-fg-strong)]">
                    +{{ $comparison['counts']['added'] }} / −{{ $comparison['counts']['removed'] }} / ~{{ $comparison['counts']['changed'] }}
                </p>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <div class="border-b border-[var(--ui-line)] px-4 py-3">
                <h2 class="font-bold text-[var(--ui-fg-strong)]">Rincian perubahan</h2>
                <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Identitas pos memakai ID referensi, kode rekening, dan uraian; pos yang identitasnya berubah akan terlihat sebagai dihapus dan ditambahkan.</p>
            </div>
            <x-ui.table pagination="none" compact>
                <thead>
                    <tr><th>Status</th><th>Kode / uraian</th><th class="text-right">Pagu awal</th><th class="text-right">Pagu tujuan</th><th class="text-right">Selisih</th><th>Volume</th><th>Perubahan alokasi</th></tr>
                </thead>
                <tbody>
                    @forelse ($comparison['rows'] as $row)
                        <tr>
                            <td><x-ui.status-badge :status="match($row['status']) { 'added' => 'SUCCESS', 'removed' => 'FAILED', default => 'WARNING' }" :label="match($row['status']) { 'added' => 'Ditambahkan', 'removed' => 'Dihapus', default => 'Berubah' }" size="xs" /></td>
                            <td>
                                <p class="font-mono text-xs text-[var(--ui-fg-muted)]">{{ $row['activity_code'] }} · {{ $row['account_code'] }}</p>
                                <p class="font-semibold text-[var(--ui-fg-strong)]">{{ $row['description'] ?: $row['activity_name'] }}</p>
                            </td>
                            <td class="text-right tabular-nums">Rp {{ number_format($row['from_amount'], 0, ',', '.') }}</td>
                            <td class="text-right tabular-nums">Rp {{ number_format($row['to_amount'], 0, ',', '.') }}</td>
                            <td class="text-right font-semibold tabular-nums">Rp {{ number_format($row['amount_delta'], 0, ',', '.') }}</td>
                            <td class="tabular-nums">{{ number_format($row['from_volume'], 2, ',', '.') }} {{ $row['from_unit'] }} → {{ number_format($row['to_volume'], 2, ',', '.') }} {{ $row['to_unit'] }}</td>
                            <td>
                                @forelse ($row['periods'] as $period)
                                    <div class="whitespace-nowrap text-xs tabular-nums">{{ \App\Services\RkasReportService::MONTH_NAMES[$period['month'] - 1] }}: Rp {{ number_format($period['delta'], 0, ',', '.') }} · vol {{ number_format($period['volume_delta'], 2, ',', '.') }}</div>
                                @empty
                                    <span class="text-xs text-[var(--ui-fg-muted)]">—</span>
                                @endforelse
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-sm text-[var(--ui-fg-muted)]">Tidak ada perubahan pagu, volume, atau alokasi pada dua revisi ini.</td></tr>
                    @endforelse
                </tbody>
            </x-ui.table>
        </section>
    @endif
</x-layouts.tailwind-app>
