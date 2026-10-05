@php
    $rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $periodQuery = match ($mode) {
        'bulan' => ['month' => $periode],
        'triwulan' => ['quarter' => $periode],
        'semester' => ['semester' => $periode],
        default => [],
    };
    $exportQuery = array_filter(['tab' => 'laporan', ...$periodQuery]);
@endphp
<div data-bulk-report-root>
    <div class="border-b border-[var(--ui-line)] px-5 py-4 sm:px-6">
        <div class="grid items-stretch gap-4 xl:grid-cols-5">
            <section aria-label="Filter laporan" class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3 sm:p-4 xl:col-span-3">
                <div class="spj-report-toolbar flex h-full flex-col justify-center gap-3">
                    <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-4 gap-y-3">
                        <span id="spj-report-period-label" class="shrink-0 whitespace-nowrap text-sm font-semibold text-[var(--ui-fg-strong)]">Periode</span>
                        <div class="flex min-w-0 flex-wrap items-center gap-x-6 gap-y-2">
                            <div class="ui-segment-group flex w-fit max-w-full overflow-x-auto" role="group" aria-labelledby="spj-report-period-label">
                                @foreach ($this->modes() as [$modeOption, $label])
                                    <button type="button" wire:click="setMode('{{ $modeOption }}')" aria-pressed="{{ $mode === $modeOption ? 'true' : 'false' }}" class="whitespace-nowrap border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 py-2 text-sm transition {{ $loop->first ? '' : '-ml-px' }} {{ $mode === $modeOption ? 'font-bold text-[var(--theme-content-accent)]' : 'font-medium text-[var(--ui-fg-muted)] hover:text-[var(--ui-fg-strong)]' }}" style="{{ $mode === $modeOption ? 'box-shadow: inset 0 -3px 0 var(--theme-action-bg);' : '' }}">{{ $label }}</button>
                                @endforeach
                            </div>
                            <div class="flex items-center gap-2 text-xs">
                                <label for="spj-report-row-count" class="text-sm font-semibold text-[var(--ui-fg-strong)]">Baris</label>
                                <x-ui.select id="spj-report-row-count" wire:model.live="perPage" aria-label="Baris per halaman"
                                    class="!min-h-9 !w-auto !py-1.5 !text-xs">
                                    <option value="10">10 baris</option>
                                    <option value="15">15 baris</option>
                                    <option value="25">25 baris</option>
                                    <option value="50">50 baris</option>
                                    <option value="100">100 baris</option>
                                </x-ui.select>
                            </div>
                            <form id="spj-bulk-preview-form" method="POST" action="{{ route('spj.preview-packages') }}" target="template-preview-frame" data-bulk-preview-form>
                                @csrf
                                <button type="submit" data-bulk-submit class="ui-btn ui-btn-primary min-h-10 whitespace-nowrap px-4 disabled:cursor-not-allowed disabled:opacity-50">Pratinjau Massal</button>
                            </form>
                        </div>
                        <label for="spj-report-periode" class="shrink-0 whitespace-nowrap text-sm font-semibold text-[var(--ui-fg-strong)]">Pilih periode</label>
                        <div class="flex min-w-0 flex-wrap items-center gap-3">
                            <x-ui.select id="spj-report-periode" wire:model.live="periode" class="min-w-[12rem] flex-1" style="width: auto;">
                                <option value="">{{ $mode === 'semua' ? 'Semua periode' : 'Pilih '.$mode }}</option>
                                @if($mode === 'semester')
                                    @foreach(range(1,2) as $semester)<option value="{{ $semester }}">Semester {{ $semester }}</option>@endforeach
                                @elseif($mode === 'triwulan')
                                    @foreach(range(1,4) as $quarter)<option value="{{ $quarter }}">Triwulan {{ $quarter }}</option>@endforeach
                                @elseif($mode === 'bulan')
                                    @foreach(range(1,12) as $month)<option value="{{ $month }}">{{ \Carbon\Carbon::create()->month($month)->translatedFormat('F') }}</option>@endforeach
                                @endif
                            </x-ui.select>
                            <x-ui.input id="spj-report-search" wire:model.live.debounce.300ms="search" type="search"
                                placeholder="Cari nomor, bukti, penerima..." aria-label="Cari laporan"
                                class="min-w-[14rem] flex-1" />
                            <x-ui.action-menu label="Ekspor">
                                <div class="ui-action-menu-label">Susun Laporan</div>
                                <a class="ui-action-menu-item" href="{{ route('spj.honor-payments.select', $exportQuery) }}">Honor Pegawai</a>
                                <a class="ui-action-menu-item" href="{{ route('spj.service-recipients.select', $exportQuery) }}">Jasa Lainnya</a>
                            </x-ui.action-menu>
                        </div>
                    </div>
                </div>
            </section>
            <section aria-label="Ringkasan laporan" class="grid grid-cols-2 content-center gap-3 xl:col-span-2">
                @foreach([['Paket sukses',$summary['count'] ?? 0,'text-[var(--theme-content-accent)]'],['Paket dibatalkan',$summary['cancelled_count'] ?? 0,'text-rose-700'],['Nilai bruto',$rupiah($summary['gross'] ?? 0),'text-[var(--ui-fg-strong)]'],['Nilai dibayarkan',$rupiah($summary['net'] ?? 0),'text-emerald-700']] as [$label,$value,$color])
                    <div class="flex min-h-[4.25rem] h-full flex-row items-center justify-between gap-2 rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 py-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">{{ $label }}</p><p class="text-right text-xl font-extrabold {{ $color }} [overflow-wrap:anywhere]">{{ $value }}</p>
                    </div>
                @endforeach
            </section>
        </div>
    </div>
    <div class="overflow-x-auto p-5"><x-ui.table pagination="server"><thead class="bg-[var(--ui-surface-soft)]"><tr><th class="px-4 py-2 text-left text-xs font-bold text-[var(--ui-fg-muted)]"><input type="checkbox" data-bulk-select-all aria-label="Pilih semua paket pada halaman ini" class="h-4 w-4 rounded border-[var(--ui-line-strong)] accent-[var(--theme-action-bg)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--theme-accent)]"></th><th class="px-4 py-2 text-left text-xs font-bold text-[var(--ui-fg-muted)]">NOMOR SPJ</th><th class="px-4 py-2 text-left text-xs font-bold text-[var(--ui-fg-muted)]">STATUS</th><th class="px-4 py-2 text-left text-xs font-bold text-[var(--ui-fg-muted)]">BUKTI / TANGGAL</th><th class="px-4 py-2 text-left text-xs font-bold text-[var(--ui-fg-muted)]">PENERIMA</th><th class="px-4 py-2 text-right text-xs font-bold text-[var(--ui-fg-muted)]">BRUTO</th><th class="px-4 py-2 text-right text-xs font-bold text-[var(--ui-fg-muted)]">PAJAK</th><th class="px-4 py-2 text-right text-xs font-bold text-[var(--ui-fg-muted)]">DIBAYARKAN</th><th class="px-4 py-2 text-right text-xs font-bold text-[var(--ui-fg-muted)]">AKSI</th></tr></thead><tbody class="divide-y divide-[var(--ui-line)]">
            @php
                $isCancelled = false;
            @endphp
            @forelse($packages ?? [] as $packageIndex => $package)
                    @php
                        $isCancelled = $package->report_status === 'CANCELLED';
                        $packageUrl = route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]);
                        $siplahResponse = data_get($package->transaction->siplah_metadata, 'siplahResponse', []);
                        $vendorName = $package->transaction->vendor_name ?: data_get($siplahResponse, 'merchant');
                        $recipientName = $package->transaction->receipt_recipient_name ?: $package->transaction->spj_recipient_name ?: $package->transaction->sourceValue('recipient_name');
                    @endphp
                <tr wire:key="spj-report-{{ $package->id }}" class="transition {{ $isCancelled ? 'bg-rose-50/70 text-[var(--ui-fg-muted)]' : 'hover:bg-[var(--ui-surface-soft)]' }}">
                    <td class="px-4 py-2"><input type="checkbox" name="package_ids[]" value="{{ $package->id }}" form="spj-bulk-preview-form" data-bulk-package class="h-4 w-4 rounded border-[var(--ui-line-strong)] accent-[var(--theme-action-bg)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--theme-accent)]" aria-label="Pilih paket {{ $package->report_document_number }}"></td>
                    <td class="px-4 py-2 font-mono text-xs font-bold {{ $isCancelled ? 'text-rose-700 line-through' : 'text-[var(--theme-content-accent)]' }}">
                        <a href="{{ $packageUrl }}" class="hover:underline">{{ $package->report_document_number }}</a>
                    </td>
                    <td class="px-4 py-2">
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $isCancelled ? 'border border-rose-200 bg-rose-100 text-rose-800' : 'border border-emerald-200 bg-emerald-100 text-emerald-800' }}">{{ $isCancelled ? 'Dibatalkan' : 'Sukses' }}</span>
                        @if ($isCancelled && $package->report_cancellation_reason)
                            <p class="mt-1 max-w-48 text-xs text-rose-700">{{ $package->report_cancellation_reason }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-2">
                        <p class="font-semibold">{{ $package->transaction->sourceValue('no_bukti') }}</p>
                        <p class="text-xs text-[var(--ui-fg-muted)]">{{ $package->transaction->sourceCarbon()?->translatedFormat('d F Y') }}</p>
                    </td>
                    <td class="px-4 py-2">
                        <p class="font-semibold">{{ $vendorName ?: '-' }}</p>
                        <p class="text-xs text-[var(--ui-fg-muted)]">{{ $recipientName ?: '-' }}</p>
                    </td>
                    <td class="px-4 py-2 text-right">{{ $rupiah($package->transaction->sourceValue('gross_amount')) }}</td>
                    <td class="px-4 py-2 text-right {{ $isCancelled ? 'text-[var(--ui-fg-muted)]' : 'text-amber-700' }}">{{ $rupiah($package->transaction->sourceValue('tax_total')) }}</td>
                    <td class="px-4 py-2 text-right font-bold {{ $isCancelled ? 'text-[var(--ui-fg-muted)]' : 'text-emerald-700' }}">{{ $rupiah($package->transaction->sourceValue('net_amount')) }}</td>
                    <td class="px-4 py-2 text-right">
                        <x-ui.action-menu label="Aksi" :drop-up="(($packages?->count() ?? 0) - $packageIndex) <= 3">
                            <button type="button" class="ui-action-menu-item w-full text-left" data-template-preview="{{ route('spj.preview-package', $package->id) }}" data-template-preview-pdf="{{ route('spj.preview-package-pdf', $package->id) }}" @if (! $isCancelled) data-template-download-pdf="{{ route('spj.preview-package-pdf', [$package->id, 'download' => 1]) }}" data-template-download-excel="{{ route('spj.preview-package-excel', $package->id) }}" @endif data-template-name="Pratinjau {{ $package->report_document_number }}">Preview dokumen</button>
                            @if (! $isCancelled)
                                <form method="POST" action="{{ route('spj.download', $package->id) }}">@csrf<button type="submit" class="ui-action-menu-item w-full text-left">Download PDF</button></form>
                                <form method="POST" action="{{ route('spj.download-package-excel', $package->id) }}">@csrf<button type="submit" class="ui-action-menu-item w-full text-left">Download Excel</button></form>
                            @endif
                            <a class="ui-action-menu-item" href="{{ $packageUrl }}">Buka Paket</a>
                        </x-ui.action-menu>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="px-5 py-6 text-center text-[var(--ui-fg-muted)]">Belum ada riwayat paket SPJ untuk filter ini.</td></tr>
            @endforelse
        </tbody></x-ui.table></div>
    @if($packages->hasPages())
        <x-ui.server-pagination :paginator="$packages" noun="paket" />
    @endif
</div>
