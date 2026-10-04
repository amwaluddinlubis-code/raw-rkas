<div class="space-y-4">
    @if (!empty($syncFreshness))
        @php($freshnessBadge = match ($syncFreshness['level']) {
            'fresh' => 'SUCCESS', 'stale', 'incomplete' => 'WARNING', 'failed' => 'FAILED', 'running' => 'RUNNING', default => 'PENDING'
        })
        <section aria-label="Kesegaran sinkronisasi ARKAS"
            class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-sm font-bold text-[var(--ui-fg-strong)]">Kesegaran data ARKAS</h2>
                        <x-ui.status-badge :status="$freshnessBadge" :label="$syncFreshness['label']" size="xs" />
                    </div>
                    <p class="mt-1 text-xs leading-5 text-[var(--ui-fg-muted)]">
                        Referensi: {{ $syncFreshness['lanes']['referensi']['finished_at']?->translatedFormat('d M Y H:i') ?? 'belum tercatat' }}
                        · Sekolah: {{ $syncFreshness['lanes']['sekolah']['finished_at']?->translatedFormat('d M Y H:i') ?? 'belum tercatat' }}
                        · Batas mutakhir {{ $syncFreshness['stale_after_hours'] }} jam.
                    </p>
                </div>
                @if (auth()->user()?->role === 'ADMIN')
                    <a href="{{ route('arkas.mirror') }}" class="ui-btn ui-btn-ghost px-3 py-1.5 text-xs">Detail sinkronisasi</a>
                @endif
            </div>
            <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($syncFreshness['counts'] as $label => $count)
                    <div class="flex items-center justify-between gap-2 rounded-lg bg-[var(--ui-surface-soft)] px-3 py-2 text-xs">
                        <span class="text-[var(--ui-fg-muted)]">{{ $label }}</span>
                        <span class="font-semibold tabular-nums text-[var(--ui-fg-strong)]">{{ $count === null ? 'Tidak tersedia' : number_format($count, 0, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
            @if ($syncFreshness['missing'] !== [])
                <p class="mt-3 text-xs leading-5 text-[var(--ui-fg-muted)]">
                    Data/tabel perlu diperiksa: {{ implode(', ', $syncFreshness['missing']) }}.
                </p>
            @endif
            <div class="mt-2 grid gap-2 text-xs text-[var(--ui-fg-muted)] sm:grid-cols-2">
                @foreach ($syncFreshness['lanes'] as $label => $lane)
                    <p>{{ ucfirst($label) }}: {{ $lane['label'] }}
                        @if ($lane['records_read'] !== null)
                            · {{ number_format($lane['records_read'], 0, ',', '.') }} dibaca / {{ number_format($lane['records_written'], 0, ',', '.') }} ditulis
                        @endif
                    </p>
                @endforeach
            </div>
        </section>
    @endif

    @if (!empty($integrity) && !$integrity['ok'])
        <section aria-label="Integritas mirror ARKAS"
            class="rounded-2xl border border-amber-300 bg-amber-50 p-4 shadow-sm dark:border-amber-700 dark:bg-amber-950">
            <h2 class="text-sm font-bold text-amber-900 dark:text-amber-100">Perlu diperiksa: konsistensi data ARKAS</h2>
            <p class="mt-1 text-xs leading-5 text-amber-800 dark:text-amber-200">Realisasi/pagu di atas dihitung dari subset data yang tertaut benar. Baris berikut perlu ditindak di ARKAS atau saat sinkronisasi berikutnya.</p>
            <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                @foreach ($integrity['checks'] as $check)
                    @if (! $check['ok'])
                        <li class="rounded-lg border border-amber-200 bg-white/60 px-3 py-2 text-xs dark:border-amber-800 dark:bg-black/20">
                            <span class="font-semibold text-amber-900 dark:text-amber-100">{{ $check['label'] }}: {{ number_format($check['count'], 0, ',', '.') }}</span>
                            <p class="mt-0.5 text-amber-800 dark:text-amber-200">{{ $check['hint'] }}</p>
                        </li>
                    @endif
                @endforeach
            </ul>
        </section>
    @endif
</div>
