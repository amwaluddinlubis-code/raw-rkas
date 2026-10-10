<div>
    <div class="border-b border-[var(--ui-line)] px-5 py-5 sm:px-6">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="font-bold text-[var(--ui-fg-strong)]">Antrean persiapan SPJ</h2>
                <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Pilih transaksi, periksa rincian, lalu siapkan paket
                    dokumennya.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <p class="max-w-xl text-xs font-medium text-[var(--ui-fg-muted)]">Prioritas: perlu dilengkapi → belum
                    dikerjakan →
                    sudah bernomor. Dalam setiap kelompok, tanggal terlama tampil lebih dahulu.</p>
                <x-ui.button variant="secondary" :href="route('spj.quarter-recap')" class="shrink-0">Rekap Triwulan</x-ui.button>
            </div>
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
            <div class="grid gap-2 sm:grid-cols-2 md:grid-cols-6 lg:grid-cols-[minmax(10rem,1fr)_minmax(7rem,0.8fr)_minmax(7rem,0.8fr)_minmax(26rem,2fr)_auto_auto] lg:items-end">
                <x-ui.field label="Cari paket" for="spj-preparation-search" class="md:col-span-6 lg:col-span-1">
                    <div class="relative">
                        <x-ui.icon name="search" size="sm" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2" />
                        <x-ui.input id="spj-preparation-search" wire:model.live.debounce.300ms="search" placeholder="No. bukti, uraian, atau penerima..." class="!pl-9" />
                    </div>
                </x-ui.field>
                <x-ui.field label="Status" for="spj-preparation-status">
                    <x-ui.searchable-select id="spj-preparation-status" wire-model="state"
                        :options="[
                            ['value' => 'all', 'label' => 'Semua status'],
                            ['value' => 'attention', 'label' => 'Perlu perhatian: rincian belum ada'],
                            ['value' => 'ready', 'label' => 'Siap dibuat'],
                            ['value' => 'unprepared', 'label' => 'Belum dikerjakan'],
                            ['value' => 'draft', 'label' => 'Perlu dilengkapi'],
                            ['value' => 'numbered', 'label' => 'Sudah bernomor'],
                        ]" placeholder="Semua status" search-placeholder="Cari status..." />
                </x-ui.field>
                <x-ui.field label="Kategori SPJ" for="spj-preparation-category">
                    <x-ui.searchable-select id="spj-preparation-category" wire-model="spj_category"
                        :options="array_merge(
                            [['value' => '', 'label' => 'Semua kategori']],
                            collect($spjTypes ?? [])->map(fn ($type) => ['value' => $type, 'label' => \App\Support\SpjDisplay::typeLabel($type)])->all()
                        )" placeholder="Semua kategori" search-placeholder="Cari kategori..." />
                </x-ui.field>
                <x-ui.field label="Periode" for="spj-preparation-period" class="min-w-0 md:col-span-2 lg:col-span-1">
                    <div class="spj-period-filter flex min-w-0 flex-wrap items-end gap-2">
                        <div class="ui-segment-group flex shrink-0 max-w-full overflow-x-auto" role="group" aria-label="Mode periode">
                        @foreach($this->modes() as $modeOption => $label)
                            <button type="button" wire:click="setMode('{{ $modeOption }}')"
                                aria-pressed="{{ $mode === $modeOption ? 'true' : 'false' }}"
                                class="whitespace-nowrap border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-2.5 py-1.5 text-xs transition {{ $loop->first ? '' : '-ml-px' }} {{ $mode === $modeOption ? 'font-bold text-[var(--theme-content-accent)]' : 'font-medium text-[var(--ui-fg-muted)] hover:text-[var(--ui-fg-strong)]' }}"
                                style="{{ $mode === $modeOption ? 'box-shadow: inset 0 -3px 0 var(--theme-action-bg);' : '' }}">{{ $label }}</button>
                        @endforeach
                        </div>
                        {{-- Mode Semua tidak butuh dropdown (satu-satunya opsi "Semua
                            periode" hanya menambah baris visual). --}}
                        @if($mode !== 'semua')
                        <x-ui.select id="spj-preparation-period" wire:model.live="periode" class="w-full min-w-0 flex-1 !py-1.5 !text-sm"
                            style="width: auto;">
                            <option value="">{{ 'Pilih '.$mode }}</option>
                            @if($mode === 'semester')
                                @foreach(range(1,2) as $semester)<option value="{{ $semester }}">Semester {{ $semester }}</option>@endforeach
                            @elseif($mode === 'triwulan')
                                @foreach(range(1,4) as $quarter)<option value="{{ $quarter }}">Triwulan {{ $quarter }}</option>@endforeach
                            @elseif($mode === 'bulan')
                                @foreach(range(1,12) as $month)<option value="{{ $month }}">{{ \Carbon\Carbon::create()->month($month)->translatedFormat('F') }}</option>@endforeach
                        @endif
                        </x-ui.select>
                        @endif
                    </div>
                </x-ui.field>
                <x-ui.field label="Baris" for="spj-preparation-per-page">
                    <x-ui.select id="spj-preparation-per-page" wire:model.live="perPage" aria-label="Baris per halaman"
                        class="!w-auto !py-1.5 !text-sm">
                        @foreach ([15, 25, 50, 100] as $perPageOption)<option value="{{ $perPageOption }}"
                            @selected($perPage == $perPageOption)>{{ $perPageOption }} baris</option>@endforeach
                        <option value="all" @selected($perPage === 'all')>Semua</option>
                    </x-ui.select>
                </x-ui.field>
                <x-ui.button type="button" variant="secondary" icon="refresh" wire:click="resetFilters" wire:loading.attr="disabled"
                    wire:target="resetFilters" class="w-full justify-center md:self-end lg:w-auto">Bersihkan</x-ui.button>
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
                                {{ \App\Support\SpjDisplay::typeLabel($transaction->spj_category) }}</p>
                            <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">{{ $transaction->items_count }} rincian
                            </p>
                        </td>
                        <td class="whitespace-nowrap px-4 py-2.5 text-right font-semibold text-[var(--ui-fg-strong)]">
                            {{ \App\Support\SpjDisplay::rupiah($transaction->sourceValue('gross_amount')) }}</td>
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
