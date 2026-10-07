@php
    $transaction = $transaction ?? $package->transaction;
@endphp
<div class="space-y-1.5 text-sm">
    <div class="font-medium text-[var(--ui-fg)]">Uraian Pembayaran</div>
    <div class="text-[var(--ui-fg-muted)] truncate">
        {!! \App\Support\SpjDisplay::presenceHtml($transaction->payment_description) !!}
    </div>

    <div class="mt-2 font-medium text-[var(--ui-fg)]">Metode Pembayaran</div>
    <div class="text-[var(--ui-fg-muted)]">
        {!! \App\Support\SpjDisplay::presenceHtml($transaction->payment_method ? ucfirst(strtolower($transaction->payment_method)) : null) !!}
    </div>

    <div class="mt-2 font-medium text-[var(--ui-fg)]">Referensi Pembayaran</div>
    <div class="text-[var(--ui-fg-muted)] truncate">
        {!! \App\Support\SpjDisplay::presenceHtml($transaction->payment_reference) !!}
    </div>

    @if($transaction->is_siplah || strtolower((string) $transaction->payment_method) === 'siplah')
        <div class="mt-2 font-medium text-[var(--ui-fg)]">Penyedia / Vendor</div>
        <div class="text-[var(--ui-fg-muted)] truncate">
            {!! \App\Support\SpjDisplay::presenceHtml($transaction->vendor_name) !!}
        </div>

        <div class="mt-2 font-medium text-[var(--ui-fg)]">NPWP Penyedia</div>
        <div class="text-[var(--ui-fg-muted)]">
            {!! \App\Support\SpjDisplay::presenceHtml($transaction->vendor_npwp) !!}
        </div>

        <div class="mt-2 font-medium text-[var(--ui-fg)]">Nomor Invoice</div>
        <div class="text-[var(--ui-fg-muted)]">
            {!! \App\Support\SpjDisplay::presenceHtml($transaction->invoice_number) !!}
        </div>

        <div class="mt-2 font-medium text-[var(--ui-fg)]">Tanggal Invoice</div>
        <div class="text-[var(--ui-fg-muted)]">
            {!! \App\Support\SpjDisplay::presenceHtml($transaction->invoice_date?->translatedFormat('d F Y')) !!}
        </div>

        <div class="mt-2 font-medium text-[var(--ui-fg)]">Status Invoice</div>
        <div class="text-[var(--ui-fg-muted)]">
            {!! \App\Support\SpjDisplay::presenceHtml($transaction->invoice_status) !!}
        </div>

        <div class="mt-2 font-medium text-[var(--ui-fg)]">Nomor Pesanan SiPLah</div>
        <div class="text-[var(--ui-fg-muted)]">
            {!! \App\Support\SpjDisplay::presenceHtml($transaction->siplah_order_number) !!}
        </div>
    @else
        <div class="mt-2 font-medium text-[var(--ui-fg)]">Penerima Utama (Kuitansi)</div>
        <div class="text-[var(--ui-fg-muted)] truncate">
            {!! \App\Support\SpjDisplay::presenceHtml($transaction->receipt_recipient_name) !!}
        </div>

        <div class="mt-2 font-medium text-[var(--ui-fg)]">Pemilik / Vendor</div>
        <div class="text-[var(--ui-fg-muted)] truncate">
            {!! \App\Support\SpjDisplay::presenceHtml($transaction->vendor_owner) !!}
        </div>

        <div class="mt-2 font-medium text-[var(--ui-fg)]">NPWP</div>
        <div class="text-[var(--ui-fg-muted)]">
            {!! \App\Support\SpjDisplay::presenceHtml($transaction->vendor_npwp) !!}
        </div>
    @endif
</div>