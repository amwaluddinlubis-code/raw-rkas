<div>
    @php
        $transaction = $transaction;
        $spjDescriptionsDirty = false;
    @endphp
    <div class="flex flex-col gap-6" x-data="{ spjDescriptionsDirty: false }">
        <x-page-header title="Transaksi #{{ $transaction->id }}"
            :subtitle="'Sumber: ' . ($transaction->sourceValue('no_bukti') ?: 'Tanpa nomor bukti') . ' · ' . ($transaction->sourceValue('description') ?: 'Tanpa uraian')"
            kicker="Transaksi & SPJ">
        </x-page-header>
        @include('transactions.partials.detail.overview-header')
        @include('transactions.partials.detail.overview-status')
        @include('transactions.partials.detail.source-reconciliation')
        @include('transactions.partials.detail.items')
    </div>
</div>
