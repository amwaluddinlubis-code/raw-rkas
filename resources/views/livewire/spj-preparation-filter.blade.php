@php
    $rupiah = fn($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $spjTypeLabel = fn($value) => match (strtoupper((string) $value)) {
        'JASA_HONORARIUM', 'HONOR_PEGAWAI' => 'Honor Pegawai',
        'JASA_LAINNYA' => 'Jasa Lainnya',
        'BARANG' => 'Barang',
        'KONSUMSI' => 'Konsumsi',
        'PEMELIHARAAN' => 'Pemeliharaan',
        'SPPD' => 'SPPD',
        default => ucwords(strtolower(str_replace('_', ' ', (string) $value))),
    };
@endphp
<div>
    <div class="border-b border-[var(--ui-line)] px-5 py-5 sm:px-6">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="font-bold text-[var(--ui-fg-strong)]">Antrean persiapan SPJ</h2>
                <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Pilih transaksi, periksa rincian, lalu siapkan paket
                    dokumennya.
                </p>
            </div>
            <p class="max-w-xl text-xs font-medium text-[var(--ui-fg-muted)]">Prioritas: perlu dilengkapi → belum
                dikerjakan →
                sudah bernomor. Dalam setiap kelompok, tanggal terlama tampil lebih dahulu.</p>
        </div>
        <nav class="spj-work-queue mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-5" aria-label="Pilih pekerjaan SPJ">
            <button type="button" wire:click="setQueueState('all')"
                aria-current="{{ $state === 'all' ? 'true' : 'false' }}"
                class="spj-work-queue-item w-full cursor-pointer text-left"><span>Semua
                    pekerjaan</span><strong>{{ $workQueueCounts['all'] ?? 0 }}</strong></button>
            <button type="button" wire:click="setQueueState('needs_details')"
                aria-current="{{ $state === 'needs_details' ? 'true' : 'false' }}"
                class="spj-work-queue-item w-full cursor-pointer text-left"><span>Perlu perhatian:<br>rincian belum
                    ada</span><strong>{{ $workQueueCounts['needs_details'] ?? 0 }}</strong></button>
            <button type="button" wire:click="setQueueState('unprepared')"
                aria-current="{{ $state === 'unprepared' ? 'true' : 'false' }}"
                class="spj-work-queue-item w-full cursor-pointer text-left"><span>Belum
                    dikerjakan</span><strong>{{ $workQueueCounts['unprepared'] ?? 0 }}</strong></button>
            <button type="button" wire:click="setQueueState('draft')"
                aria-current="{{ $state === 'draft' ? 'true' : 'false' }}"
                class="spj-work-queue-item w-full cursor-pointer text-left"><span>Perlu
                    dilengkapi</span><strong>{{ $workQueueCounts['draft'] ?? 0 }}</strong></button>
            <button type="button" wire:click="setQueueState('numbered')"
                aria-current="{{ $state === 'numbered' ? 'true' : 'false' }}"
                class="spj-work-queue-item w-full cursor-pointer text-left"><span>Sudah
                    bernomor</span><strong>{{ $workQueueCounts['numbered'] ?? 0 }}</strong></button>
        </nav>
        <div class="spj-filter-bar mt-3 rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
                <x-ui.field label="Cari bukti / uraian / vendor" for="spj-preparation-search"><x-ui.input id="spj-preparation-search"
                        type="search" placeholder="No. bukti, uraian, vendor…" autocomplete="off"
                        wire:model.live.debounce.300ms="search" />
                </x-ui.field>
                <x-ui.field label="Bulan" for="spj-preparation-month"><x-ui.select id="spj-preparation-month"
                        wire:model.live="month">
                        <option value="">Semua bulan</option>
                        @foreach (['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] as $index => $month)
                            <option value="{{ $index + 1 }}">{{ $month }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="Triwulan" for="spj-preparation-quarter"><x-ui.select id="spj-preparation-quarter"
                        wire:model.live="quarter">
                        <option value="">Semua triwulan</option>
                        @foreach (range(1, 4) as $quarter)
                            <option value="{{ $quarter }}">Triwulan
                                {{ $quarter }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="Kategori" for="spj-preparation-category"><x-ui.select id="spj-preparation-category"
                        wire:model.live="spj_category">
                        <option value="">Semua jenis SPJ</option>
                        @foreach ($spjTypes ?? [] as $type)
                            <option value="{{ $type }}">
                                {{ $spjTypeLabel($type) }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="Status" for="spj-preparation-state"><x-ui.select id="spj-preparation-state"
                        wire:model.live="state">
                        <option value="all">Semua status</option>
                        <option value="needs_details">Perlu perhatian: rincian belum ada</option>
                        <option value="ready">Siap dibuat</option>
                        <option value="unprepared">Belum dikerjakan</option>
                        <option value="draft">Perlu dilengkapi</option>
                        <option value="numbered">Sudah bernomor</option>
                    </x-ui.select></x-ui.field>
                <x-ui.button type="button" variant="secondary" icon="refresh" wire:click="resetFilters">Reset Filter</x-ui.button>
                <x-ui.button variant="secondary" :href="route('spj.quarter-recap', ['quarter' => $quarter ?? (int) ceil((int) now()->format('n') / 3)])">Rekap triwulan</x-ui.button>
            </div>
        </div>
    </div>
    <div class="overflow-x-auto">
        <x-ui.table pagination="server">
            <colgroup>
                <col class="w-[180px]">
                <col class="w-[170px]">
                <col>
                <col class="w-[170px]">
                <col class="w-[150px]">
                <col class="w-[11.5rem]">
            </colgroup>
            <thead class="bg-[var(--ui-surface-soft)]">
                <tr>
                    <th class="px-5 py-2 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">
                        Bukti /
                        Tanggal</th>
                    <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">
                        Status</th>
                    <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">
                        Uraian /
                        Penerima</th>
                    <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">
                        Kategori /
                        Rincian</th>
                    <th
                        class="px-4 py-2 text-right text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">
                        Nilai</th>
                    <th
                        class="transaction-action-column px-5 py-2 text-center text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">
                        Aksi
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--ui-line)] bg-[var(--ui-surface-base)]">
                @forelse($transactions ?? [] as $transaction)
                    <tr wire:key="spj-preparation-{{ $transaction->id }}"
                        class="transition hover:bg-[var(--ui-table-row-hover)]">
                        <td class="px-5 py-2.5">
                            <p class="font-mono font-bold text-[var(--theme-content-accent)]">
                                {{ $transaction->sourceValue('no_bukti') }}</p>
                            <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">
                                {{ $transaction->sourceCarbon()?->translatedFormat('d F Y') }}</p>
                        </td>
                        <td class="px-4 py-2.5">
                            @if ($transaction->spjPackage)
                                <x-ui.status-badge :status="$transaction->spjPackage->document_number ? 'NUMBERED' : 'DRAFT'" :label="$transaction->spjPackage->document_number ? 'Bernomor' : 'Draft paket'" />
                                <p class="mt-1 font-mono text-xs text-[var(--ui-fg-muted)]">
                                    {{ $transaction->spjPackage->document_number ?: 'Perlu dilengkapi' }}</p>
                            @else<x-ui.status-badge status="BELUM_LENGKAP" label="Belum disiapkan" />
                            @endif
                        </td>
                        <td class="max-w-sm px-4 py-2.5">
                            <p class="truncate font-semibold text-[var(--ui-fg-strong)]">
                                {{ $transaction->payment_description ?: $transaction->sourceValue('description') ?: 'Tanpa uraian' }}
                            </p>
                            <p class="mt-1 truncate text-xs text-[var(--ui-fg-muted)]">
                                {{ $transaction->sourceValue('recipient_name') ?: 'Penerima belum diisi' }}</p>
                        </td>
                        <td class="px-4 py-2.5">
                            <p class="text-xs font-bold text-[var(--theme-content-accent)]">
                                {{ $spjTypeLabel($transaction->spj_category) }}</p>
                            <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">{{ $transaction->items_count }} rincian
                            </p>
                        </td>
                        <td class="whitespace-nowrap px-4 py-2.5 text-right font-semibold text-[var(--ui-fg-strong)]">
                            {{ $rupiah($transaction->sourceValue('gross_amount')) }}</td>
                        <td class="transaction-action-column px-5 py-2.5">
                            @if ($transaction->spjPackage || $transaction->items_count)
                                <div class="transaction-action-cell flex items-center justify-center"
                                    aria-label="Tindakan persiapan {{ $transaction->sourceValue('no_bukti') }}">
                                    <button type="button" data-transaction-action-trigger
                                        data-detail-url="{{ route('transactions.show', $transaction) }}"
                                        data-package-url="{{ $transaction->spjPackage ? route('spj.index', ['tab' => 'paket', 'package_id' => $transaction->spjPackage->id]) : route('transactions.prepare-spj', $transaction->id) }}"
                                        class="transaction-action-button transaction-action-edit"
                                        title="Tampilkan aksi persiapan" aria-haspopup="dialog">
                                        <span aria-hidden="true">⋯</span>
                                        <span>Aksi</span>
                                    </button>
                                </div>
                            @else<span class="text-xs text-[var(--ui-fg-muted)]">Perlu perhatian: rincian belum
                                    ada</span>
                            @endif
                        </td>
                    </tr>
                @empty<tr>
                        <td colspan="6" class="px-5 py-6 text-center">
                            <p class="font-semibold text-[var(--ui-fg-strong)]">Belum ada transaksi tersinkron.</p>
                            <p class="mt-1 text-base text-[var(--ui-fg-muted)]">Jalankan Sinkron Semua ARKAS terlebih
                                dahulu.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </div>
    @if($transactions->hasPages())
        <x-ui.server-pagination :paginator="$transactions" noun="transaksi" />
    @endif
</div>
