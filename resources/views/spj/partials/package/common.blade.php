<section class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3" data-vendor-recommendation data-vendor-recommend-url="{{ route('transactions.vendor-recommendation') }}" data-vendor-transaction-id="{{ (int) $transaction->id }}">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-strong)]">Data Umum Dokumen</h3>
        <span class="text-[11px] font-medium text-[var(--ui-fg-muted)]">Field bertanda * wajib diisi sebelum
            penomoran</span>
    </div>

    @if ($transaction->is_siplah)
        @php($siplahResponse = data_get($transaction->siplah_metadata, 'siplahResponse', []))
        <div
            class="mt-2 grid gap-2 rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-muted)] px-3 py-2 text-xs sm:grid-cols-2 lg:grid-cols-4">
            <div><span class="block text-[var(--ui-fg-muted)]">Marketplace</span><strong
                    class="text-[var(--ui-fg-strong)]">{{ data_get($siplahResponse, 'marketplace_displayname') ?: 'SiPLah' }}</strong>
            </div>
            <div><span class="block text-[var(--ui-fg-muted)]">ID transaksi</span><strong
                    class="break-all font-mono text-[var(--ui-fg-strong)]">{{ data_get($siplahResponse, 'transaction_id') ?: $transaction->siplah_transaction_id ?: '—' }}</strong>
            </div>
            <div><span class="block text-[var(--ui-fg-muted)]">Alamat penyedia</span><strong
                    class="text-[var(--ui-fg-strong)]">{{ data_get($siplahResponse, 'merchant_address') ?: '—' }}</strong>
            </div>
            <div><span class="block text-[var(--ui-fg-muted)]">Tanggal pembayaran</span><strong
                    class="text-[var(--ui-fg-strong)]">{{ $transaction->siplah_payment_date?->translatedFormat('d F Y H:i') ?: '—' }}</strong>
            </div>
        </div>
    @endif

    <div class="mt-2 grid gap-3 lg:grid-cols-2 lg:items-start">
        <div class="min-w-0">
            @php($fieldPrefix = 'spj-common-'.$transaction->id)
            <label for="{{ $fieldPrefix }}-payment-description" class="text-xs font-semibold text-[var(--ui-fg-strong)]">Uraian pembayaran <span
                    class="text-rose-600">*</span></label>
            @php($siplahInvoice = data_get($transaction->siplah_metadata, 'siplahResponse.invoice_number'))
            @php($paymentDescriptionDefault = $transaction->is_siplah ? app(\App\Services\SpjDescriptionService::class)->siplahPaymentDescription($transaction) : null)
            <x-ui.textarea id="{{ $fieldPrefix }}-payment-description" name="payment_description" rows="5" class="mt-1 !min-h-[8.75rem] !py-1.5 !text-sm"
                required>{{ old('payment_description', $transaction->payment_description ?: $paymentDescriptionDefault) }}</x-ui.textarea>
        </div>

        @php($effectiveCategory = strtoupper((string) old('spj_category', $transaction->spj_category ?: $selectedSpjType ?? '')))
        @php($currentMethod = old('payment_method', $transaction->payment_method ?: ($transaction->is_siplah ? 'siplah' : 'tunai')))
        @php($referenceDefault = match ($currentMethod) {
            'siplah' => 'VA Sumut - ',
            'transfer_bank' => 'ACC Sumut',
            default => '-',
        })
        <div class="grid min-w-0 gap-2 sm:grid-cols-2" x-data="{
            method: @js($currentMethod),
            suggestions: { siplah: 'VA Sumut - ', tunai: '-', transfer_bank: 'ACC Sumut' },
            suggest() {
                const input = this.$refs.paymentReference;
                if (!(input instanceof HTMLInputElement)) return;
                const current = input.value.trim();
                const known = Object.values(this.suggestions).map((suggestion) => suggestion.trim());
                if (current === '' || known.includes(current)) {
                    input.value = this.suggestions[this.method] ?? '';
                }
            },
        }">
            <div>
                <label for="{{ $fieldPrefix }}-payment-method" class="text-xs font-semibold text-[var(--ui-fg-strong)]">Metode pembayaran <span
                        class="text-rose-600">*</span></label>
                <x-ui.select id="{{ $fieldPrefix }}-payment-method" name="payment_method" class="mt-1 !py-1.5 !text-sm" required x-on:change="method = $event.target.value; suggest()">
                    @foreach (['tunai' => 'Tunai', 'transfer_bank' => 'Transfer Bank'] as $value => $label)
                        <option value="{{ $value }}" @selected($currentMethod === $value)>{{ $label }}</option>
                    @endforeach
                    @if ($effectiveCategory === 'BARANG' || $currentMethod === 'siplah')
                        <option value="siplah" @selected($currentMethod === 'siplah')>SiPLah</option>
                    @endif
                </x-ui.select>
            </div>
            <div>
                <label for="{{ $fieldPrefix }}-payment-reference" class="text-xs font-semibold text-[var(--ui-fg-strong)]">Referensi pembayaran</label>
                @php($siplahOrder = $transaction->siplah_order_number ?: (filled($siplahInvoice) ? collect(explode('/', $siplahInvoice))->filter()->last() : null))
                <x-ui.input id="{{ $fieldPrefix }}-payment-reference" name="payment_reference" x-ref="paymentReference" :value="old('payment_reference', $transaction->payment_reference ?: $referenceDefault)" class="mt-1 !py-1.5 !text-sm" />
            </div>
            <div>
                <label for="{{ $fieldPrefix }}-vendor-name" class="text-xs font-semibold text-[var(--ui-fg-strong)]">Penyedia / Merchant / Toko</label>
                <x-ui.input id="{{ $fieldPrefix }}-vendor-name" name="vendor_name" data-vendor-name-input :value="old('vendor_name', $transaction->vendor_name)" class="mt-1 !py-1.5 !text-sm" />
            </div>
            <div>
                <label for="{{ $fieldPrefix }}-vendor-owner" class="text-xs font-semibold text-[var(--ui-fg-strong)]">Pemilik Merchant / Toko /
                    Direktur</label>
                <x-ui.input id="{{ $fieldPrefix }}-vendor-owner" name="vendor_owner" data-vendor-owner-input :value="old('vendor_owner', $transaction->vendor_owner)" class="mt-1 !py-1.5 !text-sm" />
                <p data-vendor-owner-hint class="mt-1 hidden text-[11px] text-[var(--ui-fg-muted)]"></p>
            </div>
            <div>
                <label for="{{ $fieldPrefix }}-vendor-npwp" class="text-xs font-semibold text-[var(--ui-fg-strong)]">NPWP penyedia</label>
                <x-ui.input id="{{ $fieldPrefix }}-vendor-npwp" name="vendor_npwp" :value="old('vendor_npwp', $transaction->vendor_npwp)" class="mt-1 !py-1.5 !text-sm" />
            </div>
            <div>
                <label for="{{ $fieldPrefix }}-receipt-recipient" class="text-xs font-semibold text-[var(--ui-fg-strong)]">Penerima Utama / Kuitansi /
                    Penandatangan <span class="text-rose-600">*</span></label>
                <x-ui.input id="{{ $fieldPrefix }}-receipt-recipient" name="receipt_recipient_name" data-vendor-recipient-input :value="old('receipt_recipient_name', $transaction->receipt_recipient_name)" class="mt-1 !py-1.5 !text-sm" required />
                <p data-vendor-recipient-hint class="mt-1 hidden text-[11px] text-[var(--ui-fg-muted)]"></p>
            </div>

        </div>
    </div>
</section>
