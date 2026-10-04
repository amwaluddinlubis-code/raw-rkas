<x-layouts.tailwind-app>
    @php
        $transaction = $package->transaction;
        $packageUrl = route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]);
        $transactionUrl = route('transactions.show', $transaction->id);
        $failedChecks = $checklist->where('passed', false)->values();
        $blockingRequirements = $documentRequirements->filter(fn ($item) => $item['applicable'] && $item['required'] && ! $item['available'])->values();
        $blockingCount = $failedChecks->count() + $blockingRequirements->count();
        $passedChecks = $checklist->where('passed', true)->values();
        $readyRequirements = $documentRequirements->filter(fn ($item) => $item['applicable'] && $item['available'])->values();
        $optionalMissing = $documentRequirements->filter(fn ($item) => $item['applicable'] && ! $item['required'] && ! $item['available'])->values();
        $notApplicable = $documentRequirements->filter(fn ($item) => ! $item['applicable'])->values();
        $doneCount = $passedChecks->count() + $readyRequirements->count();
        $isTransactionUrl = fn ($url) => str_starts_with((string) $url, $transactionUrl);
        $spjTypeLabel = fn ($value): string => match (strtoupper((string) $value)) {
            'JASA_HONORARIUM', 'HONOR_PEGAWAI' => 'Honor Pegawai',
            'JASA_LAINNYA' => 'Jasa Lainnya',
            'BARANG' => 'Barang',
            'KONSUMSI' => 'Konsumsi',
            'PEMELIHARAAN' => 'Pemeliharaan',
            'SPPD' => 'SPPD',
            default => ucwords(strtolower(str_replace('_', ' ', (string) $value))),
        };
    @endphp
    <div class="spj-semantic-workspace space-y-6">
        <x-page-header
            :title="'Checklist — '.($transaction->sourceValue('no_bukti') ?: 'Tanpa nomor bukti')"
            :subtitle="($transaction->payment_description ?: $transaction->sourceValue('description') ?: 'Uraian belum tersedia').' · Rp '.number_format((float) $transaction->sourceValue('gross_amount'), 0, ',', '.')"
            kicker="Checklist Paket SPJ"
        >
            <x-slot:actions>
                <x-ui.button variant="secondary" :href="$packageUrl" class="print:hidden">Buka paket</x-ui.button>
                <x-ui.button variant="secondary" type="button" onclick="window.print()" class="print:hidden">Cetak pendamping</x-ui.button>
            </x-slot:actions>

            <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4">
                <x-stat-item label="Jalur pengadaan" :value="$requirementSummary['channel']" :hint="$transaction->spj_category ? $spjTypeLabel($transaction->spj_category) : 'Tanpa kategori'" value-class="text-[var(--theme-content-accent)]" icon="work" icon-class="text-[var(--theme-content-accent)]" />
                <x-stat-item label="Dokumen wajib siap" :value="$requirementSummary['required_ready'].' / '.$requirementSummary['required_total']" :hint="$progress.'% lengkap'" value-class="text-slate-800" icon="document" icon-class="text-slate-800" />
                <x-stat-item label="Masih menghalangi" :value="$blockingCount" :hint="$completedChecks.'/'.$totalChecks.' pemeriksaan lolos'" :value-class="$blockingCount > 0 ? 'text-amber-700' : 'text-emerald-700'" icon="warning" :icon-class="$blockingCount > 0 ? 'text-amber-700' : 'text-emerald-700'" />
                <x-stat-item label="Status paket" :value="$package->status" :hint="$transaction->sourceCarbon()?->translatedFormat('d F Y') ?: 'Tanggal belum tersedia'" value-class="text-indigo-700" icon="archive" icon-class="text-indigo-700" />
            </div>
        </x-page-header>

        <?php if ($blockingCount > 0): ?>
            <section class="rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4 text-sm leading-6 text-amber-900">
                <p class="font-bold">Belum siap diberi nomor — {{ $blockingCount }} hal perlu dilengkapi.</p>
                <p class="mt-0.5">Kerjakan berurutan dari nomor 1. Setiap baris menunjukkan di mana memperbaikinya (Paket atau Transaksi).</p>
            </section>

        <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <div class="border-b border-[var(--ui-line)] px-5 py-3">
                <h2 class="font-bold text-[var(--ui-fg-strong)]">Yang menghalangi ({{ $blockingCount }})</h2>
                </div>
                <ol class="divide-y divide-[var(--ui-line)]">
                    @foreach($failedChecks as $index => $check)
                        <li class="flex flex-col gap-3 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 gap-3">
                                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-amber-100 text-xs font-black text-amber-800">{{ $index + 1 }}</span>
                                <div class="min-w-0">
                                    <p class="font-bold text-[var(--ui-fg-strong)]">{{ $check['label'] }} <x-ui.badge variant="neutral">{{ $isTransactionUrl($check['url']) ? 'Transaksi' : 'Paket' }}</x-ui.badge></p>
                                    <p class="mt-0.5 text-sm leading-6 text-amber-800">{{ $check['message'] }}</p>
                                </div>
                            </div>
                            <x-ui.button variant="secondary" :href="$check['url']" class="shrink-0 text-xs print:hidden">Perbaiki →</x-ui.button>
                        </li>
                    @endforeach
                    @foreach($blockingRequirements as $index => $item)
                        @php
                            $fixUrl = $item['key'] === 'transaction_details'
                                ? $transactionUrl.'#rincian-transaksi'
                                : $packageUrl.'#spj-manual-form';
                        @endphp
                        <li class="flex flex-col gap-3 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 gap-3">
                                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-amber-100 text-xs font-black text-amber-800">{{ $failedChecks->count() + $index + 1 }}</span>
                                <div class="min-w-0">
                                    <p class="font-bold text-[var(--ui-fg-strong)]">{{ $item['label'] }} <x-ui.badge variant="neutral">{{ $item['key'] === 'transaction_details' ? 'Transaksi' : 'Paket' }}</x-ui.badge></p>
                                    <p class="mt-0.5 text-sm leading-6 text-amber-800">{{ $item['message'] }}</p>
                                    <p class="mt-0.5 text-xs text-[var(--ui-fg-muted)]">{{ $item['group'] }} · {{ $item['source'] }}</p>
                                </div>
                            </div>
                            <x-ui.button variant="secondary" :href="$fixUrl" class="shrink-0 text-xs print:hidden">Perbaiki →</x-ui.button>
                        </li>
                    @endforeach
                </ol>
            </section>
        <?php else: ?>
            <section class="rounded-2xl border border-emerald-300 bg-emerald-50 px-5 py-4 text-sm leading-6 text-emerald-900">
                <p class="font-bold">Semua kebutuhan wajib lengkap — paket siap dilanjutkan.</p>
                <?php if ($canMarkReady): ?>
                    <p class="mt-0.5">Gunakan tombol “Tandai siap diproses” di atas untuk melanjutkan ke penomoran.</p>
                <?php elseif ($package->status !== 'DRAFT'): ?>
                    <p class="mt-2"><x-ui.status-badge :status="$package->status" /></p>
                <?php elseif (! $canEdit): ?>
                    <p class="mt-0.5">Mode pemeriksa: data dapat dilihat, tetapi status paket tidak dapat diubah.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <div class="border-b border-[var(--ui-line)] px-5 py-3">
                <h2 class="font-bold text-[var(--ui-fg-strong)]">Cocokkan BKU – Kwitansi</h2>
                <p class="mt-0.5 text-xs leading-5 text-[var(--ui-fg-muted)]">Kiri: fakta ARKAS/BKU (readonly). Kanan: isian operator. Nomor, nilai, dan penerima harus saling cocok sebelum final.</p>
            </div>
            <ul class="divide-y divide-[var(--ui-line)]">
                @foreach($bkuMatch['rows'] as $row)
                    <li class="flex flex-col gap-1 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm font-bold text-[var(--ui-fg-strong)]">{{ $row['label'] }}</p>
                        <div class="flex min-w-0 flex-col gap-1 text-sm sm:flex-row sm:items-center sm:gap-6">
                            <p class="min-w-0 text-[var(--ui-fg-muted)]"><span class="font-semibold">BKU:</span> {{ $row['source'] }}</p>
                            <p class="min-w-0 font-semibold {{ $row['match'] ? 'text-emerald-700' : 'text-amber-800' }}"><span class="font-semibold">Kwitansi:</span> {{ $row['overlay'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        @if($categorySuggestion !== null || $helperHints !== [])
            <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
                <div class="border-b border-[var(--ui-line)] px-5 py-3">
                    <h2 class="font-bold text-[var(--ui-fg-strong)]">Saran pemeriksa — tidak memblokir penomoran</h2>
                    <p class="mt-0.5 text-xs leading-5 text-[var(--ui-fg-muted)]">Hint dari ambang poster (materai, pajak) dan kemiripan invoice. Keputusan akhir tetap di tangan operator.</p>
                </div>
                <ul class="divide-y divide-[var(--ui-line)]">
                    @if($categorySuggestion !== null)
                        <li class="px-5 py-3 text-sm">
                            <p class="font-bold text-[var(--ui-fg-strong)]">Saran kategori: {{ $categorySuggestion['label'] }}</p>
                            <p class="mt-0.5 text-[var(--ui-fg-muted)]">{{ $categorySuggestion['reason'] }}</p>
                        </li>
                    @endif
                    @foreach($helperHints as $hint)
                        <li class="px-5 py-3 text-sm">
                            <p class="font-bold text-[var(--ui-fg-strong)]">{{ $hint['label'] }}</p>
                            <p class="mt-0.5 text-[var(--ui-fg-muted)]">{{ $hint['message'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if($packageTimeline !== [])
            <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm print:hidden">
                <div class="border-b border-[var(--ui-line)] px-5 py-3">
                    <h2 class="font-bold text-[var(--ui-fg-strong)]">Riwayat paket</h2>
                    <p class="mt-0.5 text-xs leading-5 text-[var(--ui-fg-muted)]">Dari log operasional — siapa melakukan apa, tanpa istilah teknis.</p>
                </div>
                <ul class="divide-y divide-[var(--ui-line)]">
                    @foreach($packageTimeline as $event)
                        <li class="px-5 py-2.5 text-sm">
                            <p class="font-semibold text-[var(--ui-fg-strong)]">{{ $event['label'] }} <span class="font-normal text-xs text-[var(--ui-fg-muted)]">{{ $event['at'] }}</span></p>
                            <p class="mt-0.5 text-xs text-[var(--ui-fg-muted)]">{{ $event['description'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <div class="flex flex-col gap-3 border-b border-[var(--ui-line)] px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-bold text-[var(--ui-fg-strong)]">Bukti Dukung Eksternal ({{ $externalCheckedCount }}/{{ count($externalPatternItems) }})</h2>
                    <p class="mt-0.5 text-xs leading-5 text-[var(--ui-fg-muted)]">Checklist manual sesuai pola kegiatan — tidak memblokir penomoran. Dokumen yang dibuat aplikasi (A2) hanya informatif.</p>
                </div>
                <form method="GET" action="{{ route('spj.checklist', $package->id) }}" class="flex items-center gap-2 print:hidden">
                    <x-ui.field label="Pola kegiatan">
                        <x-ui.select name="pola" onchange="this.form.submit()">
                            @foreach($externalPatterns as $patternKey => $pattern)
                                <option value="{{ $patternKey }}" {{ $patternKey === $externalPatternKey ? 'selected' : '' }}>{{ $pattern['label'] }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                </form>
            </div>
            <ul class="divide-y divide-[var(--ui-line)]">
                @foreach($externalPatternItems as $itemKey)
                    @php
                        $item = $externalItems[$itemKey];
                        $isGenerated = $item['source'] === 'generated';
                        $isChecked = in_array($itemKey, $externalCheckedKeys, true);
                        $canToggleExternal = ! $isGenerated && $canEdit && $package->isEditable();
                    @endphp
                    <li class="flex flex-col gap-2 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 items-center gap-2.5">
                            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full text-xs font-black {{ $isGenerated || $isChecked ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500' }}">{{ $isGenerated || $isChecked ? '✓' : '○' }}</span>
                            <p class="min-w-0 text-sm font-semibold text-[var(--ui-fg-strong)]">{{ $item['label'] }}</p>
                            <x-ui.badge variant="neutral">{{ $isGenerated ? 'Aplikasi' : 'Manual' }}</x-ui.badge>
                        </div>
                        <span class="{{ $isGenerated ? 'shrink-0 text-xs text-[var(--ui-fg-muted)]' : 'hidden' }}">Ikut status A2 di atas</span>
                        <form method="POST" action="{{ route('spj.external-checklist.toggle', $package->id) }}" class="{{ $canToggleExternal ? 'shrink-0 print:hidden' : 'hidden' }}">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                            <input type="hidden" name="item_key" value="{{ $itemKey }}">
                            <input type="hidden" name="pola" value="{{ $externalPatternKey }}">
                            <x-ui.button variant="secondary" type="submit" class="text-xs">{{ $isChecked ? 'Batalkan tanda' : 'Tandai tersedia' }}</x-ui.button>
                        </form>
                        <span class="{{ ! $isGenerated && ! $canToggleExternal ? 'shrink-0 text-xs text-[var(--ui-fg-muted)]' : 'hidden' }}">Tidak dapat diubah pada status/peran saat ini</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <div class="border-b border-[var(--ui-line)] px-5 py-3">
                <h2 class="font-bold text-[var(--ui-fg-strong)]">Catatan operator ({{ $operatorNotes->count() }})</h2>
                <p class="mt-0.5 text-xs leading-5 text-[var(--ui-fg-muted)]">Handover shift dan pengingat internal — tidak tercetak ke dokumen SPJ dan tidak memblokir penomoran.</p>
            </div>
            <ul class="divide-y divide-[var(--ui-line)]">
                @forelse($operatorNotes as $note)
                    <li class="flex flex-col gap-2 px-5 py-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm text-[var(--ui-fg-strong)]">{{ $note->body }}</p>
                            <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">{{ $noteAuthorNames[$note->created_by] ?? 'Operator' }} · {{ $note->created_at?->translatedFormat('d M Y H:i') }}</p>
                        </div>
                        @if($canNote && (auth()->user()?->role === 'ADMIN' || (int) $note->created_by === (int) auth()->id()))
                            <form method="POST" action="{{ route('spj.operator-notes.destroy', $note->id) }}" class="shrink-0 print:hidden" onsubmit="return confirm('Hapus catatan ini?')">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <input type="hidden" name="_method" value="DELETE">
                                <x-ui.button variant="secondary" type="submit" class="text-xs">Hapus</x-ui.button>
                            </form>
                        @endif
                    </li>
                @empty
                    <li class="px-5 py-4 text-sm text-[var(--ui-fg-muted)]">Belum ada catatan untuk paket ini.</li>
                @endforelse
            </ul>
            @if($canNote)
                <form method="POST" action="{{ route('spj.operator-notes.store', $package->id) }}" class="border-t border-[var(--ui-line)] px-5 py-4 print:hidden">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <x-ui.field label="Tambah catatan (maks 500 karakter)" for="operator-note-body">
                        <x-ui.textarea id="operator-note-body" name="body" rows="2" maxlength="500" required placeholder="cth: nota asli menunggu stempel toko, lanjutkan besok pagi" />
                    </x-ui.field>
                    <div class="mt-2"><x-ui.button variant="secondary" type="submit" class="text-xs">Simpan catatan</x-ui.button></div>
                </form>
            @endif
        </section>

        <details class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <summary class="cursor-pointer px-5 py-3 text-sm font-bold text-[var(--ui-fg-strong)]">Sudah lengkap ({{ $doneCount }}) — klik untuk melihat</summary>
            <ul class="divide-y divide-[var(--ui-line)] border-t border-[var(--ui-line)]">
                @foreach($passedChecks as $check)
                    <li class="flex items-center gap-2.5 px-5 py-2 text-sm"><span class="font-black text-emerald-600">✓</span><span class="font-semibold text-[var(--ui-fg-strong)]">{{ $check['label'] }}</span><span class="text-xs text-[var(--ui-fg-muted)]">{{ $check['group'] }}</span></li>
                @endforeach
                @foreach($readyRequirements as $item)
                    <li class="flex items-center gap-2.5 px-5 py-2 text-sm"><span class="font-black text-emerald-600">✓</span><span class="font-semibold text-[var(--ui-fg-strong)]">{{ $item['label'] }}</span><span class="text-xs text-[var(--ui-fg-muted)]">{{ $item['group'] }}</span></li>
                @endforeach
            </ul>
        </details>

        <?php if ($optionalMissing->isNotEmpty() || $notApplicable->isNotEmpty()): ?>
            <details class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
                <summary class="cursor-pointer px-5 py-3 text-sm font-bold text-[var(--ui-fg-muted)]">Opsional / tidak berlaku ({{ $optionalMissing->count() + $notApplicable->count() }}) — tidak memblokir</summary>
                <ul class="divide-y divide-[var(--ui-line)] border-t border-[var(--ui-line)]">
                    @foreach($optionalMissing as $item)
                        <li class="px-5 py-2 text-sm"><span class="font-semibold text-[var(--ui-fg-muted)]">{{ $item['label'] }}</span> <x-ui.badge variant="neutral">Opsional</x-ui.badge><p class="mt-0.5 text-xs text-[var(--ui-fg-muted)]">{{ $item['message'] }}</p></li>
                    @endforeach
                    @foreach($notApplicable as $item)
                        <li class="px-5 py-2 text-sm"><span class="font-semibold text-[var(--ui-fg-muted)]">{{ $item['label'] }}</span> <x-ui.badge variant="neutral">Tidak berlaku</x-ui.badge></li>
                    @endforeach
                </ul>
            </details>
        <?php endif; ?>
    </div>
</x-layouts.tailwind-app>
