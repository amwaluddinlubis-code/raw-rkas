@php
    $category = strtoupper((string) $transaction->spj_category);
@endphp
<div class="mt-3 space-y-2 text-xs" style="color: var(--ui-fg-muted)">
    {{-- Data Umum Dokumen --}}
    <div class="font-semibold" style="color: var(--ui-fg)">Data Umum Dokumen</div>
    <div class="grid gap-1 text-[11px]">
        <div><span class="font-medium">Uraian:</span> {{ $transaction->payment_description ?: 'Belum diisi' }}</div>
        <div><span class="font-medium">Metode:</span> {{ $transaction->payment_method ? ucfirst(strtolower($transaction->payment_method)) : 'Belum diisi' }}</div>
        <div><span class="font-medium">Referensi:</span> {{ $transaction->payment_reference ?: 'Belum diisi' }}</div>
        @if($transaction->is_siplah || strtolower((string) $transaction->payment_method) === 'siplah')
            <div><span class="font-medium">Penyedia:</span> {{ $transaction->vendor_name ?: 'Belum diisi' }}</div>
            <div><span class="font-medium">NPWP:</span> {{ $transaction->vendor_npwp ?: 'Belum diisi' }}</div>
            <div><span class="font-medium">Invoice:</span> {{ $transaction->invoice_number ?: 'Belum diisi' }}</div>
            <div><span class="font-medium">Tgl Invoice:</span> {{ $transaction->invoice_date?->translatedFormat('d F Y') ?: 'Belum diisi' }}</div>
        @else
            <div><span class="font-medium">Penerima Utama:</span> {{ $transaction->receipt_recipient_name ?: 'Belum diisi' }}</div>
            <div><span class="font-medium">Pemilik:</span> {{ $transaction->vendor_owner ?: 'Belum diisi' }}</div>
            <div><span class="font-medium">NPWP:</span> {{ $transaction->vendor_npwp ?: 'Belum diisi' }}</div>
        @endif

    </div>

    {{-- Data Pengadaan / Kategori --}}
    @php
        $categoryLabel = match ($category) {
            'BARANG' => 'Pengadaan',
            'KONSUMSI' => 'Konsumsi',
            'PEMELIHARAAN' => 'Pemeliharaan',
            'SPPD' => 'Perjalanan Dinas',
            'HONOR_PEGAWAI' => 'Honor',
            default => 'Jasa Lainnya',
        };
    @endphp
    <div class="mt-2 font-semibold" style="color: var(--ui-fg)">Data {{ $categoryLabel }}</div>
    <div class="grid gap-1 text-[11px]">
        @switch($category)
            @case('BARANG')
                @if($transaction->goods->first())
                    @php $goods = $transaction->goods->first(); @endphp
                    <div><span class="font-medium">No Pesanan:</span> {{ $goods->order_number ?: 'Belum diterbitkan' }}</div>
                    <div><span class="font-medium">Tgl Pesanan:</span> {{ $goods->order_date?->translatedFormat('d F Y') ?: 'Belum diisi' }}</div>
                    <div><span class="font-medium">No BAP:</span> {{ $goods->bap_number ?: 'Belum diterbitkan' }}</div>
                    <div><span class="font-medium">Tgl BAP:</span> {{ $goods->bap_date?->translatedFormat('d F Y') ?: 'Belum diisi' }}</div>
                    <div><span class="font-medium">No BAST:</span> {{ $goods->bast_number ?: 'Belum diterbitkan' }}</div>
                    <div><span class="font-medium">Tgl BAST:</span> {{ $goods->bast_date?->translatedFormat('d F Y') ?: 'Belum diisi' }}</div>
                    @if($transaction->is_siplah || strtolower((string) $transaction->payment_method) === 'siplah')
                        <div><span class="font-medium">No Pesanan SiPLah:</span> {{ $transaction->siplah_order_number ?: 'Belum diisi' }}</div>
                        <div><span class="font-medium">Status Invoice:</span> {{ $transaction->invoice_status ?: 'Belum diisi' }}</div>
                    @endif
                @else
                    <div>Belum ada data pengadaan</div>
                @endif
                @break

            @case('KONSUMSI')
                @php $participants = $transaction->items->flatMap(fn ($item) => $item->participants)->values(); @endphp
                <div><span class="font-medium">Jumlah Peserta:</span> {{ $participants->count() }}</div>
                <div><span class="font-medium">Total Porsi:</span> {{ $participants->sum('portions') }}</div>
                @if($participants->count())
                    <div><span class="font-medium">Penerima Utama:</span> {{ $participants->firstWhere('is_primary', true)?->name ?? $participants->first()?->name ?? 'Belum ditentukan' }}</div>
                @endif
                @break

            @case('PEMELIHARAAN')
                @if($transaction->workOrder)
                    <div><span class="font-medium">Jenis Biaya:</span> {{ $transaction->workOrder->expense_type ?: 'Belum diisi' }}</div>
                    <div><span class="font-medium">Uraian Pekerjaan:</span> {{ $transaction->workOrder->work_description ?: 'Belum diisi' }}</div>
                    <div><span class="font-medium">Lokasi:</span> {{ $transaction->workOrder->work_location ?: 'Belum diisi' }}</div>
                    <div><span class="font-medium">Mulai:</span> {{ $transaction->workOrder->work_started_at?->translatedFormat('d F Y') ?: 'Belum diisi' }}</div>
                    <div><span class="font-medium">Selesai:</span> {{ $transaction->workOrder->work_completed_at?->translatedFormat('d F Y') ?: 'Belum diisi' }}</div>
                    <div><span class="font-medium">No SPK:</span> {{ $transaction->workOrder->spk_number ?: 'Belum diterbitkan' }}</div>
                    <div><span class="font-medium">Tgl SPK:</span> {{ $transaction->workOrder->spk_date?->translatedFormat('d F Y') ?: 'Belum diisi' }}</div>
                    <div><span class="font-medium">No RAB:</span> {{ $transaction->workOrder->rab_number ?: 'Belum diterbitkan' }}</div>
                    <div><span class="font-medium">Tgl RAB:</span> {{ $transaction->workOrder->rab_date?->translatedFormat('d F Y') ?: 'Belum diisi' }}</div>
                    @php $workers = $transaction->workers; @endphp
                    @if($workers->count())
                        <div><span class="font-medium">Jumlah Pekerja:</span> {{ $workers->count() }}</div>
                        <div><span class="font-medium">Total Hari Kerja:</span> {{ $workers->sum('work_days') }}</div>
                    @endif
                @else
                    <div>Belum ada data pemeliharaan</div>
                @endif
                @break

            @case('SPPD')
                @php $travels = $transaction->travels; @endphp
                @if($travels->count())
                    @foreach($travels->take(3) as $travel)
                        <div><span class="font-medium">{{ $travel->traveler_name }}:</span> {{ $travel->destination }} ({{ $travel->departure_date?->translatedFormat('d F Y') }} - {{ $travel->return_date?->translatedFormat('d F Y') }})</div>
                    @endforeach
                    @if($travels->count() > 3)
                        <div class="text-[10px]">+ {{ $travels->count() - 3 }} pelaksana lain...</div>
                    @endif
                @else
                    <div>Belum ada data SPPD</div>
                @endif
                @break

            @case('HONOR_PEGAWAI')
                @php $honors = $transaction->honors; @endphp
                @if($honors->count())
                    @foreach($honors->take(3) as $honor)
                        <div><span class="font-medium">{{ $honor->name }}:</span> {{ $honor->position }} - {{ $rupiah($honor->gross_amount) }} ({{ $honor->honor_months }} bln)</div>
                    @endforeach
                    @if($honors->count() > 3)
                        <div class="text-[10px]">+ {{ $honors->count() - 3 }} penerima lain...</div>
                    @endif
                @else
                    <div>Belum ada data honor</div>
                @endif
                @break

            @case('JASA_LAINNYA')
                @php $recipients = $transaction->serviceRecipients; @endphp
                @if($recipients->count())
                    @foreach($recipients->take(3) as $recipient)
                        <div><span class="font-medium">{{ $recipient->name }}:</span> {{ $recipient->service_type }} - {{ $rupiah($recipient->amount) }}</div>
                    @endforeach
                    @if($recipients->count() > 3)
                        <div class="text-[10px]">+ {{ $recipients->count() - 3 }} penerima lain...</div>
                    @endif
                @else
                    <div>Belum ada data jasa lain</div>
                @endif
                @break

            @default
                <div>Kategori tidak dikenali</div>
        @endswitch
    </div>
</div>