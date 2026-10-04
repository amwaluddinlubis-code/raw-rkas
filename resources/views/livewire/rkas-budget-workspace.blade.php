<div class="flex flex-col gap-6">
    @php($rupiah = fn($value) => 'Rp ' . number_format((float) $value, 0, ',', '.'))

    <x-page-header title="Penganggaran RKAS" :subtitle="'Pantau pagu RKAS dan realisasi BKU pada konteks ' . $contextLabel . '.'" kicker="Anggaran & Realisasi">
        <x-slot:actions>
            <form method="POST" action="{{ route('arkas.sync') }}"
                data-confirm="Sinkronisasi akan memperbarui data RKAS dan BKU dari ARKAS. Lanjutkan?">
                @csrf
                <input type="hidden" name="confirm_sync" value="1">
                <x-ui.button type="submit" icon="sync">
                    <span>Sinkron Semua ARKAS</span>
                </x-ui.button>
            </form>
        </x-slot:actions>

        <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4">
            <x-stat-item label="Total Anggaran" :value="$rupiah($budget)" hint="RKAS tersinkron"
                value-class="text-[var(--theme-content-accent)]" icon="budget"
                icon-class="text-[var(--theme-content-accent)]" />
            <x-stat-item label="Realisasi BKU" :value="$rupiah($spent)" hint="Belanja tercatat" value-class="text-emerald-700"
                icon="transaction" icon-class="text-emerald-700" />
            <x-stat-item label="Sisa Anggaran" :value="$rupiah($remaining)" :hint="'Belum dibukukan ' . $rupiah($underBudget) . ' · Kelebihan ' . $rupiah($overBudget)" icon="balance"
                value-class="text-amber-700" icon-class="text-amber-700" />
            <x-stat-item label="Kegiatan RKAS" :value="number_format($activityCount, 0, ',', '.')" hint="Kegiatan tersinkron" icon="work"
                icon-class="text-[var(--theme-content-accent)]" />
        </div>
    </x-page-header>

    @php($revTabs = $revisions ?? [])
    @if (count($revTabs) > 0)
        @php($revQuery = array_filter(array_merge(request()->query(), ['revisi' => null])))
        @php($activeRevId = $revision ?? ($latestApprovedId ?? null))
        @php($activeRev = collect($revTabs)->firstWhere('id', $activeRevId) ?? collect($revTabs)->last())
        <section aria-label="Revisi RKAS"
            class="flex flex-wrap items-center gap-x-4 gap-y-3 rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-4 py-3 shadow-sm">
            <div class="ui-segment-group flex w-fit max-w-full overflow-x-auto" role="group" aria-label="Revisi RKAS">
                @foreach ($revTabs as $tabIndex => $tab)
                    @php($isActive = $tab['id'] === ($activeRev['id'] ?? null))
                    @php($isDefault = $tab['id'] === ($latestApprovedId ?? null))
                    <a href="{{ $isDefault ? route('rkas-budget.index', $revQuery) : route('rkas-budget.index', array_merge($revQuery, ['revisi' => $tab['id']])) }}"
                        wire:navigate aria-current="{{ $isActive ? 'true' : 'false' }}"
                        title="{{ $tab['status'] === 'pending' ? 'Pengajuan menunggu persetujuan' : 'Revisi disetujui' }} · Pagu Rp {{ number_format((float) $tab['amount'], 0, ',', '.') }}"
                        class="{{ $tabIndex > 0 ? '-ml-px' : '' }} whitespace-nowrap border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 py-2 text-sm no-underline transition {{ $isActive ? 'font-bold text-[var(--theme-content-accent)]' : 'font-medium text-[var(--ui-fg-muted)] hover:text-[var(--ui-fg-strong)]' }}"
                        style="{{ $isActive ? 'box-shadow: inset 0 -3px 0 var(--theme-action-bg);' : '' }}">{{ $tab['status'] === 'pending' ? 'Pengajuan' : 'Pengesahan' }}
                        ke-{{ $tab['seq'] ?? $tabIndex + 1 }}</a>
                @endforeach
            </div>
            <div class="min-w-0 text-xs leading-5 text-[var(--ui-fg-muted)]">
                @if (($activeRev['status'] ?? '') === 'pending')
                    <x-ui.status-badge status="PENDING" size="xs" />
                    <span class="ml-1">Pengajuan ke-{{ $activeRev['seq'] ?? '' }} ·
                        {{ $activeRev['dateLabel'] }} (menunggu persetujuan).</span>
                @elseif($activeRev)
                    <span>Pengesahan ke-{{ $activeRev['seq'] ?? '' }} · {{ $activeRev['dateLabel'] }}.</span>
                @else
                    <span>Belum ada revisi tersinkron pada konteks ini.</span>
                @endif
            </div>
            <a href="{{ route('rkas-budget.revisions.compare', ['to' => $activeRev['id'] ?? null]) }}"
                class="ui-btn ui-btn-secondary ml-auto !min-h-9 px-3 py-1.5 text-sm">
                <x-ui.icon name="report" size="sm" /> Bandingkan revisi
            </a>
            <a href="{{ route('rkas-budget.simulate') }}"
                class="ui-btn ui-btn-secondary !min-h-9 px-3 py-1.5 text-sm">
                <x-ui.icon name="edit" size="sm" /> Simulasi pagu
            </a>
            <a href="{{ route('rkas-budget.audit') }}"
                class="ui-btn ui-btn-secondary !min-h-9 px-3 py-1.5 text-sm">
                <x-ui.icon name="document" size="sm" /> Audit mirror
            </a>
        </section>
    @endif

    @include('rkas-budget.partials.filter')

    @php($reportRevisi = request()->query('revisi'))
    @php($reportQuery = $reportRevisi !== null && $reportRevisi !== '' ? ['revisi' => $reportRevisi] : [])
    <x-section-card title="Unduh Laporan RKAS"
        description="Kertas Kerja per pengesahan revisi dalam format PDF atau Excel. Revisi aktif mengikuti tab revisi di atas."
        class="order-last">
        <div x-data="{
            previewOpen: false,
            previewUrl: '',
            openPreview(url, value = '', parameter = 'bulan') {
                if (!value && (url.includes('/bulanan') || url.includes('/triwulan-bulanan'))) {
                    window.alert(url.includes('/triwulan-bulanan') ? 'Pilih triwulan laporan terlebih dahulu.' : 'Pilih bulan laporan terlebih dahulu.');
                    return;
                }
                const target = new URL(url, window.location.origin);
                if (value) target.searchParams.set(parameter, value);
                this.previewUrl = target.toString();
                this.previewOpen = true;
            },
            openDownload(url, value = '', parameter = 'bulan') {
                if (!value && (url.includes('/bulanan') || url.includes('/triwulan-bulanan'))) {
                    window.alert(url.includes('/triwulan-bulanan') ? 'Pilih triwulan laporan terlebih dahulu.' : 'Pilih bulan laporan terlebih dahulu.');
                    return;
                }
                const target = new URL(url, window.location.origin);
                if (value) target.searchParams.set(parameter, value);
                window.open(target.toString(), '_blank');
            },
            printPreview() {
                const frame = this.$refs.previewFrame;
                if (frame?.contentWindow) {
                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                }
            }
        }" @keydown.escape.window="previewOpen = false">
            <div class="grid gap-2 md:grid-cols-2">
                @foreach ([['tahunan', 'Tahunan', 'Pagu setahun per sumber dana (Operasi/Modal).'], ['tahap', 'Tahap', 'Alokasi Tahap 1 (TW 1+2) dan Tahap 2 (TW 3+4).'], ['triwulan', 'Triwulan', 'Alokasi TW 1 sampai TW 4.']] as [$reportScope, $reportLabel, $reportDesc])
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-4 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-[var(--ui-fg-strong)]">{{ $reportLabel }}</p>
                            <p class="text-xs text-[var(--ui-fg-muted)]">{{ $reportDesc }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button type="button" class="ui-btn ui-btn-primary !min-h-10 !min-w-10 !px-2 !py-2 text-sm"
                                title="Pratinjau {{ $reportLabel }}" aria-label="Pratinjau {{ $reportLabel }}"
                                @click="openPreview(@js(route('rkas-reports.preview', array_merge(['scope' => $reportScope], $reportQuery))))"><x-ui.icon name="preview"
                                    size="sm" /></button>
                            <a class="ui-btn ui-btn-danger !min-h-10 !min-w-10 !px-2 !py-2 text-sm"
                                title="Unduh PDF {{ $reportLabel }}" aria-label="Unduh PDF {{ $reportLabel }}"
                                href="{{ route('rkas-reports.pdf', array_merge(['scope' => $reportScope], $reportQuery)) }}"
                                target="_blank"><x-ui.icon name="pdf" size="sm" /></a>
                            <a class="ui-btn ui-btn-success !min-h-10 !min-w-10 !px-2 !py-2 text-sm"
                                title="Unduh Excel {{ $reportLabel }}" aria-label="Unduh Excel {{ $reportLabel }}"
                                href="{{ route('rkas-reports.excel', array_merge(['scope' => $reportScope], $reportQuery)) }}"><x-ui.icon
                                    name="excel" size="sm" /></a>
                        </div>
                    </div>
                @endforeach
                <div
                    class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-4 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-[var(--ui-fg-strong)]">Triwulan per Bulan</p>
                        <p class="text-xs text-[var(--ui-fg-muted)]">Rincian tiga bulan dalam triwulan terpilih.</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <label class="sr-only" for="rkas-report-triwulan-bulanan">Triwulan laporan</label>
                        <select id="rkas-report-triwulan-bulanan" x-ref="reportQuarter" required
                            class="ui-select px-3 py-2 text-sm">
                            <option value="">Pilih triwulan</option>
                            <option value="1">Triwulan I</option>
                            <option value="2">Triwulan II</option>
                            <option value="3">Triwulan III</option>
                            <option value="4">Triwulan IV</option>
                        </select>
                        <button type="button" class="ui-btn ui-btn-primary !min-h-10 !min-w-10 !px-2 !py-2 text-sm"
                            title="Pratinjau Triwulan per Bulan" aria-label="Pratinjau Triwulan per Bulan"
                            @click="openPreview(@js(route('rkas-reports.preview', array_merge(['scope' => 'triwulan-bulanan'], $reportQuery))), $refs.reportQuarter.value, 'triwulan')"><x-ui.icon
                                name="preview" size="sm" /></button>
                        <button type="button" class="ui-btn ui-btn-danger !min-h-10 !min-w-10 !px-2 !py-2 text-sm"
                            title="Unduh PDF Triwulan per Bulan" aria-label="Unduh PDF Triwulan per Bulan"
                            @click="openDownload(@js(route('rkas-reports.pdf', array_merge(['scope' => 'triwulan-bulanan'], $reportQuery))), $refs.reportQuarter.value, 'triwulan')"><x-ui.icon
                                name="pdf" size="sm" /></button>
                        <button type="button" class="ui-btn ui-btn-success !min-h-10 !min-w-10 !px-2 !py-2 text-sm"
                            title="Unduh Excel Triwulan per Bulan" aria-label="Unduh Excel Triwulan per Bulan"
                            @click="openDownload(@js(route('rkas-reports.excel', array_merge(['scope' => 'triwulan-bulanan'], $reportQuery))), $refs.reportQuarter.value, 'triwulan')"><x-ui.icon
                                name="excel" size="sm" /></button>
                    </div>
                </div>
                <div
                    class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-4 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-[var(--ui-fg-strong)]">Bulanan</p>
                        <p class="text-xs text-[var(--ui-fg-muted)]">Rincian per bulan terpilih.</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <label class="sr-only" for="rkas-report-bulan">Bulan laporan</label>
                        <select id="rkas-report-bulan" x-ref="reportMonth" required
                            class="ui-select px-3 py-2 text-sm">
                            <option value="">Pilih bulan…</option>
                            @foreach (['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] as $monthIndex => $monthName)
                                <option value="{{ $monthIndex + 1 }}">{{ $monthName }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="ui-btn ui-btn-primary !min-h-10 !min-w-10 !px-2 !py-2 text-sm"
                            title="Pratinjau Bulanan" aria-label="Pratinjau Bulanan"
                            @click="openPreview(@js(route('rkas-reports.preview', array_merge(['scope' => 'bulanan'], $reportQuery))), $refs.reportMonth.value)"><x-ui.icon
                                name="preview" size="sm" /></button>
                        <button type="button" class="ui-btn ui-btn-danger !min-h-10 !min-w-10 !px-2 !py-2 text-sm"
                            title="Unduh PDF Bulanan" aria-label="Unduh PDF Bulanan"
                            @click="openDownload(@js(route('rkas-reports.pdf', array_merge(['scope' => 'bulanan'], $reportQuery))), $refs.reportMonth.value)"><x-ui.icon
                                name="pdf" size="sm" /></button>
                        <button type="button" class="ui-btn ui-btn-success !min-h-10 !min-w-10 !px-2 !py-2 text-sm"
                            title="Unduh Excel Bulanan" aria-label="Unduh Excel Bulanan"
                            @click="openDownload(@js(route('rkas-reports.excel', array_merge(['scope' => 'bulanan'], $reportQuery))), $refs.reportMonth.value)"><x-ui.icon
                                name="excel" size="sm" /></button>
                    </div>
                </div>
            </div>
            <form method="GET" action="{{ route('rkas-reports.package') }}"
                class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4">
                <input type="hidden" name="revisi" value="{{ $reportRevisi ?? '' }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-fg-strong)]">Paket laporan</h3>
                        <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Pilih beberapa laporan untuk konteks sekolah, tahun, sumber dana, dan revisi aktif yang sama.</p>
                    </div>
                    <x-ui.button type="submit" icon="download">Unduh paket ZIP</x-ui.button>
                </div>
                <fieldset class="mt-4">
                    <legend class="text-xs font-semibold text-[var(--ui-fg-strong)]">Jenis laporan</legend>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ([['tahunan', 'Tahunan'], ['tahap', 'Tahap'], ['triwulan', 'Triwulan'], ['triwulan-bulanan', 'Triwulan per bulan'], ['bulanan', 'Bulanan']] as [$packageScope, $packageLabel])
                            <label class="flex items-center gap-2 rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 py-2 text-sm text-[var(--ui-fg)]">
                                <input type="checkbox" name="scopes[]" value="{{ $packageScope }}">
                                <span>{{ $packageLabel }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <fieldset>
                        <legend class="text-xs font-semibold text-[var(--ui-fg-strong)]">Format</legend>
                        <div class="mt-2 flex flex-wrap gap-3 text-sm text-[var(--ui-fg)]">
                            <label class="inline-flex items-center gap-2"><input type="checkbox" name="formats[]" value="pdf" checked> PDF</label>
                            <label class="inline-flex items-center gap-2"><input type="checkbox" name="formats[]" value="xlsx" checked> Excel</label>
                        </div>
                    </fieldset>
                    <x-ui.field label="Bulan (untuk laporan Bulanan)" for="rkas-package-month">
                        <x-ui.select id="rkas-package-month" name="bulan">
                            <option value="">Pilih bulan</option>
                            @foreach (\App\Services\RkasReportService::MONTH_NAMES as $monthIndex => $monthName)
                                <option value="{{ $monthIndex + 1 }}">{{ $monthName }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.field label="Triwulan (untuk laporan Triwulan per bulan)" for="rkas-package-quarter">
                        <x-ui.select id="rkas-package-quarter" name="triwulan">
                            <option value="">Pilih triwulan</option>
                            <option value="1">Triwulan I</option><option value="2">Triwulan II</option>
                            <option value="3">Triwulan III</option><option value="4">Triwulan IV</option>
                        </x-ui.select>
                    </x-ui.field>
                    <p class="self-end text-xs leading-5 text-[var(--ui-fg-muted)]">Laporan Bulanan dan Triwulan per bulan memerlukan periode yang dipilih.</p>
                </div>
            </form>
            <div x-show="previewOpen" x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 pr-8 pb-8" role="dialog"
                aria-modal="true" aria-label="Pratinjau laporan RKAS">
                <div class="flex h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-[var(--ui-surface)] shadow-2xl"
                    @click.outside="previewOpen = false">
                    <div class="flex items-center justify-between border-b border-[var(--ui-line)] px-4 py-3">
                        <h2 class="font-bold text-[var(--ui-fg-strong)]">Pratinjau Laporan RKAS</h2>
                        <div class="flex items-center gap-2">
                            <button type="button" class="ui-btn ui-btn-primary !min-h-8 !px-3 !py-1.5 text-sm"
                                @click="printPreview()" aria-label="Cetak laporan"><x-ui.icon name="print"
                                    size="sm" /> Cetak</button>
                            <button type="button" class="ui-btn ui-btn-secondary !min-h-8 !px-3 !py-1.5 text-sm"
                                @click="previewOpen = false" aria-label="Tutup pratinjau">Tutup</button>
                        </div>
                    </div>
                    <iframe x-ref="previewFrame" class="min-h-0 flex-1 bg-white pt-8 pr-2 pb-6 pl-6" :src="previewUrl"
                        title="Pratinjau laporan RKAS"></iframe>
                </div>
            </div>
        </div>
    </x-section-card>

    <x-section-card title="Rincian Hierarki RKAS" :description="'Pagu, realisasi, dan sisa ' . $filterContext . '.'" :padding="false">
        <x-slot:actions>
            <span class="hidden xl:inline" style="color: var(--ui-fg-muted)">•
                {{ number_format($treeTotals['items'], 0, ',', '.') }} data</span>
            <a class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition hover:brightness-95"
                style="border-color: var(--ui-line); color: var(--ui-fg-muted); background: var(--ui-bg)"
                href="{{ route('synced-data.show', 'rkas') }}">
                <x-ui.icon name="database" class="h-4 w-4" />
                <span>Data Mentah</span>
            </a>
        </x-slot:actions>

        <div class="mt-2 overflow-x-auto rounded-xl border" style="border-color: var(--ui-line)">
            <table class="min-w-[1100px] w-full divide-y text-sm" style="border-color: var(--ui-line)"
                data-pagination="none">
                <thead style="background: var(--ui-surface-soft)">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-bold uppercase" style="color: var(--ui-fg-muted)">
                            Kode Program</th>
                        <th class="min-w-[260px] px-3 py-2 text-left text-xs font-bold uppercase"
                            style="color: var(--ui-fg-muted)">Uraian</th>
                        <th class="px-3 py-2 text-left text-xs font-bold uppercase" style="color: var(--ui-fg-muted)">
                            Kode Rekening</th>
                        <th class="px-3 py-2 text-right text-xs font-bold uppercase"
                            style="color: var(--ui-fg-muted)">Volume</th>
                        <th class="px-3 py-2 text-left text-xs font-bold uppercase" style="color: var(--ui-fg-muted)">
                            Satuan</th>
                        <th class="px-3 py-2 text-right text-xs font-bold uppercase"
                            style="color: var(--ui-fg-muted)">Tarif Harga</th>
                        <th class="px-3 py-2 text-right text-xs font-bold uppercase"
                            style="color: var(--ui-fg-muted)">Pagu</th>
                        <th class="px-3 py-2 text-right text-xs font-bold uppercase"
                            style="color: var(--ui-fg-muted)">Realisasi</th>
                        <th class="px-3 py-2 text-right text-xs font-bold uppercase"
                            style="color: var(--ui-fg-muted)">Selisih</th>
                    </tr>
                </thead>
                <tbody x-data="{ open: {} }" class="divide-y" style="border-color: var(--ui-line)">
                    @forelse($hierarchyTree as $program)
                        <tr
                            style="background: color-mix(in srgb, var(--theme-accent-soft) 45%, var(--ui-surface-base))">
                            <td colspan="6" class="px-3 py-1.5 text-xs">
                                <button type="button" class="inline-flex items-center gap-2 text-left font-bold"
                                    style="color: var(--ui-fg-strong)"
                                    x-on:click="open['p-{{ $program['code'] }}'] = ! open['p-{{ $program['code'] }}']">
                                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full border"
                                        style="color: var(--theme-content-accent); border-color: color-mix(in srgb, var(--theme-content-accent) 55%, var(--ui-line)); background: color-mix(in srgb, var(--theme-accent-soft) 55%, var(--ui-surface-base))">
                                        <x-ui.icon name="chevron-down" size="xs"
                                            x-show="open['p-{{ $program['code'] }}']" />
                                        <x-ui.icon name="chevron-right" size="xs"
                                            x-show="! open['p-{{ $program['code'] }}']" />
                                    </span>
                                    <span class="font-mono">{{ $program['code'] }}</span>
                                    <span>· {{ $program['name'] }}</span>
                                </button>
                            </td>
                            <td class="whitespace-nowrap px-3 py-1.5 text-right text-xs font-bold"
                                style="color: var(--theme-content-accent)">{{ $rupiah($program['amount']) }}
                            </td>
                            <td
                                class="whitespace-nowrap px-3 py-1.5 text-right text-xs font-semibold text-emerald-700">
                                {{ $rupiah($program['realization']) }}</td>
                            <td class="whitespace-nowrap px-3 py-1.5 text-right text-xs font-bold"
                                style="color: var(--ui-fg-muted)">{{ $rupiah($program['remaining']) }}</td>
                        </tr>
                        @foreach ($program['subs'] as $sub)
                            <tr x-show="open['p-{{ $program['code'] }}']"
                                style="background: color-mix(in srgb, var(--theme-accent-soft) 25%, var(--ui-surface-base))">
                                <td colspan="6" class="px-3 py-1 pl-8 text-xs">
                                    <button type="button"
                                        class="inline-flex items-center gap-2 text-left font-semibold"
                                        style="color: var(--ui-fg-strong)"
                                        x-on:click="open['s-{{ $sub['code'] }}'] = ! open['s-{{ $sub['code'] }}']">
                                        <span
                                            class="inline-flex h-4 w-4 items-center justify-center rounded-full border"
                                            style="color: var(--theme-accent-strong); border-color: color-mix(in srgb, var(--theme-accent-strong) 55%, var(--ui-line)); background: color-mix(in srgb, var(--theme-accent-soft) 35%, var(--ui-surface-base))">
                                            <x-ui.icon name="chevron-down" size="xs"
                                                x-show="open['s-{{ $sub['code'] }}']" />
                                            <x-ui.icon name="chevron-right" size="xs"
                                                x-show="! open['s-{{ $sub['code'] }}']" />
                                        </span>
                                        <span class="font-mono">{{ $sub['code'] }}</span>
                                        <span>· {{ $sub['name'] }}</span>
                                    </button>
                                </td>
                                <td class="whitespace-nowrap px-3 py-1 text-right text-xs font-semibold"
                                    style="color: var(--theme-content-accent)">{{ $rupiah($sub['amount']) }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-1 text-right text-xs text-emerald-700">
                                    {{ $rupiah($sub['realization']) }}</td>
                                <td class="whitespace-nowrap px-3 py-1 text-right text-xs font-semibold"
                                    style="color: var(--ui-fg-muted)">{{ $rupiah($sub['remaining']) }}</td>
                            </tr>
                            @foreach ($sub['activities'] as $activity)
                                <tr x-show="open['p-{{ $program['code'] }}'] && open['s-{{ $sub['code'] }}']"
                                    style="background: var(--ui-surface-soft)">
                                    <td colspan="6" class="px-3 py-1 pl-14 text-xs">
                                        <button type="button"
                                            class="inline-flex items-center gap-2 text-left font-semibold"
                                            style="color: var(--ui-fg-strong)"
                                            x-on:click="open['k-{{ $activity['code'] }}'] = ! open['k-{{ $activity['code'] }}']">
                                            <span
                                                class="inline-flex h-4 w-4 items-center justify-center rounded-full border"
                                                style="color: var(--theme-accent-strong); border-color: color-mix(in srgb, var(--theme-accent-strong) 55%, var(--ui-line)); background: color-mix(in srgb, var(--theme-accent-soft) 35%, var(--ui-surface-base))">
                                                <x-ui.icon name="chevron-down" size="xs"
                                                    x-show="open['k-{{ $activity['code'] }}']" />
                                                <x-ui.icon name="chevron-right" size="xs"
                                                    x-show="! open['k-{{ $activity['code'] }}']" />
                                            </span>
                                            <span class="font-mono">{{ $activity['code'] }}</span>
                                            <span>· {{ $activity['name'] }}</span>
                                        </button>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-1 text-right text-xs font-semibold"
                                        style="color: var(--theme-content-accent)">
                                        {{ $rupiah($activity['amount']) }}</td>
                                    <td class="whitespace-nowrap px-3 py-1 text-right text-xs text-emerald-700">
                                        {{ $rupiah($activity['realization']) }}</td>
                                    <td class="whitespace-nowrap px-3 py-1 text-right text-xs font-semibold"
                                        style="color: var(--ui-fg-muted)">
                                        {{ $rupiah($activity['remaining']) }}</td>
                                </tr>
                                @foreach ($activity['items'] as $item)
                                    <tr x-show="open['p-{{ $program['code'] }}'] && open['s-{{ $sub['code'] }}'] && open['k-{{ $activity['code'] }}']"
                                        class="text-xs">
                                        <td class="whitespace-nowrap px-3 py-1 pl-20 font-mono"
                                            style="color: var(--ui-fg-muted)">
                                            {{ trim((string) $item->activity_code, '.') }}</td>
                                        <td class="px-3 py-1">
                                            <p class="font-semibold leading-tight" style="color: var(--ui-fg-strong)">
                                                {{ $item->description ?: 'Tanpa uraian' }}</p>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-1 font-mono"
                                            style="color: var(--ui-fg-muted)">
                                            {{ $item->account_code ?: 'Tanpa kode rekening' }}</td>
                                        <td class="whitespace-nowrap px-3 py-1 text-right">
                                            {{ rtrim(rtrim(number_format($item->volume, 2, ',', '.'), '0'), ',') }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-1">{{ $item->unit }}</td>
                                        <td class="whitespace-nowrap px-3 py-1 text-right">
                                            {{ $rupiah($item->unit_price) }}</td>
                                        <td class="whitespace-nowrap px-3 py-1 text-right font-semibold"
                                            style="color: var(--theme-content-accent)">
                                            {{ $rupiah($item->display_amount) }}</td>
                                        <td class="whitespace-nowrap px-3 py-1 text-right text-emerald-700">
                                            {{ $rupiah($item->realization) }}</td>
                                        <td class="whitespace-nowrap px-3 py-1 text-right"
                                            style="color: var(--ui-fg-muted)">{{ $rupiah($item->variance) }}
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-14 text-center">
                                <p class="text-sm font-semibold" style="color: var(--ui-fg-strong)">Belum ada
                                    RKAS.</p>
                                <p class="mt-1 text-base" style="color: var(--ui-fg-muted)">Jalankan
                                    sinkronisasi atau ubah kata kunci pencarian.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-section-card>
</div>
