{{-- Bar filter dipakai ulang tab Paket dan Atribut SPJ.
    Satu-satunya pembeda adalah prefix id ($idPrefix = spj-package /
    spj-attribute) agar asosiasi label/for, hook CSS
    (.spj-package-filter-bar/.spj-attribute-filter-bar), dan assertion
    id per-tab tetap utuh. State Livewire (search/status/category/
    mode/periode/perPage + modes()/setMode()/clearFilters()) dimiliki
    trait HasSpjListFilter di kedua komponen. --}}
<div class="{{ $idPrefix }}-filter-bar grid gap-2 border-b border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-4 py-3 sm:grid-cols-2 md:grid-cols-6 lg:grid-cols-[minmax(10rem,1fr)_minmax(7rem,0.8fr)_minmax(7rem,0.8fr)_minmax(26rem,2fr)_auto_auto] lg:items-end">
    <x-ui.field label="Cari paket" for="{{ $idPrefix }}-search" class="md:col-span-6 lg:col-span-1">
        <div class="relative">
            <x-ui.icon name="search" size="sm" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2" />
            <x-ui.input id="{{ $idPrefix }}-search" wire:model.live.debounce.300ms="search" placeholder="No. bukti, uraian, atau penerima..." class="!pl-9" />
        </div>
    </x-ui.field>
    <x-ui.field label="Status" for="{{ $idPrefix }}-status">
        <x-ui.searchable-select id="{{ $idPrefix }}-status" wire-model="status"
            :options="[
                ['value' => '', 'label' => 'Semua status'],
                ['value' => 'DRAFT', 'label' => 'Draft'],
                ['value' => 'READY', 'label' => 'Siap dinomori'],
                ['value' => 'NUMBERED', 'label' => 'Bernomor'],
                ['value' => 'FINAL', 'label' => 'Final'],
                ['value' => 'CANCELLED', 'label' => 'Dibatalkan'],
            ]" placeholder="Semua status" search-placeholder="Cari status..." />
    </x-ui.field>
    <x-ui.field label="Kategori SPJ" for="{{ $idPrefix }}-category">
        <x-ui.searchable-select id="{{ $idPrefix }}-category" wire-model="category"
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
    <x-ui.field label="Periode" for="{{ $idPrefix }}-period" class="min-w-0 md:col-span-2 lg:col-span-1">
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
            <x-ui.select id="{{ $idPrefix }}-period" wire:model.live="periode" class="w-full min-w-0 flex-1 !py-1.5 !text-sm"
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
    <x-ui.field label="Baris" for="{{ $idPrefix }}-per-page">
        <x-ui.select id="{{ $idPrefix }}-per-page" wire:model.live="perPage" aria-label="Baris per halaman"
            class="!w-auto !py-1.5 !text-sm">
            @foreach ([10, 15, 25, 50, 100] as $perPageOption)<option value="{{ $perPageOption }}"
                @selected($perPage == $perPageOption)>{{ $perPageOption }} baris</option>@endforeach
        </x-ui.select>
    </x-ui.field>
    <x-ui.button type="button" variant="secondary" icon="refresh" wire:click="clearFilters" wire:loading.attr="disabled"
        wire:target="clearFilters" class="w-full justify-center md:self-end lg:w-auto">Bersihkan</x-ui.button>
</div>
