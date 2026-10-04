<div class="flex flex-col gap-6">
    <x-page-header title="Simulasi Pagu RKAS" subtitle="Uji dampak perubahan pagu per kegiatan terhadap triwulan. Read-only; tidak menulis ke ARKAS." kicker="Anggaran & Realisasi" />
    <section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-4 shadow-sm">
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([1, 2, 3, 4] as $quarter)
                <div class="rounded-lg bg-[var(--ui-surface-soft)] px-3 py-2 text-xs">
                    <p class="font-semibold text-[var(--ui-fg-strong)]">TW{{ $quarter }}</p>
                    <p>Saat ini: Rp {{ number_format($simulasi['current_quarters'][$quarter], 0, ',', '.') }}</p>
                    <p>Usulan: Rp {{ number_format($simulasi['proposed_quarters'][$quarter], 0, ',', '.') }}</p>
                    <p class="{{ $simulasi['delta_quarters'][$quarter] < 0 ? 'text-rose-700' : 'text-emerald-700' }}">Selisih: {{ number_format($simulasi['delta_quarters'][$quarter], 0, ',', '.') }}</p>
                </div>
            @endforeach
        </div>
    </section>
    <section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-4 shadow-sm overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead><tr class="text-[var(--ui-fg-muted)]"><th class="px-2 py-1.5 text-left">Kegiatan</th><th class="px-2 py-1.5 text-right">Pagu saat ini</th><th class="px-2 py-1.5 text-right">Pagu usulan</th></tr></thead>
            <tbody>
                @foreach ($simulasi['activities'] as $index => $activity)
                    <tr class="border-t border-[var(--ui-line)]">
                        <td class="px-2 py-1">{{ $activity['code'] }} · {{ $activity['name'] }}</td>
                        <td class="px-2 py-1 text-right tabular-nums">{{ number_format($activity['current'], 0, ',', '.') }}</td>
                        <td class="px-2 py-1 text-right"><input type="number" min="0" wire:model.live.debounce.300ms="overrides.{{ str_replace('.', '_', $activity['code']) }}" placeholder="{{ number_format($activity['current'], 0, ',', '.') }}" class="w-36 rounded border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-2 py-1 text-right"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
</div>
