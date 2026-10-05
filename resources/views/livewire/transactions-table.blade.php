<div class="space-y-6">
    @php($rupiah = fn($value) => 'Rp ' . number_format((float) $value, 0, ',', '.'))
    @php(
    $spjTypeLabel = fn($value) => match (strtoupper((string) $value)) {
        'HONOR_PEGAWAI' => 'Honor Pegawai',
        'JASA_LAINNYA' => 'Jasa Lainnya',
        'BARANG' => 'Barang',
        'KONSUMSI' => 'Konsumsi',
        'PEMELIHARAAN' => 'Pemeliharaan',
        'SPPD' => 'SPPD',
        default => ucwords(strtolower(str_replace('_', ' ', (string) $value)))
    }
)

    <x-page-header title="Transaksi & SPJ"
        subtitle="Mulai dari transaksi, lengkapi data SPJ, lalu lanjutkan ke paket dokumen tanpa berpindah alur."
        kicker="BUKU KAS & SPJ">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="database" :href="route('synced-data.show', 'bku')" wire:navigate>
                Lihat BKU Mentah
            </x-ui.button>
            <x-ui.button icon="document" :href="route('spj.index')">
                Buka Ruang Kerja SPJ
            </x-ui.button>
        </x-slot:actions>

        <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-2 lg:grid-cols-4 sm:divide-x sm:divide-y-0">
            <x-stat-item label="Transaksi" :value="number_format($filteredStats->count, 0, ',', '.')" hint="Hasil filter aktif" value-class="text-[var(--ui-fg-strong)]"
                icon="transaction" icon-class="text-[var(--ui-fg-strong)]" />
            <x-stat-item label="Nilai Bruto" :value="$rupiah($filteredStats->gross)" hint="Total nilai hasil filter"
                value-class="text-[var(--theme-content-accent)]" icon="budget" icon-class="text-[var(--theme-content-accent)]" />
            <x-stat-item label="Pajak" :value="$rupiah($filteredStats->tax)" hint="Total pajak hasil filter" value-class="text-amber-600"
                icon="tax" icon-class="text-amber-600" />
            <x-stat-item label="Dibayarkan" :value="$rupiah($filteredStats->net)" hint="Nilai bersih hasil filter"
                value-class="text-emerald-700" icon="balance" icon-class="text-emerald-700" />
        </div>
    </x-page-header>

    <section class="ui-filter-panel">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[var(--ui-line)] px-5 py-3">
            <p class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide"
                style="color: var(--theme-content-accent)">
                <x-ui.icon name="filter" size="sm" />
                <span>Filter Transaksi</span>
            </p>
        </div>
        <div class="flex flex-wrap items-end gap-2 p-5">
            <div class="space-y-1.5">
                <span class="block text-sm font-semibold text-[var(--ui-fg-strong)]"
                    id="transaction-mode-label">Periode</span>
                <div class="ui-segment-group flex w-fit max-w-full overflow-x-auto" role="group"
                    aria-labelledby="transaction-mode-label">
                    @foreach ($this->modes() as [$modeOption, $label])
                        <button type="button" wire:click="setMode('{{ $modeOption }}')"
                            aria-pressed="{{ $mode === $modeOption ? 'true' : 'false' }}"
                            class="whitespace-nowrap border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 py-2 text-sm transition {{ $loop->first ? '' : '-ml-px' }} {{ $mode === $modeOption ? 'font-bold text-[var(--theme-content-accent)]' : 'font-medium text-[var(--ui-fg-muted)] hover:text-[var(--ui-fg-strong)]' }}"
                            style="{{ $mode === $modeOption ? 'box-shadow: inset 0 -3px 0 var(--theme-action-bg);' : '' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            @if ($mode === 'bulan')
                <x-ui.field label="Bulan" for="transaction-month" class="min-w-[12rem]">
                    <x-ui.select id="transaction-month" wire:model.live="month">
                        <option value="">Pilih bulan…</option>
                        @foreach (range(1, 12) as $monthOption)
                            <option value="{{ $monthOption }}">
                                {{ \Carbon\Carbon::create()->month($monthOption)->translatedFormat('F') }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
            @elseif ($mode === 'triwulan')
                <x-ui.field label="Triwulan" for="transaction-quarter" class="min-w-[12rem]">
                    <x-ui.select id="transaction-quarter" wire:model.live="quarter">
                        <option value="">Pilih triwulan…</option>
                        @foreach (range(1, 4) as $quarterOption)
                            <option value="{{ $quarterOption }}">Triwulan {{ $quarterOption }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
            @elseif ($mode === 'semester')
                <x-ui.field label="Semester" for="transaction-semester" class="min-w-[12rem]">
                    <x-ui.select id="transaction-semester" wire:model.live="semester">
                        <option value="">Pilih semester…</option>
                        <option value="1">Semester 1</option>
                        <option value="2">Semester 2</option>
                    </x-ui.select>
                </x-ui.field>
            @endif
            <x-ui.field label="Cari transaksi" for="transaction-search" class="min-w-[11rem] flex-1">
                <x-ui.input id="transaction-search" wire:model.live.debounce.400ms="q"
                    placeholder="Nomor bukti, uraian, penerima..." />
            </x-ui.field>
            <x-ui.field label="Status" for="transaction-status" class="min-w-[10rem]">
                <x-ui.select id="transaction-status" wire:model.live="status">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.button type="button" variant="secondary" icon="refresh" wire:click="clearFilters">
                Reset Filter
            </x-ui.button>
        </div>

    </section>

    <section class="overflow-hidden border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
        <x-ui.toolbar class="border-b-0 bg-[var(--ui-surface-soft)] px-4 py-3 sm:px-5">
            <div>
                <h2 class="flex items-center gap-2 text-base font-bold" style="color: var(--ui-fg)">
                    <x-ui-icon name="transaction" class="h-5 w-5" />
                    <span>Daftar Transaksi SPJ</span>
                </h2>
                <p class="mt-0.5 text-sm" style="color: var(--ui-fg-muted)">Semua transaksi yang belum bernomor tetap di
                    depan; yang sudah bernomor dipindahkan ke belakang.</p>
            </div>
            <x-slot:actions>
                <div class="flex min-w-[9rem] items-center gap-2">
                    <x-ui-icon name="queue" class="h-4 w-4" style="color: var(--ui-fg-muted)" />
                    <label for="transaction-per-page" class="sr-only">Baris per halaman</label>
                    <x-ui.select id="transaction-per-page" wire:model.live="perPage" class="!w-auto !py-1.5 text-sm">
                        <option value="15">15 baris</option>
                        <option value="25">25 baris</option>
                        <option value="50">50 baris</option>
                        <option value="100">100 baris</option>
                        <option value="all">Semua</option>
                    </x-ui.select>
                </div>
            </x-slot:actions>
        </x-ui.toolbar>

        {{-- Mobile cards --}}
        <div class="grid gap-2 border-t border-[var(--ui-line)] p-3 lg:hidden">
            @if ($transactions->count() > 0)
                @foreach ($transactions as $transaction)
                    @php($workStatus = $this->workStatusFor($transaction))
                    <article class="border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 py-3"
                        wire:key="transaction-card-{{ $transaction->id }}">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex min-w-0 items-center gap-2">
                                <span
                                    class="font-mono text-[13px] font-bold text-[var(--ui-fg-muted)]">#{{ $transaction->id }}</span>
                                <x-ui.status-badge :status="$workStatus['status']" :label="$workStatus['label']" size="xs" />
                            </div>
                            <span class="text-[13px]"
                                style="color: var(--ui-fg-muted)">{{ $transaction->sourceCarbon()?->format('d/m/Y') ?? '—' }}</span>
                        </div>
                        <div class="mt-1 flex items-center gap-2 text-[13px]">
                            <span class="font-mono font-bold"
                                style="color: var(--theme-content-accent)">{{ $transaction->sourceValue('no_bukti') }}</span>
                            @if ($this->paymentMethodFor($transaction) === 'siplah')
                                <span class="text-[13px]" style="color: var(--ui-fg-muted)">· SiPLah</span>
                            @endif
                        </div>
                        <p class="mt-1 truncate text-sm font-semibold" style="color: var(--ui-fg)">
                            {{ $transaction->sourceValue('description') ?: 'Tanpa uraian ARKAS' }}</p>
                        <p class="mt-0.5 truncate text-[13px]"
                            style="color: {{ filled($transaction->payment_description) ? 'var(--theme-content-accent)' : 'var(--ui-fg-muted)' }}">
                            SPJ: {{ $transaction->payment_description ?: 'Deskripsi belanja belum diisi' }}</p>
                        <div class="mt-1 flex items-start justify-between gap-3 text-[13px]"
                            style="color: var(--ui-fg-muted)">
                            <span
                                class="min-w-0 truncate">{{ $transaction->spj_category ? $spjTypeLabel($transaction->spj_category) . ' · ' : '' }}{{ $transaction->items_count }}
                                item ·
                                {{ $transaction->effective_receipt_recipient_name ?: $transaction->sourceValue('recipient_name') ?: 'Penerima belum diisi' }}</span>
                            <div class="shrink-0 text-right">
                                <p class="font-semibold" style="color: var(--ui-fg)">Total Transaksi:
                                    {{ $rupiah($transaction->sourceValue('gross_amount')) }}</p>
                                <p class="mt-0.5 font-semibold text-amber-700">Pajak:
                                    {{ $rupiah($transaction->sourceValue('tax_total')) }}</p>
                            </div>
                        </div>
                        <div
                            class="transaction-action-cell mt-3 flex justify-end border-t border-[var(--ui-line)] pt-3">
                            <button type="button" data-transaction-action-trigger
                                data-detail-url="{{ route('transactions.show', $transaction) }}"
                                data-package-url="{{ route('transactions.prepare-spj', $transaction->id) }}"
                                class="transaction-action-button transaction-action-edit"
                                title="Tampilkan aksi transaksi" aria-haspopup="dialog">
                                <span aria-hidden="true">⋯</span>
                                <span>Aksi</span>
                                <span class="sr-only">Paket SPJ Detail</span>
                            </button>
                        </div>
                    </article>
                @endforeach
            @else
                <div class="border border-dashed p-8 text-center">
                    <x-ui-icon name="inbox" class="mx-auto h-7 w-7" style="color: var(--ui-fg-muted)" />
                    <p class="mt-2 text-sm font-semibold" style="color: var(--ui-fg)">Transaksi belum ditemukan.</p>
                    <p class="mt-1 text-sm" style="color: var(--ui-fg-muted)">Coba ubah filter atau sinkron ARKAS.</p>
                </div>
            @endif
        </div>

        {{-- Desktop table --}}
        <div class="hidden overflow-x-auto border-t border-[var(--ui-line)] lg:block">
            <x-ui.table pagination="server">
                <colgroup>
                    <col class="w-[180px]">
                    <col>
                    <col class="w-[220px]">
                    <col class="w-[200px]">
                </colgroup>
                <thead class="bg-[var(--ui-surface-soft)]">
                    <tr class="border-b border-[var(--ui-line)]">
                        <th class="px-4 py-2 text-left text-[13px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">ID
                            / Status</th>
                        <th class="px-4 py-2 text-left text-[13px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">
                            Uraian / Referensi</th>
                        <th class="px-4 py-2 text-right text-[13px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">
                            Nilai</th>
                        <th
                            class="transaction-action-column px-4 py-2 text-center text-[13px] font-bold uppercase tracking-wide">
                            Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--ui-line)] bg-[var(--ui-surface-base)]">
                    @if ($transactions->count() > 0)
                        @foreach ($transactions as $transaction)
                            @php($workStatus = $this->workStatusFor($transaction))
                            <tr class="transition hover:bg-[var(--ui-surface-soft)]"
                                wire:key="transaction-row-{{ $transaction->id }}">
                                <td class="px-4 py-2 align-middle">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span
                                            class="shrink-0 font-mono text-[13px] font-bold text-[var(--ui-fg-muted)]">#{{ $transaction->id }}</span>
                                        <div class="min-w-0"><x-ui.status-badge :status="$workStatus['status']" :label="$workStatus['label']"
                                                size="xs" /></div>
                                    </div>
                                    <p class="mt-1 truncate text-[13px]" style="color: var(--ui-fg-muted)">
                                        <span class="font-mono font-bold"
                                            style="color: var(--theme-content-accent)">{{ $transaction->sourceValue('no_bukti') }}</span>
                                        ·
                                        {{ $transaction->sourceCarbon()?->format('d/m/Y') ?? '—' }}{{ $this->paymentMethodFor($transaction) === 'siplah' ? ' · SiPLah' : '' }}
                                    </p>
                                </td>
                                <td class="min-w-0 px-4 py-2 align-middle">
                                    <p class="truncate text-sm font-semibold" style="color: var(--ui-fg)"
                                        title="{{ $transaction->sourceValue('description') }}">
                                        {{ $transaction->sourceValue('description') ?: 'Tanpa uraian ARKAS' }}</p>
                                    <p class="mt-1 truncate text-[13px]"
                                        style="color: {{ filled($transaction->payment_description) ? 'var(--theme-content-accent)' : 'var(--ui-fg-muted)' }}"
                                        title="{{ $transaction->payment_description }}">SPJ:
                                        {{ $transaction->payment_description ?: 'Deskripsi belanja belum diisi' }}</p>
                                    <p class="mt-1 truncate text-[13px]" style="color: var(--ui-fg-muted)">
                                        {{ $transaction->sourceValue('activity_code') ?: '—' }} ·
                                        {{ $transaction->sourceValue('account_code') ?: 'Rekening belum tersedia' }} ·
                                        {{ $transaction->spj_category ? $spjTypeLabel($transaction->spj_category) . ' · ' : '' }}{{ $transaction->items_count }}
                                        item ·
                                        {{ $transaction->effective_receipt_recipient_name ?: $transaction->sourceValue('recipient_name') ?: 'Penerima belum diisi' }}{{ $transaction->requires_reconciliation ? ' · Rekonsiliasi' : '' }}
                                    </p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right align-middle">
                                    <p class="text-sm font-semibold" style="color: var(--ui-fg)"><span
                                            class="font-medium" style="color: var(--ui-fg-muted)">Total
                                            Transaksi:</span> {{ $rupiah($transaction->sourceValue('gross_amount')) }}
                                    </p>
                                    <p class="mt-1 text-[13px] font-semibold text-amber-700"><span
                                            class="font-medium">Pajak:</span>
                                        {{ $rupiah($transaction->sourceValue('tax_total')) }}</p>
                                </td>
                                <td class="transaction-action-column px-3 py-2 align-middle">
                                    <div class="transaction-action-cell flex items-center justify-center"
                                        aria-label="Aksi transaksi {{ $transaction->sourceValue('no_bukti') }}">
                                        <button type="button" data-transaction-action-trigger
                                            data-detail-url="{{ route('transactions.show', $transaction) }}"
                                            data-package-url="{{ route('transactions.prepare-spj', $transaction->id) }}"
                                            class="transaction-action-button transaction-action-edit"
                                            title="Tampilkan aksi transaksi" aria-haspopup="dialog">
                                            <span aria-hidden="true">⋯</span>
                                            <span>Aksi</span>
                                            <span class="sr-only">Paket SPJ Detail</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="4" class="px-5 py-6 text-center">
                                <x-ui-icon name="inbox" class="mx-auto h-7 w-7"
                                    style="color: var(--ui-fg-muted)" />
                                <p class="mt-2 text-sm font-semibold" style="color: var(--ui-fg)">Transaksi belum
                                    ditemukan.</p>
                                <p class="mt-1 text-sm" style="color: var(--ui-fg-muted)">Jalankan Sinkron Semua ARKAS
                                    atau ubah filter pencarian.</p>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </x-ui.table>
        </div>

        <x-ui.server-pagination :paginator="$transactions" noun="transaksi" />
    </section>

</div>
