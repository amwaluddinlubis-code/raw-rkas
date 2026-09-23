<x-layouts.tailwind-app title="Referensi">
    @php
        $isPriceTab = $tab === 'harga';
        $baseParams = ['tahun' => $year, 'q' => $search ?: null, 'perPage' => $perPage];
    @endphp

    <div class="space-y-5">
        <x-page-header
            title="Referensi"
            subtitle="Rekening dan acuan harga ARKAS dari mirror pusat. Semua data bersifat baca-saja."
            kicker="Referensi"
            icon="database"
        >
            <x-slot:actions>
                <span class="ui-btn ui-btn-secondary px-3 py-2 text-base">{{ number_format($rows->total(), 0, ',', '.') }} baris</span>
            </x-slot:actions>

            <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <div class="px-5 py-3.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">Tahun acuan</p>
                    <p class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">{{ $year ?? '—' }}</p>
                </div>
                <div class="px-5 py-3.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">Sumber data</p>
                    <p class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">Mirror pusat</p>
                </div>
                <div class="px-5 py-3.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">Mode tampilan</p>
                    <p class="mt-1 text-lg font-bold text-emerald-700">Baca-saja</p>
                </div>
            </div>
        </x-page-header>

        <section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-4 shadow-sm sm:px-5" aria-label="Navigasi referensi">
            <nav class="ui-tabs-list" aria-label="Tab referensi">
                <a href="{{ route('references.index', array_filter(['tab' => 'rekening'] + $baseParams)) }}"
                    aria-current="{{ ! $isPriceTab ? 'page' : 'false' }}"
                    class="ui-tab {{ ! $isPriceTab ? 'ui-tab-active' : '' }}"><span class="ui-tab-icon"><x-ui.icon name="budget" size="sm" /></span><span>Rekening</span></a>
                <a href="{{ route('references.index', array_filter(['tab' => 'harga'] + $baseParams)) }}"
                    aria-current="{{ $isPriceTab ? 'page' : 'false' }}"
                    class="ui-tab {{ $isPriceTab ? 'ui-tab-active' : '' }}"><span class="ui-tab-icon"><x-ui.icon name="balance" size="sm" /></span><span>Acuan Harga</span></a>
            </nav>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow">
            <x-ui.toolbar class="border-b border-[var(--ui-line)] px-5 py-3 sm:px-6">
                <div>
                    <h2 class="text-lg font-bold text-[var(--ui-fg-strong)]">{{ $isPriceTab ? 'Acuan Harga' : 'Rekening' }}</h2>
                    <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">
                        {{ $isPriceTab ? 'Harga acuan barang per satuan dari referensi ARKAS.' : 'Daftar rekening belanja beserta penanda pajaknya.' }}
                    </p>
                </div>
                <x-slot:actions>
                    <x-page-table-per-page :total="$rows->total()" name="perPage" :current="$perPage" />
                </x-slot:actions>
            </x-ui.toolbar>

            <form method="get" action="{{ route('references.index') }}" class="flex flex-col gap-3 border-b border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-5 py-3 sm:flex-row sm:items-end sm:px-6">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="hidden" name="perPage" value="{{ $perPage }}">
                <x-ui.field label="Tahun" class="sm:w-36">
                    <x-ui.select name="tahun" onchange="this.form.submit()">
                        @forelse($years as $option)
                            <option value="{{ $option }}" @selected((string) $year === (string) $option)>{{ $option }}</option>
                        @empty
                            <option value="">Belum ada data</option>
                        @endforelse
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="{{ $isPriceTab ? 'Cari barang / rekening' : 'Cari kode / nama rekening' }}" class="flex-1">
                    <x-ui.input type="search" name="q" value="{{ $search }}" placeholder="{{ $isPriceTab ? 'cth: kertas, 5.1.02…' : 'cth: 5.2.2, honor…' }}" />
                </x-ui.field>
                <div class="flex gap-2">
                    <x-ui.button type="submit" variant="primary">Cari</x-ui.button>
                    <x-ui.button variant="secondary" :href="route('references.index', ['tab' => $tab, 'tahun' => $year, 'perPage' => $perPage])">Atur ulang</x-ui.button>
                </div>
            </form>

            @if($rows->isEmpty())
                <x-ui.empty-state
                    title="Tidak ada data referensi"
                    description="Mirror referensi belum disinkronkan untuk tahun ini atau pencarian tidak cocok. Jalankan Sinkronisasi Referensi lalu coba lagi."
                />
            @elseif(! $isPriceTab)
                <x-ui.table min-width="960px" pagination="server">
                    <thead>
                        <tr>
                            <th class="sticky left-0 z-10 w-14 bg-[var(--ui-surface-soft)] text-center">No</th>
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
                    <thead>
                        <tr>
                            <th class="sticky left-0 z-10 w-14 bg-[var(--ui-surface-soft)] text-center">No</th>
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

            @if(! $rows->isEmpty())
                <x-ui.server-pagination :paginator="$rows" noun="baris" />
            @endif
        </section>
    </div>
</x-layouts.tailwind-app>
