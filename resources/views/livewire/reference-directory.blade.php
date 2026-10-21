<div class="space-y-5">
    <x-page-header
        title="Referensi"
        subtitle="Rekening dan acuan harga ARKAS dari mirror pusat. Semua data bersifat baca-saja."
        kicker="Referensi"
        icon="database"
    >
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-3 py-2 text-base font-bold tabular-nums text-[var(--ui-fg-strong)]" aria-label="Total {{ number_format($rows->total(), 0, ',', '.') }} baris">{{ number_format($rows->total(), 0, ',', '.') }} baris</span>
        </x-slot:actions>

        <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-3 sm:divide-x sm:divide-y-0">
            <div class="px-5 py-3.5">
                <p class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">Tahun acuan</p>
                <p class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">{{ $tahun ?: '—' }}</p>
            </div>
            <div class="px-5 py-3.5">
                <p class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">Sumber data</p>
                <p class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">Mirror pusat</p>
            </div>
            <div class="px-5 py-3.5">
                <p class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">Mode tampilan</p>
                <p class="mt-1 text-lg font-bold text-[var(--theme-content-accent)]">Baca-saja</p>
            </div>
        </div>
    </x-page-header>

    <section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-4 shadow-sm sm:px-5" aria-label="Navigasi referensi">
        <nav class="ui-tabs-list" aria-label="Tab referensi">
            <button type="button" wire:click="setTab('rekening')"
                aria-current="{{ ! $isPriceTab && ! $isActivityTab ? 'page' : 'false' }}"
                class="ui-tab {{ ! $isPriceTab && ! $isActivityTab ? 'ui-tab-active' : '' }}">
                <span class="ui-tab-icon"><x-ui.icon name="budget" size="sm" /></span>
                <span>Rekening</span>
            </button>
            <button type="button" wire:click="setTab('harga')"
                aria-current="{{ $isPriceTab ? 'page' : 'false' }}"
                class="ui-tab {{ $isPriceTab ? 'ui-tab-active' : '' }}">
                <span class="ui-tab-icon"><x-ui.icon name="balance" size="sm" /></span>
                <span>Acuan Harga</span>
            </button>
            <button type="button" wire:click="setTab('kegiatan')"
                aria-current="{{ $isActivityTab ? 'page' : 'false' }}"
                class="ui-tab {{ $isActivityTab ? 'ui-tab-active' : '' }}">
                <span class="ui-tab-icon"><x-ui.icon name="work" size="sm" /></span>
                <span>Program &amp; Kegiatan</span>
            </button>
        </nav>
    </section>

    <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow">
        <x-ui.toolbar class="border-b border-[var(--ui-line)] px-5 py-3 sm:px-6">
            <div>
                <h2 class="text-lg font-bold text-[var(--ui-fg-strong)]">{{ $isActivityTab ? 'Program & Kegiatan' : ($isPriceTab ? 'Acuan Harga' : 'Rekening') }}</h2>
                <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">
                    {{ $isActivityTab ? 'Daftar program, subprogram, dan kegiatan dari hierarki ARKAS.' : ($isPriceTab ? 'Harga acuan barang per satuan dari referensi ARKAS.' : 'Daftar rekening belanja beserta penanda pajaknya.') }}
                </p>
            </div>
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <label for="reference-per-page" class="text-xs font-semibold text-[var(--ui-fg-muted)]">Baris</label>
                    <x-ui.select id="reference-per-page" wire:model.live="perPage" class="!w-auto !py-1.5 !text-xs">
                        <option value="15">15 baris</option>
                        <option value="25">25 baris</option>
                        <option value="50">50 baris</option>
                        <option value="100">100 baris</option>
                    </x-ui.select>
                </div>
            </x-slot:actions>
        </x-ui.toolbar>

        <div class="flex flex-col gap-3 border-b border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-5 py-3 sm:flex-row sm:items-end sm:px-6">
            <x-ui.field label="Tahun" class="sm:w-36">
                <x-ui.select wire:model.live="tahun">
                    @forelse($years as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @empty
                        <option value="">Belum ada data</option>
                    @endforelse
                </x-ui.select>
            </x-ui.field>
            @if ($isActivityTab)
                <x-ui.field label="Program" class="min-w-[10rem]">
                    <x-ui.select wire:model.live="program">
                        <option value="">Semua Program</option>
                        @foreach ($programOptions as $p)
                            <option value="{{ $p['kode'] }}">{{ $p['kode'] }} - {{ $p['nama'] }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="Sub Program" class="min-w-[10rem]">
                    <x-ui.select wire:model.live="sub" :disabled="!$program">
                        <option value="">Semua Sub Program</option>
                        @foreach ($subOptions as $s)
                            <option value="{{ $s['kode'] }}">{{ $s['kode'] }} - {{ $s['nama'] }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="Kegiatan" class="min-w-[10rem]">
                    <x-ui.select wire:model.live="kegiatan" :disabled="!$sub">
                        <option value="">Semua Kegiatan</option>
                        @foreach ($kegiatanOptions as $k)
                            <option value="{{ $k['kode'] }}">{{ $k['kode'] }} - {{ $k['nama'] }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
            @endif
            <x-ui.field label="{{ $isActivityTab ? 'Cari program / subprogram / kegiatan' : ($isPriceTab ? 'Cari barang / rekening' : 'Cari kode / nama rekening') }}" class="flex-1">
                <x-ui.input type="search" wire:model.live.debounce.350ms="q" placeholder="{{ $isActivityTab ? 'cth: 01.05, kerangka acuan…' : ($isPriceTab ? 'cth: kertas, 5.1.02…' : 'cth: 5.2.2, honor…') }}" />
            </x-ui.field>
            <div class="flex gap-2">
                <x-ui.button type="button" wire:click="resetFilters" variant="secondary">Atur ulang</x-ui.button>
            </div>
        </div>

        <div wire:loading.delay class="border-b border-[var(--ui-line)] bg-indigo-50/50 px-5 py-2 text-xs text-indigo-700 sm:px-6">
            <span class="inline-flex items-center gap-2 font-medium">
                <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                Memuat data referensi...
            </span>
        </div>

        @if($q !== '')
            <p class="border-b border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-5 py-2 text-sm text-[var(--ui-fg-muted)] sm:px-6" role="status">
                Hasil untuk <span class="font-semibold text-[var(--ui-fg-strong)]">&ldquo;{{ $q }}&rdquo;</span>
                — <span class="font-semibold tabular-nums text-[var(--ui-fg-strong)]">{{ number_format($rows->total(), 0, ',', '.') }}</span> baris
                (tahun {{ $tahun ?: '—' }})
            </p>
        @endif

        @if($rows->isEmpty())
            <x-ui.empty-state
                title="Tidak ada data referensi"
                description="Mirror referensi belum disinkronkan untuk tahun ini atau pencarian tidak cocok. Jalankan Sinkronisasi Referensi lalu coba lagi."
            />
        @elseif($isActivityTab)
            <x-ui.table min-width="960px" pagination="server">
                <caption class="sr-only">Program dan kegiatan ARKAS tahun {{ $tahun ?: '—' }}</caption>
                <thead>
                    <tr>
                        <th class="sticky left-0 z-10 w-14 bg-[var(--ui-component-surface-soft)] text-center">No</th>
                        <th>Program</th>
                        <th>Subprogram</th>
                        <th>Kegiatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td class="text-center font-mono text-xs">{{ ($rows->currentPage() - 1) * $rows->perPage() + $loop->iteration }}</td>
                            <td class="text-sm"><span class="font-mono text-xs">{{ $row->program_code }}</span> · {{ $row->program_name }}</td>
                            <td class="text-sm"><span class="font-mono text-xs">{{ $row->sub_program_code }}</span> · {{ $row->sub_program_name }}</td>
                            <td class="text-sm"><span class="font-mono text-xs">{{ $row->activity_code }}</span> · {{ $row->activity_name }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @elseif(! $isPriceTab)
            <x-ui.table min-width="960px" pagination="server">
                <caption class="sr-only">Rekening belanja ARKAS tahun {{ $tahun ?: '—' }}</caption>
                <thead>
                    <tr>
                        <th class="sticky left-0 z-10 w-14 bg-[var(--ui-component-surface-soft)] text-center">No</th>
                        <th class="whitespace-nowrap">Kode rekening</th>
                        <th>Nama rekening</th>
                        <th class="text-center">PPN</th>
                        <th class="text-center">PPh 21</th>
                        <th class="text-center">PPh 22</th>
                        <th class="text-center">PPh 23</th>
                        <th class="text-center">PPh 4</th>
                        <th class="text-center">SSPD</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td class="text-center font-mono text-xs">{{ ($rows->currentPage() - 1) * $rows->perPage() + $loop->iteration }}</td>
                            <td class="whitespace-nowrap font-mono text-xs">{{ $row->kode_rekening }}</td>
                            <td class="text-sm">{{ $row->nama_rekening }}</td>
                            @foreach(['is_ppn', 'is_pph21', 'is_pph22', 'is_pph23', 'is_pph4', 'is_sspd'] as $flag)
                                <td class="text-center">
                                    @if((string) ($row->{$flag} ?? '0') === '1')
                                        <x-ui.badge variant="theme">Ya</x-ui.badge>
                                    @else
                                        <span class="text-xs text-[var(--ui-fg-muted)]">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @else
            <x-ui.table min-width="960px" pagination="server">
                <caption class="sr-only">Acuan harga barang ARKAS tahun {{ $tahun ?: '—' }}</caption>
                <thead>
                    <tr>
                        <th class="sticky left-0 z-10 w-14 bg-[var(--ui-component-surface-soft)] text-center">No</th>
                        <th>Nama barang</th>
                        <th class="whitespace-nowrap">Kode rekening</th>
                        <th>Satuan</th>
                        <th class="text-right">Harga acuan</th>
                        <th class="text-right">Batas atas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td class="text-center font-mono text-xs">{{ ($rows->currentPage() - 1) * $rows->perPage() + $loop->iteration }}</td>
                            <td class="text-sm">{{ $row->nama_barang }}
                                @if((int) ($row->varian ?? 1) > 1)
                                    <span class="block text-[11px] text-[var(--ui-fg-muted)]" title="Kode barang: {{ $row->kode_list }}">{{ $row->varian }} kode barang</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap font-mono text-xs">{{ $row->kode_rekening }}</td>
                            <td class="text-sm">{{ $row->satuan }}</td>
                            <td class="text-right font-mono text-xs">{{ number_format((float) ($row->harga_min ?? 0), 0, ',', '.') }}{{ (float) ($row->harga_max ?? 0) > (float) ($row->harga_min ?? 0) ? ' – '.number_format((float) $row->harga_max, 0, ',', '.') : '' }}</td>
                            <td class="text-right font-mono text-xs">{{ number_format((float) ($row->batas_atas ?? 0), 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif

        @if($rows->hasPages())
            <x-ui.server-pagination :paginator="$rows" noun="baris" />
        @endif
    </section>
</div>

