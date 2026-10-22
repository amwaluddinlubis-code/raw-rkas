<x-layouts.tailwind-app>
    @php
        $spjProgress = ($totalPackages ?? 0) > 0 ? min(100, (int) round((($numberedPackages ?? 0) / $totalPackages) * 100)) : 0;
        $packagesAwaitingNumber = max(0, ($totalPackages ?? 0) - ($numberedPackages ?? 0));
        $transactionsWithoutPackage = max(0, ($readyTransactions ?? 0) - ($totalPackages ?? 0));
    @endphp
    <div class="spj-semantic-workspace space-y-6" x-data="{
        tab: '{{ $tab ?? 'persiapan' }}',
        loadingTab: false,
        changeTab(name) {
            if (this.tab === name || this.loadingTab) {
                return;
            }
            const url = new URL(window.location.href);
            url.searchParams.set('tab', name);
            if (name !== 'paket') url.searchParams.delete('package_id');
            if (window.Livewire && typeof window.Livewire.navigate === 'function') {
                window.Livewire.navigate(url.toString());
                return;
            }
            this.loadingTab = true;
            window.location.assign(url.toString());
        }
    }" @click="const button = $event.target.closest('[data-tab]'); if (button) { $event.preventDefault(); changeTab(button.dataset.tab); }">
        <x-page-header
            title="Pusat Dokumen SPJ"
            subtitle="Kelola alur dari transaksi siap, kelengkapan paket, hingga dokumen bernomor dalam satu ruang kerja."
            kicker="MANAJEMEN DOKUMEN PERTANGGUNGJAWABAN"
        >
            <x-slot:actions>
                <x-ui.button variant="secondary" :href="route('transactions.index')">Lihat transaksi</x-ui.button>
                <x-ui.button type="button" data-tab="monitoring">Periksa kendala</x-ui.button>
            </x-slot:actions>

            @include('spj.partials.summary')
        </x-page-header>

        {{-- Tab Navigation --}}
        <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow">
            @include('spj.partials.main-tabs')

            {{-- Tab: Persiapan (filter AJAX via Livewire, tanpa reload; hanya tab aktif yang dirender server) --}}
            @if(($tab ?? 'persiapan') === 'persiapan')
            <div x-show="tab === 'persiapan'" x-transition>
                <livewire:spj-preparation-filter />
            </div>
            @endif
            {{-- Tab: Paket --}}
            @if(($tab ?? 'persiapan') === 'paket')
            <div x-show="tab === 'paket'" x-transition>
                @php
                    if (isset($package)) {
                @endphp
                    @php
                        $transaction = $package->transaction;
                        $participantRows = $transaction->items
                            ->flatMap(fn ($item) => $item->participants)
                            ->sortBy(fn ($participant) => [(int) ($participant->sort_order ?? 0), (int) $participant->getKey()])
                            ->map(fn ($participant) => ['name' => $participant->name, 'position' => $participant->position, 'nip' => $participant->nip, 'nuptk' => $participant->nuptk, 'portions' => (int) $participant->portions, 'is_primary' => (bool) $participant->is_primary])
                            ->values()
                            ->all();
                    @endphp
                    @if(strtoupper((string) $transaction->spj_category) === 'KONSUMSI' && $participantRows === [])
                        @php
                            $participantRows = collect($participantRoster ?? [])->map(fn ($employee) => ['name' => $employee->name, 'position' => $employee->position ?: $employee->staff_type, 'nip' => $employee->nip, 'nuptk' => $employee->nuptk, 'portions' => 1])->values()->all();
                        @endphp
                    @endif
                    @php
                        $participantRows = old('participants', $participantRows);
                        $activeSpjDocument = $package->documents->first(fn ($document) => $document->document_type === 'SPJ' && $document->scope_key === 'MAIN' && in_array($document->status, ['NUMBERED', 'FINAL'], true) && filled($document->document_number));
                        $cancelledSpjDocument = $package->documents->where('document_type', 'SPJ')->where('scope_key', 'MAIN')->where('status', 'CANCELLED')->sortByDesc('id')->first();
                        $hasActiveSpjNumber = $activeSpjDocument !== null && $package->status !== 'CANCELLED';
                        $packageCategory = strtoupper((string) $transaction->spj_category);
                        $isHonorPackage = in_array($packageCategory, ['HONOR_PEGAWAI', 'JASA_HONORARIUM'], true);
                        $isGoodsPackage = in_array($packageCategory, ['BARANG', 'KONSUMSI'], true);
                        $isConsumptionPackage = $packageCategory === 'KONSUMSI';
                        $isSiplah = (bool) $transaction->is_siplah || strtolower((string) $transaction->payment_method) === 'siplah';
                        $purchaseDetails = $transaction->goods->first();
                        $transactionDateLimit = $transaction->sourceCarbon()?->format('Y-m-d') ?: $transaction->transaction_date?->format('Y-m-d');
                        $orderDate = $purchaseDetails?->order_date?->format('Y-m-d') ?: $transaction->order_date?->format('Y-m-d') ?: $transactionDateLimit;
                        $bapDate = $purchaseDetails?->bap_date?->format('Y-m-d') ?: $transaction->bap_date?->format('Y-m-d') ?: $transactionDateLimit;
                        $bastDate = $purchaseDetails?->bast_date?->format('Y-m-d') ?: $transaction->bast_date?->format('Y-m-d') ?: $transactionDateLimit;
                    @endphp
                    <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-4">
                        <x-ui.button variant="secondary" :href="route('spj.index', ['tab' => 'persiapan'])">← Semua paket</x-ui.button>
                        <x-ui.button variant="secondary" :href="route('transactions.show', $transaction->id)">Lihat transaksi</x-ui.button>
                    </div>

                    @include('spj.partials.package.transaction-summary')
                    @if(strtolower((string) $transaction->payment_method) === 'siplah' || $transaction->is_siplah)
                        <section class="mx-5 mt-5 rounded-xl border p-4 shadow-sm" style="border-color: var(--ui-line); background: var(--ui-surface-soft)">
                            <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="text-base font-bold" style="color: var(--ui-fg)">Metode Pembelian: SiPLah</h2><x-ui.badge>Pembelian SiPLah</x-ui.badge></div>
                            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-5">
                                @foreach([
                                    'Penyedia' => $transaction->vendor_name,
                                    'Nomor Pesanan SiPLah' => $transaction->siplah_order_number,
                                    'Nomor Invoice' => $transaction->invoice_number,
                                    'Tanggal Invoice' => $transaction->invoice_date?->translatedFormat('d F Y'),
                                    'Referensi Pembayaran' => $transaction->payment_reference,
                                ] as $label => $value)
                                    @if(filled($value))<div><dt class="text-xs font-semibold" style="color: var(--ui-fg-muted)">{{ $label }}</dt><dd class="mt-1 font-semibold" style="color: var(--ui-fg)">{{ $value }}</dd></div>@endif
                                @endforeach
                            </dl>
                        </section>
                    @endif

                    <section class="mx-5 mt-5 overflow-hidden rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow" x-data="{ packageTab: new URLSearchParams(window.location.search).get('package_tab') || 'rincian', selectPackageTab(name) { this.packageTab = name; const url = new URL(window.location.href); url.searchParams.set('package_tab', name); window.history.replaceState({}, '', url); } }">
                        <div class="ui-tabs">
                            <nav class="ui-tabs-list" role="tablist" aria-label="Bagian Paket SPJ" @click="const button = $event.target.closest('[data-package-tab]'); if (button) selectPackageTab(button.dataset.packageTab)" @keydown="if ($event.key === 'ArrowRight' || $event.key === 'ArrowLeft') { const buttons = [...$el.querySelectorAll('[data-package-tab]')]; const current = buttons.indexOf($event.target); const next = $event.key === 'ArrowRight' ? (current + 1) % buttons.length : (current - 1 + buttons.length) % buttons.length; buttons[next].focus(); selectPackageTab(buttons[next].dataset.packageTab); }">
                                <button type="button" role="tab" id="package-tab-rincian" aria-controls="package-panel-rincian" data-package-tab="rincian" :aria-selected="(packageTab === 'rincian').toString()" :class="packageTab === 'rincian' ? 'ui-tab ui-tab-active' : 'ui-tab'"><span class="ui-tab-icon"><x-ui.icon name="document" size="sm" /></span><span>Rincian</span> <span class="ml-1 rounded-full bg-[var(--ui-surface-muted)] px-1.5 py-0.5 text-[11px]">{{ $transaction->items->count() }}</span></button>
                                <button type="button" role="tab" id="package-tab-isian" aria-controls="package-panel-isian" data-package-tab="isian" :aria-selected="(packageTab === 'isian').toString()" :class="packageTab === 'isian' ? 'ui-tab ui-tab-active' : 'ui-tab'"><span class="ui-tab-icon"><x-ui.icon name="edit" size="sm" /></span><span>Isian Manual</span></button>
                                <button type="button" role="tab" id="package-tab-pajak" aria-controls="package-panel-pajak" data-package-tab="pajak" :aria-selected="(packageTab === 'pajak').toString()" :class="packageTab === 'pajak' ? 'ui-tab ui-tab-active' : 'ui-tab'"><span class="ui-tab-icon"><x-ui.icon name="tax" size="sm" /></span><span>Rincian Pajak</span></button>
                                <button type="button" role="tab" id="package-tab-penomoran" aria-controls="package-panel-penomoran" data-package-tab="penomoran" :aria-selected="(packageTab === 'penomoran').toString()" :class="packageTab === 'penomoran' ? 'ui-tab ui-tab-active' : 'ui-tab'"><span class="ui-tab-icon"><x-ui.icon name="number" size="sm" /></span><span>Penomoran</span> @if($hasActiveSpjNumber)<span class="ml-1 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[11px] text-emerald-700">OK</span>@elseif($package->status === 'CANCELLED')<span class="ml-1 rounded-full bg-rose-100 px-1.5 py-0.5 text-[11px] text-rose-700">Dibatalkan</span>@else<span class="ml-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-[11px] text-amber-700">Belum</span>@endif</button>
                            </nav>
                        </div>

                        <div x-show="packageTab === 'rincian'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" id="package-panel-rincian" role="tabpanel" aria-labelledby="package-tab-rincian" data-panel="rincian" class="tab-panel">
                            @include('spj.partials.package.items-readonly')
                            <div class="mt-5 border-t border-[var(--ui-line)] pt-1">
                                @include('spj.partials.package.validation')
                                @include('spj.partials.package.documents')
                            </div>
                            <div class="grid grid-cols-1 gap-2 px-4 py-4 sm:grid-cols-2">
                                @include('spj.partials.package.package-navigation', ['packageTab' => 'rincian'])
                            </div>
                        </div>

                        <div x-show="packageTab === 'isian'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" id="package-panel-isian" role="tabpanel" aria-labelledby="package-tab-isian" data-panel="isian" class="tab-panel" x-data="{saving:false}">
                            <div class="border-b border-[var(--ui-line)] px-4 py-3">
                                <h2 class="text-base font-bold text-[var(--ui-fg-strong)]">Isian Manual Paket SPJ</h2>
                                <p class="mt-0.5 text-xs text-[var(--ui-fg-muted)]">Hanya isian kuning yang wajib. Bagian biru tampil sesuai kategori.</p>
                            </div>
                            @include('spj.partials.package.manual-form')
                        </div>

                        <div x-show="packageTab === 'pajak'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" id="package-panel-pajak" role="tabpanel" aria-labelledby="package-tab-pajak" data-panel="pajak" class="tab-panel p-4">
                            @include('spj.partials.package.tax-reference')
                            <div class="grid grid-cols-1 gap-2 pt-4 sm:grid-cols-2">
                                @include('spj.partials.package.package-navigation', ['packageTab' => 'pajak'])
                            </div>
                        </div>

                        <div x-show="packageTab === 'penomoran'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" id="package-panel-penomoran" role="tabpanel" aria-labelledby="package-tab-penomoran" data-panel="penomoran" class="tab-panel p-4">
                            @include('spj.partials.package.numbering')
                            <div class="grid grid-cols-1 gap-2 pt-4 sm:grid-cols-2">
                                @include('spj.partials.package.package-navigation', ['packageTab' => 'penomoran'])
                            </div>
                        </div>
                    </section>
                @php
                    } else {
                @endphp
                    <livewire:spj-package-list />
                @php
                    }
                @endphp
            </div>
            @endif

            {{-- Tab: Attribut SPJ --}}
            @if(($tab ?? 'persiapan') === 'atribut')
            <div x-show="tab === 'atribut'" x-transition>
                <livewire:spj-attribute-list />
            </div>
            @endif

            {{-- Tab: Laporan (filter AJAX via Livewire, tanpa reload) --}}
            @if(($tab ?? 'persiapan') === 'laporan')
            <div x-show="tab === 'laporan'" x-transition>
                <livewire:spj-report-filter />
            </div>
            @endif

            {{-- Tab: Monitoring --}}
            @if(($tab ?? 'persiapan') === 'monitoring')
            <div x-show="tab === 'monitoring'" x-transition>
                @include('spj.partials.monitoring')
            </div>
            @endif
        </section>
    </div>

    @include('spj.partials.preview-modal')

    <script>
        (() => {
            // Delegasi penuh di document agar modal tetap berfungsi setelah
            // navigasi SPA mengganti isi body (tanpa listener pada elemen).
            const templatePreview = (action, button) => {
                const modal = document.getElementById('template-preview-modal');
                if (!modal) return;
                const frame = document.getElementById('template-preview-frame');
                const title = document.getElementById('template-preview-title');
                const downloadPdf = document.getElementById('template-preview-download-pdf');
                const downloadExcel = document.getElementById('template-preview-download-excel');
                if (action === 'close') {
                    modal.classList.add('hidden'); modal.classList.remove('flex');
                    if (frame) frame.src = 'about:blank';
                    [downloadPdf, downloadExcel].forEach((link) => {
                        if (!link) return;
                        link.href = '#';
                        link.classList.add('hidden');
                    });
                    return;
                }
                if (!button || !frame) return;
                if (title) title.textContent = button.dataset.templateName || 'Pratinjau Template';
                frame.src = button.dataset.templatePreviewPdf || button.dataset.templatePreview;
                [[downloadPdf, button.dataset.templateDownloadPdf], [downloadExcel, button.dataset.templateDownloadExcel]].forEach(([link, url]) => {
                    if (!link) return;
                    link.classList.toggle('hidden', !url);
                    if (url) link.href = url;
                });
                modal.classList.remove('hidden'); modal.classList.add('flex');
            };
            const updateBulkSelection = () => {
                const selectedCount = document.querySelectorAll('[data-bulk-package]:checked').length;
                const status = document.querySelector('[data-bulk-selection-status]');
                const submit = document.querySelector('[data-bulk-submit]');
                const overLimit = selectedCount > 20;

                if (status) {
                    const message = selectedCount === 0
                        ? '0 dari 20 paket dipilih. Pilih minimal 1 paket.'
                        : overLimit
                            ? `${selectedCount} paket dipilih. Maksimal 20; batalkan pilihan ${selectedCount - 20} paket.`
                            : `${selectedCount} dari 20 paket dipilih. Siap untuk Bulk Preview.`;
                    if (status.textContent.trim() !== message) status.textContent = message;
                    status.classList.toggle('text-rose-700', overLimit);
                    status.classList.toggle('text-[var(--ui-fg-muted)]', !overLimit);
                }
                if (submit) submit.disabled = selectedCount === 0 || overLimit;

                return selectedCount;
            };
            document.addEventListener('click', (event) => {
                if (event.target.closest('[data-close-template-preview]')) {
                    templatePreview('close');
                    return;
                }
                const modal = document.getElementById('template-preview-modal');
                if (modal && !modal.classList.contains('hidden') && event.target === modal) {
                    templatePreview('close');
                    return;
                }
                const button = event.target.closest('[data-template-preview]');
                if (!button || button.closest('[inert]')) return;
                templatePreview('open', button);
            });
            document.addEventListener('change', (event) => {
                const selectAll = event.target.closest('[data-bulk-select-all]');
                if (selectAll) {
                    const report = selectAll.closest('table');
                    report?.querySelectorAll('[data-bulk-package]').forEach((checkbox) => { checkbox.checked = selectAll.checked; });
                    updateBulkSelection();
                    return;
                }
                if (event.target.matches('[data-bulk-package]')) {
                    const report = event.target.closest('table');
                    const boxes = [...(report?.querySelectorAll('[data-bulk-package]') || [])];
                    const selectAllBox = report?.querySelector('[data-bulk-select-all]');
                    if (selectAllBox) {
                        selectAllBox.checked = boxes.length > 0 && boxes.every((checkbox) => checkbox.checked);
                        selectAllBox.indeterminate = boxes.some((checkbox) => checkbox.checked) && !selectAllBox.checked;
                    }
                    updateBulkSelection();
                }
            });
            document.addEventListener('submit', (event) => {
                const form = event.target.closest('[data-bulk-preview-form]');
                if (!form) return;
                const selectedCount = updateBulkSelection();
                if (selectedCount === 0 || selectedCount > 20) {
                    event.preventDefault();
                    return;
                }
                const modal = document.getElementById('template-preview-modal');
                const title = document.getElementById('template-preview-title');
                const frame = document.getElementById('template-preview-frame');
                if (title) title.textContent = `Pratinjau Massal ${selectedCount} Paket SPJ`;
                if (frame) frame.src = 'about:blank';
                modal?.classList.remove('hidden');
                modal?.classList.add('flex');
            });
            updateBulkSelection();
            const bulkReportRoot = document.querySelector('[data-bulk-report-root]');
            if (bulkReportRoot && 'MutationObserver' in window) {
                new MutationObserver(updateBulkSelection).observe(bulkReportRoot, { childList: true, subtree: true });
            }
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape') templatePreview('close'); });
        })();
    </script>
</x-layouts.tailwind-app>
