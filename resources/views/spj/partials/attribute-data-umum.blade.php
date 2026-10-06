@php
    $transaction = $transaction ?? $package->transaction;
@endphp
<div class="space-y-1.5 text-sm">
    <div class="font-medium text-[var(--ui-fg)]">Uraian Pembayaran</div>
    <div class="text-[var(--ui-fg-muted)] truncate">
        @if($transaction->payment_description)
            {{ $transaction->payment_description }}
        @else
            <span class="text-rose-500">Belum diisi</span>
        @endif
    </div>

    <div class="mt-2 font-medium text-[var(--ui-fg)]">Metode Pembayaran</div>
    <div class="text-[var(--ui-fg-muted)]">
        @if($transaction->payment_method)
            {{ ucfirst(strtolower($transaction->payment_method)) }}
        @else
            <span class="text-rose-500">Belum diisi</span>
        @endif
    </div>

    <div class="mt-2 font-medium text-[var(--ui-fg)]">Referensi Pembayaran</div>
    <div class="text-[var(--ui-fg-muted)] truncate">
        @if($transaction->payment_reference)
            {{ $transaction->payment_reference }}
        @else
            <span class="text-rose-500">Belum diisi</span>
        @endif
    </div>

    @if($transaction->is_siplah || strtolower((string) $transaction->payment_method) === 'siplah')
        <div class="mt-2 font-medium text-[var(--ui-fg)]">Penyedia / Vendor</div>
        <div class="text-[var(--ui-fg-muted)] truncate">
            @if($transaction->vendor_name)
                {{ $transaction->vendor_name }}
            @else
                <span class="text-rose-500">Belum diisi</span>
            @endif
        </div>

        <div class="mt-2 font-medium text-[var(--ui-fg)]">NPWP Penyedia</div>
        <div class="text-[var(--ui-fg-muted)]">
            @if($transaction->vendor_npwp)
                {{ $transaction->vendor_npwp }}
            @else
                <span class="text-rose-500">Belum diisi</span>
            @endif
        </div>

        <div class="mt-2 font-medium text-[var(--ui-fg)]">Nomor Invoice</div>
        <div class="text-[var(--ui-fg-muted)]">
            @if($transaction->invoice_number)
                {{ $transaction->invoice_number }}
            @else
                <span class="text-rose-500">Belum diisi</span>
            @endif
        </div>

        <div class="mt-2 font-medium text-[var(--ui-fg)]">Tanggal Invoice</div>
        <div class="text-[var(--ui-fg-muted)]">
            @if($transaction->invoice_date)
                {{ $transaction->invoice_date->translatedFormat('d F Y') }}
            @else
                <span class="text-rose-500">Belum diisi</span>
            @endif
        </div>

        <div class="mt-2 font-medium text-[var(--ui-fg)]">Status Invoice</div>
        <div class="text-[var(--ui-fg-muted)]">
            @if($transaction->invoice_status)
                {{ $transaction->invoice_status }}
            @else
                <span class="text-rose-500">Belum diisi</span>
            @endif
        </div>

        <div class="mt-2 font-medium text-[var(--ui-fg)]">Nomor Pesanan SiPLah</div>
        <div class="text-[var(--ui-fg-muted)]">
            @if($transaction->siplah_order_number)
                {{ $transaction->siplah_order_number }}
            @else
                <span class="text-rose-500">Belum diisi</span>
            @endif
        </div>
    @else
        <div class="mt-2 font-medium text-[var(--ui-fg)]">Penerima Utama (Kuitansi)</div>
        <div class="text-[var(--ui-fg-muted)] truncate">
            @if($transaction->receipt_recipient_name)
                {{ $transaction->receipt_recipient_name }}
            @else
                <span class="text-rose-500">Belum diisi</span>
            @endif
        </div>

        <div class="mt-2 font-medium text-[var(--ui-fg)]">Pemilik / Vendor</div>
        <div class="text-[var(--ui-fg-muted)] truncate">
            @if($transaction->vendor_owner)
                {{ $transaction->vendor_owner }}
            @else
                <span class="text-rose-500">Belum diisi</span>
            @endif
        </div>

        <div class="mt-2 font-medium text-[var(--ui-fg)]">NPWP</div>
        <div class="text-[var(--ui-fg-muted)]">
            @if($transaction->vendor_npwp)
                {{ $transaction->vendor_npwp }}
            @else
                <span class="text-rose-500">Belum diisi</span>
            @endif
        </div>
    @endif
</div>