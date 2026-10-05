<div data-spj-refresh="numbering">
    <h2 class="text-base font-bold text-[var(--ui-fg-strong)]">Penomoran Dokumen</h2>
    <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Nomor mengikuti format aktif dan tanggal peristiwa. Nomor yang sudah
        diterbitkan tidak akan ditimpa.</p>
    <div class="mt-3 grid gap-3 sm:grid-cols-3">
        <div class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
            <p class="text-[11px] font-bold uppercase text-[var(--ui-fg-muted)]">Kategori</p>
            <p class="mt-1 font-bold text-[var(--ui-fg-strong)]">{{ $spjTypeLabel($packageCategory) }}</p>
        </div>
        <div class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
            <p class="text-[11px] font-bold uppercase text-[var(--ui-fg-muted)]">Tanggal sumber</p>
            <p class="mt-1 font-bold text-[var(--ui-fg-strong)]">{{ $transaction->transaction_date?->translatedFormat('d F Y') }}</p>
        </div>
        <div class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
            <p class="text-[11px] font-bold uppercase text-[var(--ui-fg-muted)]">Cakupan nomor</p>
            <p class="mt-1 font-bold text-indigo-700">
                {{ $isHonorPackage ? 'SPJ utama · dipakai pada kuitansi honor' : ($isGoodsPackage ? 'SPJ, Pesanan, BAP, BAST' : 'SPJ dan dokumen kategori') }}
            </p>
        </div>
    </div>
    @if ($hasActiveSpjNumber)
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-600">Sudah ditetapkan</p>
            <p class="mt-1 font-mono text-base font-bold text-emerald-800 break-all">
                {{ $activeSpjDocument->document_number }}</p>
        </div>
    @elseif($package->status === 'CANCELLED')
        <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4">
            <p class="text-[11px] font-bold uppercase tracking-wide text-rose-600">Nomor dibatalkan</p>
            <p class="mt-1 break-all font-mono text-base font-bold text-rose-800 line-through">
                {{ $cancelledSpjDocument?->document_number ?: $package->document_number }}</p>
            @if ($cancelledSpjDocument?->cancellation_reason)
                <p class="mt-2 text-xs text-rose-700">Alasan: {{ $cancelledSpjDocument->cancellation_reason }}</p>
            @endif
            <form class="mt-3" method="POST" action="{{ route('spj.assign-number', $package->id) }}"
                data-confirm="Terbitkan ulang nomor SPJ tanpa mengubah data paket? Sistem akan menggunakan slot nomor tersedia sesuai urutan aktif.">
                @csrf
                <button
                    class="rounded-md bg-violet-600 px-4 py-2 text-sm font-bold text-white shadow hover:bg-violet-700">Terbitkan
                    ulang nomor</button>
                <p class="mt-1 text-xs text-rose-700">Gunakan ini jika isian sudah benar. Untuk koreksi data, buka paket
                    terlebih dahulu.</p>
            </form>
        </div>
    @else
        <div
            class="mt-4 rounded-lg border border-dashed border-[var(--ui-line-strong)] bg-[var(--ui-surface-soft)] p-6 text-center">
            <p class="text-base font-medium text-[var(--ui-fg)]">Belum bernomor</p>
            <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Periksa kembali data sumber dan Data Umum sebelum menerbitkan nomor.
            </p>
            @include('spj.partials.package.numbering-preflight-modal')
            <p class="mt-2 text-xs text-[var(--ui-fg-muted)]">Nomor SPJ mengikuti tanggal transaksi dan urutan BKU. Nomor pesanan,
                BAP, dan BAST diterbitkan terpisah menurut tanggal dokumennya.</p>
        </div>
    @endif
    @if ($package->documents->isNotEmpty())
        <div class="mt-4 divide-y divide-[var(--ui-line)] rounded-lg border border-[var(--ui-line)]">
            @foreach ($package->documents as $document)
                <div class="p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div><b>{{ $document->document_type }}</b>
                            <p class="font-mono text-xs text-[var(--ui-fg)]">{{ $document->document_number }}</p>
                        </div><span
                            class="rounded-full bg-[var(--ui-surface-muted)] px-2 py-1 text-xs font-bold">{{ $document->status }}
                            @if ($document->is_late_entry)
                                · SUSULAN
                            @endif
                        </span>
                    </div>
                    @if (in_array($document->status, ['NUMBERED', 'FINAL']))
                        <div class="mt-2 flex flex-wrap gap-2">
                            @if ($document->status === 'NUMBERED')
                                <form method="POST" action="{{ route('spj.documents.finalize', $document->id) }}">
                                    @csrf<button
                                        class="rounded bg-emerald-600 px-2 py-1 text-xs font-bold text-white">Finalkan</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('spj.documents.replace', $document->id) }}"
                                class="flex gap-1"
                                data-confirm="Nomor lama {{ $document->document_number }} akan dibatalkan permanen dan sistem menerbitkan nomor baru. Lanjutkan?">
                                @csrf<input name="reason" required minlength="5" placeholder="Alasan penomoran ulang"
                                    class="rounded border-[var(--ui-line-strong)] text-xs"><button
                                    class="rounded bg-amber-600 px-2 py-1 text-xs font-bold text-white hover:bg-amber-700">Nomori
                                    ulang</button></form>
                            @if (auth()->user()->isAdministrator())
                                <form method="POST" action="{{ route('spj.documents.cancel', $document->id) }}"
                                    class="flex gap-1"
                                    data-confirm="Batalkan nomor {{ $document->document_number }}? Slot nomor dapat dipakai kembali sesuai aturan urutan.">
                                    @csrf<input name="reason" required minlength="5" placeholder="Alasan pembatalan"
                                        class="rounded border-rose-300 text-xs"><button
                                        class="rounded bg-rose-600 px-2 py-1 text-xs font-bold text-white hover:bg-rose-700">Batalkan
                                        nomor</button></form>
                            @endif
                        </div>
                    @elseif($document->status === 'CANCELLED')
                        <div class="mt-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800">
                            <b>Nomor dibatalkan.</b> {{ $document->cancellation_reason }}</div>
                        @if ($document->document_type === 'SPJ' && $document->scope_key === 'MAIN' && $package->status === 'DRAFT')
                            <form method="POST" action="{{ route('spj.assign-number', $package->id) }}" class="mt-2"
                                data-confirm="Terbitkan nomor SPJ baru sebagai pengganti nomor yang dibatalkan?">
                                @csrf
                                <button
                                    class="rounded bg-violet-600 px-3 py-2 text-xs font-bold text-white shadow-sm hover:bg-violet-700">Terbitkan
                                    nomor pengganti</button>
                                <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Nomor lama tetap tersimpan dalam riwayat dan
                                    tidak digunakan kembali.</p>
                            </form>
                        @endif
                    @endif
                </div>
            @endforeach
        </div>
    @endif
    @unless ($isHonorPackage)<fieldset @disabled(!$package->isEditable())
            class="mt-5 disabled:cursor-not-allowed disabled:opacity-60">
            <div class="grid gap-4 {{ $isGoodsPackage ? 'lg:grid-cols-2' : '' }}">
                @if ($isGoodsPackage)
                    <section class="rounded-lg border border-[var(--ui-line)] p-4">
                        <h3 class="font-bold text-[var(--ui-fg-strong)]">Pembayaran bertahap</h3>
                        <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Total bruto tidak boleh melampaui nilai transaksi.</p>
                        <div class="mt-3 space-y-2">
                            @forelse($transaction->payments as $payment)
                                <div class="flex justify-between text-sm">
                                    <span>{{ $payment->payment_date?->translatedFormat('d F Y') }} ·
                                        {{ $payment->scope_key }} @if ($payment->is_late_entry)
                                            <b class="text-amber-700">Susulan</b>
                                        @endif
                                    </span>
                                    <b>{{ $rupiah($payment->gross_amount) }}</b>
                            </div>@empty<p class="text-xs text-[var(--ui-fg-muted)]">Belum ada pembayaran.</p>
                            @endforelse
                        </div>
                        <form method="POST" action="{{ route('spj.payments.store', $transaction->id) }}"
                            class="mt-3 grid gap-2 sm:grid-cols-2">@csrf
                            <input type="date" name="payment_date" max="{{ $transactionDateLimit }}" required
                                class="rounded-md border-[var(--ui-line-strong)] text-sm"><input type="number"
                                name="gross_amount" min="1" required placeholder="Nilai bruto"
                                class="rounded-md border-[var(--ui-line-strong)] text-sm">
                            <input type="number" name="tax_amount" min="0" value="0" placeholder="Pajak"
                                class="rounded-md border-[var(--ui-line-strong)] text-sm"><input name="payment_reference"
                                placeholder="Referensi pembayaran"
                                class="rounded-md border-[var(--ui-line-strong)] text-sm">
                            <button
                                class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-bold text-white sm:col-span-2">Tambah
                                pembayaran</button>
                        </form>
                    </section>
                    <section class="rounded-lg border border-[var(--ui-line)] p-4">
                        <h3 class="font-bold text-[var(--ui-fg-strong)]">Penerimaan barang bertahap</h3>
                        <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Jumlah kumulatif tidak boleh melebihi jumlah pesanan.</p>
                        <div class="mt-3 space-y-2">
                            @forelse($transaction->goodsReceipts as $receipt)
                                <div class="flex items-center justify-between gap-2 text-sm">
                                    <p><b>{{ $receipt->scope_key }}</b> ·
                                        {{ $receipt->receipt_date?->translatedFormat('d F Y') }} @if ($receipt->is_late_entry)
                                            <b class="text-amber-700">Susulan</b>
                                            @endif@if (filled($receipt->order_date) || filled($receipt->bap_date) || filled($receipt->bast_date))
                                                <span class="text-[var(--ui-fg-muted)]"> · Pesanan:
                                                    {{ $receipt->order_date?->translatedFormat('d/m/Y') ?: '—' }} · BAP:
                                                    {{ $receipt->bap_date?->translatedFormat('d/m/Y') ?: '—' }} · BAST:
                                                    {{ $receipt->bast_date?->translatedFormat('d/m/Y') ?: '—' }}</span>
                                            @endif
                                    </p>
                                    @if ($package->isEditable())
                                        <form method="POST"
                                            action="{{ route('spj.receipts.destroy', [$transaction->id, $receipt->id]) }}"
                                            data-confirm="Hapus tahap penerimaan {{ $receipt->scope_key }} ({{ $receipt->receipt_date?->translatedFormat('d F Y') }})? Rincian barang tahap ini ikut terhapus; sequence tahap lain tidak berubah.">
                                            @csrf @method('DELETE')<button title="Hapus tahap {{ $receipt->scope_key }}"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded text-rose-700 hover:bg-rose-50">×</button>
                                        </form>
                                    @endif
                                </div>
                            @empty<p class="text-xs text-[var(--ui-fg-muted)]">Belum ada penerimaan bertahap.</p>
                            @endforelse
                        </div>
                        @php($receivableItems = app(\App\Services\TransactionSettlementService::class)->receivableItems($transaction))
                        @if ($receivableItems === [])
                            <p class="mt-3 text-xs text-[var(--ui-fg-muted)]">Semua barang sudah diterima penuh — tidak ada sisa untuk
                                tahap baru.</p>
                        @else
                            <form method="POST" action="{{ route('spj.receipts.store', $transaction->id) }}"
                                data-receipt-form class="mt-3 space-y-3">@csrf
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <x-ui.field label="Barang" for="receipt-item" required
                                        hint="Hanya barang yang masih bersisa.">
                                        <select id="receipt-item" name="items[0][transaction_item_id]"
                                            data-receipt-item required
                                            class="h-10 w-full rounded-md border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-3 text-sm text-[var(--ui-fg)]">
                                            <option value="">Pilih barang</option>
                                            @foreach ($receivableItems as $receivable)
                                                <option value="{{ $receivable['id'] }}"
                                                    data-remaining="{{ $receivable['remaining'] }}"
                                                    data-unit="{{ $receivable['unit'] }}"
                                                    data-unit-price="{{ $receivable['unit_price'] }}">{{ $receivable['label'] }}
                                                    — sisa
                                                    {{ rtrim(rtrim(number_format($receivable['remaining'], 4, ',', '.'), '0'), ',') }}{{ $receivable['unit'] ? ' ' . $receivable['unit'] : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </x-ui.field>
                                    <x-ui.field label="Tanggal terima fisik" for="receipt-date"
                                        hint="Kosongkan untuk ikut Tgl BAP tahap.">
                                        <input id="receipt-date" type="date" name="receipt_date"
                                            max="{{ $transactionDateLimit }}"
                                            class="h-10 w-full rounded-md border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-3 text-sm text-[var(--ui-fg)]">
                                    </x-ui.field>
                                    <x-ui.field label="Jumlah diterima" for="receipt-qty" required>
                                        <input id="receipt-qty" type="number" step="1" min="1"
                                            name="items[0][quantity_received]" data-receipt-qty required
                                            placeholder="Contoh: 2"
                                            class="h-10 w-full rounded-md border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-3 text-sm text-[var(--ui-fg)]">
                                        <p data-receipt-hint class="text-xs text-[var(--ui-fg-muted)]"></p>
                                    </x-ui.field>
                                    <x-ui.field label="Nilai penerimaan"
                                        hint="Otomatis dari harga satuan sumber.">
                                        <input type="text" inputmode="numeric" data-receipt-amount-display
                                            readonly tabindex="-1" placeholder="Terisi otomatis"
                                            title="Nilai terhitung otomatis dari harga satuan sumber"
                                            class="h-10 w-full rounded-md border-[var(--ui-line-strong)] bg-[var(--ui-surface-soft)] px-3 text-right font-mono text-sm font-semibold text-[var(--ui-fg)]">
                                        <input type="hidden" name="items[0][amount_received]"
                                            data-receipt-amount>
                                    </x-ui.field>
                                </div>
                                <fieldset class="rounded-lg border border-[var(--ui-line)] p-3">
                                    <legend class="px-1 text-xs font-bold text-[var(--ui-fg)]">Tanggal surat tahap
                                    </legend>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <x-ui.field label="Tgl pesanan tahap" for="receipt-order-date">
                                            <input id="receipt-order-date" type="date" name="order_date"
                                                max="{{ $transactionDateLimit }}"
                                                class="h-10 w-full rounded-md border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-3 text-sm text-[var(--ui-fg)]">
                                        </x-ui.field>
                                        <x-ui.field label="Tgl BAP tahap" for="receipt-bap-date">
                                            <input id="receipt-bap-date" type="date" name="bap_date"
                                                max="{{ $transactionDateLimit }}"
                                                class="h-10 w-full rounded-md border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-3 text-sm text-[var(--ui-fg)]">
                                        </x-ui.field>
                                        <x-ui.field label="Tgl BAST tahap" for="receipt-bast-date">
                                            <input id="receipt-bast-date" type="date" name="bast_date"
                                                max="{{ $transactionDateLimit }}"
                                                class="h-10 w-full rounded-md border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-3 text-sm text-[var(--ui-fg)]">
                                        </x-ui.field>
                                        <x-ui.field label="Tgl invoice tahap" for="receipt-invoice-date">
                                            <input id="receipt-invoice-date" type="date" name="invoice_date"
                                                max="{{ $transactionDateLimit }}"
                                                class="h-10 w-full rounded-md border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-3 text-sm text-[var(--ui-fg)]">
                                        </x-ui.field>
                                    </div>
                                </fieldset>
                                <button
                                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700"><span
                                        aria-hidden="true" class="text-base font-black">+</span>Tambah
                                    penerimaan</button>
                            </form>
                        @endif
                        @if ($transaction->goodsReceipts->where('status', '!==', 'CANCELLED')->count() > 1)
                            <div class="mt-3 rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
                                <p class="text-xs font-bold text-[var(--ui-fg)]">Nomor surat per tahap</p>
                                <p class="mt-0.5 text-[11px] text-[var(--ui-fg-muted)]">Kuitansi tetap satu (scope MAIN). Pesanan,
                                    BAP, dan BAST diterbitkan per tahap penerimaan.</p>
                                <div class="mt-2 space-y-2">
                                    @foreach ($transaction->goodsReceipts->where('status', '!==', 'CANCELLED')->sortBy('receipt_sequence') as $receipt)
                                        @php($tahapScope = 'TAHAP:' . $receipt->receipt_sequence)
                                        <div
                                            class="rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-2">
                                            <p class="text-xs font-bold text-[var(--ui-fg)]">Tahap
                                                {{ $receipt->receipt_sequence }} ·
                                                {{ $receipt->receipt_date?->translatedFormat('d F Y') }}</p>
                                            <div class="mt-1 grid gap-2 sm:grid-cols-3">
                                                @foreach (['PESANAN' => $receipt->order_date, 'BAP' => $receipt->bap_date, 'BAST' => $receipt->bast_date] as $docType => $letterDate)
                                                    @php($tahapDoc = $package->documents->first(fn($doc) => $doc->document_type === $docType && $doc->scope_key === $tahapScope && $doc->status !== 'CANCELLED'))
                                                    <div class="text-xs">
                                                        <p class="font-bold text-[var(--ui-fg)]">{{ $docType }}</p>
                                                        @if ($tahapDoc?->document_number)
                                                            <p class="mt-0.5 font-mono font-bold text-emerald-700">
                                                                {{ $tahapDoc->document_number }}</p>
                                                        @elseif($package->isEditable())
                                                            <form method="POST"
                                                                action="{{ route('spj.documents.assign-number', [$package->id, $docType]) }}"
                                                                class="mt-1 flex gap-1">@csrf
                                                                <input type="hidden" name="scope_key"
                                                                    value="{{ $tahapScope }}">
                                                                <input type="date" name="document_date"
                                                                    value="{{ $letterDate?->format('Y-m-d') ?: now()->format('Y-m-d') }}"
                                                                    required
                                                                    class="w-full rounded border-[var(--ui-line-strong)] px-1 py-1 text-xs">
                                                                <button
                                                                    class="shrink-0 rounded bg-indigo-600 px-2 py-1 text-xs font-bold text-white">Nomori</button>
                                                            </form>
                                                        @else
                                                            <p class="mt-0.5 text-[var(--ui-fg-muted)]">Belum bernomor</p>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </section>
                @endif
            </div>
    </fieldset>@endunless
</div>
