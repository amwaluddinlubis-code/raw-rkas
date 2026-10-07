@php
    $rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $spjTypeLabel = fn ($value) => match (strtoupper((string) $value)) {
        'JASA_HONORARIUM', 'HONOR_PEGAWAI' => 'Honor Pegawai',
        'JASA_LAINNYA' => 'Jasa Lainnya',
        'BARANG' => 'Barang',
        'KONSUMSI' => 'Konsumsi',
        'PEMELIHARAAN' => 'Pemeliharaan',
        'SPPD' => 'SPPD',
        default => ucwords(strtolower(str_replace('_', ' ', (string) $value))),
    };
    $listedPackages = $packageList ?? collect();
@endphp
<div>
    <div class="border-b border-[var(--ui-line)] px-5 py-4 sm:px-6">
        <h2 class="font-bold" style="color: var(--ui-fg)">Daftar Paket SPJ</h2>
        <p class="mt-1 text-sm" style="color: var(--ui-fg-muted)">Pilih paket untuk memeriksa kelengkapan, memperbaiki isian manual, dan mengelola penomoran.</p>
    </div>
    <div class="spj-package-filter-bar grid gap-2 border-b border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-4 py-3 sm:grid-cols-2 md:grid-cols-6 lg:grid-cols-[minmax(10rem,1fr)_minmax(7rem,0.8fr)_minmax(7rem,0.8fr)_minmax(26rem,2fr)_auto_auto] lg:items-end">
        <x-ui.field label="Cari paket" for="spj-package-search" class="md:col-span-6 lg:col-span-1">
            <div class="relative">
                <x-ui.icon name="search" size="sm" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2" />
                <x-ui.input id="spj-package-search" wire:model.live.debounce.300ms="search" placeholder="No. bukti, uraian, atau penerima..." class="!pl-9" />
            </div>
        </x-ui.field>
        <x-ui.field label="Status" for="spj-package-status">
            <x-ui.searchable-select id="spj-package-status" wire-model="status"
                :options="[
                    ['value' => '', 'label' => 'Semua status'],
                    ['value' => 'DRAFT', 'label' => 'Draft'],
                    ['value' => 'READY', 'label' => 'Siap dinomori'],
                    ['value' => 'NUMBERED', 'label' => 'Bernomor'],
                    ['value' => 'FINAL', 'label' => 'Final'],
                    ['value' => 'CANCELLED', 'label' => 'Dibatalkan'],
                ]" placeholder="Semua status" search-placeholder="Cari status..." />
        </x-ui.field>
        <x-ui.field label="Kategori SPJ" for="spj-package-category">
            <x-ui.searchable-select id="spj-package-category" wire-model="category"
                :options="[
                    ['value' => '', 'label' => 'Semua kategori'],
                    ['value' => 'BARANG', 'label' => 'Barang'],
                    ['value' => 'KONSUMSI', 'label' => 'Konsumsi'],
                    ['value' => 'PEMELIHARAAN', 'label' => 'Pemeliharaan'],
                    ['value' => 'SPPD', 'label' => 'SPPD'],
                    ['value' => 'HONOR_PEGAWAI', 'label' => 'Honor Pegawai'],
                    ['value' => 'JASA_LAINNYA', 'label' => 'Jasa Lainnya'],
                ]" placeholder="Semua kategori" search-placeholder="Cari kategori..." />
        </x-ui.field>
        <x-ui.field label="Periode" for="spj-package-period" class="min-w-0 md:col-span-2 lg:col-span-1">
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
                <x-ui.select id="spj-package-period" wire:model.live="periode" class="w-full min-w-0 flex-1 !py-1.5 !text-sm"
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
        <x-ui.field label="Baris" for="spj-package-per-page">
            <x-ui.select id="spj-package-per-page" wire:model.live="perPage" aria-label="Baris per halaman"
                class="!w-auto !py-1.5 !text-sm">
                @foreach ([10, 15, 25, 50, 100] as $perPageOption)<option value="{{ $perPageOption }}"
                    @selected($perPage == $perPageOption)>{{ $perPageOption }} baris</option>@endforeach
            </x-ui.select>
        </x-ui.field>
        <x-ui.button type="button" variant="secondary" icon="refresh" wire:click="clearFilters" wire:loading.attr="disabled"
            wire:target="clearFilters" class="w-full justify-center md:self-end lg:w-auto">Bersihkan</x-ui.button>
    </div>
    <div class="grid gap-3 p-4 lg:hidden">
        @forelse($listedPackages as $listedPackage)
            <a wire:key="spj-package-card-{{ $listedPackage->id }}" href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $listedPackage->id]) }}" class="rounded-xl border p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md" style="border-color: var(--ui-line); background: var(--ui-surface-base); color: var(--ui-fg)">
                <div class="flex items-start justify-between gap-3"><div><p class="font-mono text-sm font-bold theme-text">{{ $listedPackage->transaction->sourceValue('no_bukti') }}</p><p class="mt-1 text-xs" style="color: var(--ui-fg-muted)">{{ $listedPackage->transaction->sourceCarbon()?->translatedFormat('d F Y') }}</p></div>@if($listedPackage->status === 'CANCELLED')<x-ui.status-badge status="CANCELLED" label="Dibatalkan" size="xs" />@elseif($listedPackage->document_number)<x-ui.status-badge status="NUMBERED" label="Bernomor" size="xs" />@else<x-ui.status-badge status="DRAFT" label="Draft" size="xs" />@endif</div>
                <p class="mt-2 line-clamp-2 text-sm font-semibold" style="color: var(--ui-fg)">{{ $listedPackage->transaction->payment_description ?: $listedPackage->transaction->sourceValue('description') }}</p>
                <div class="mt-3 flex items-center justify-between text-xs" style="color: var(--ui-fg-muted)"><span style="color: var(--theme-content-accent)">{{ $spjTypeLabel($listedPackage->transaction->spj_category) }}</span><b style="color: var(--ui-fg)">{{ $rupiah($listedPackage->transaction->sourceValue('gross_amount')) }}</b></div>
            </a>
        @empty
            <div class="rounded-xl border border-dashed p-8 text-center" style="border-color: var(--ui-line); color: var(--ui-fg-muted)">Belum ada paket SPJ. Siapkan transaksi dari tab Persiapan.</div>
        @endforelse
    </div>
    <div class="hidden overflow-x-auto lg:block">
        <x-ui.table pagination="server">
            <thead class="bg-[var(--ui-surface-soft)]"><tr><th class="px-5 py-2 text-left text-xs font-bold uppercase text-slate-500">Bukti / Tanggal</th><th class="px-4 py-2 text-left text-xs font-bold uppercase text-slate-500">Uraian / Penerima</th><th class="px-4 py-2 text-left text-xs font-bold uppercase text-slate-500">Kategori</th><th class="px-4 py-2 text-right text-xs font-bold uppercase text-slate-500">Nilai</th><th class="px-4 py-2 text-left text-xs font-bold uppercase text-slate-500">Status</th><th class="px-5 py-2 text-right text-xs font-bold uppercase text-slate-500">Aksi</th></tr></thead>
            <tbody class="divide-y divide-[var(--ui-line)]">
                @forelse($listedPackages as $listedPackage)
                    <tr wire:key="spj-package-{{ $listedPackage->id }}" class="hover:bg-slate-50"><td class="px-5 py-2.5"><p class="font-mono font-bold theme-text">{{ $listedPackage->transaction->sourceValue('no_bukti') }}</p><p class="mt-1 text-xs" style="color: var(--ui-fg-muted)">{{ $listedPackage->transaction->sourceCarbon()?->translatedFormat('d F Y') }}</p></td><td class="max-w-sm px-4 py-2.5"><p class="truncate font-semibold" style="color: var(--ui-fg)">{{ $listedPackage->transaction->payment_description ?: $listedPackage->transaction->sourceValue('description') }}</p><p class="mt-1 truncate text-xs" style="color: var(--ui-fg-muted)">{{ $listedPackage->transaction->sourceValue('recipient_name') ?: 'Penerima belum diisi' }}</p></td><td class="px-4 py-2.5 text-xs font-bold" style="color: var(--theme-content-accent)">{{ $spjTypeLabel($listedPackage->transaction->spj_category) }}</td><td class="px-4 py-2.5 text-right font-semibold" style="color: var(--ui-fg)">{{ $rupiah($listedPackage->transaction->sourceValue('gross_amount')) }}</td><td class="px-4 py-2.5">@if($listedPackage->status === 'CANCELLED')<x-ui.status-badge status="CANCELLED" label="Dibatalkan" />@elseif($listedPackage->document_number)<x-ui.status-badge status="NUMBERED" label="Bernomor" />@else<x-ui.status-badge status="DRAFT" label="Draft" />@endif @if($listedPackage->document_number)<p class="mt-1 font-mono text-[11px]" style="color: var(--ui-fg-muted)">{{ $listedPackage->document_number }}</p>@endif</td><td class="px-5 py-2.5 text-right"><x-ui.button :href="route('spj.index', ['tab' => 'paket', 'package_id' => $listedPackage->id])">Buka paket →</x-ui.button></td></tr>
                @empty<tr><td colspan="6" class="px-5 py-6 text-center" style="color: var(--ui-fg-muted)">Belum ada paket SPJ. Siapkan transaksi dari tab Persiapan.</td></tr>@endforelse
            </tbody>
        </x-ui.table>
    </div>
    @if($packageList->hasPages())
        <x-ui.server-pagination :paginator="$packageList" noun="paket" />
    @endif
</div>
