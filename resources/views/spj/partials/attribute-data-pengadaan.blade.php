@php
    $transaction = $transaction ?? $package->transaction;
    $category = $category ?? strtoupper((string) $transaction->spj_category);
@endphp
<div class="space-y-1.5 text-sm">
    @switch($category)
        @case('BARANG')
            @if($transaction->goods->first())
                @php $goods = $transaction->goods->first(); @endphp
                <div class="font-medium text-[var(--ui-fg)]">Nomor Pesanan</div>
                <div class="font-mono text-[var(--theme-content-accent)]">
                    {!! \App\Support\SpjDisplay::presenceHtml($goods->order_number, 'Belum diterbitkan') !!}
                </div>

                <div class="mt-2 font-medium text-[var(--ui-fg)]">Tanggal Pesanan</div>
                <div class="text-[var(--ui-fg-muted)]">
                    {!! \App\Support\SpjDisplay::presenceHtml($goods->order_date?->translatedFormat('d F Y')) !!}
                </div>

                <div class="mt-2 font-medium text-[var(--ui-fg)]">Nomor BAP</div>
                <div class="font-mono text-[var(--theme-content-accent)]">
                    {!! \App\Support\SpjDisplay::presenceHtml($goods->bap_number, 'Belum diterbitkan') !!}
                </div>

                <div class="mt-2 font-medium text-[var(--ui-fg)]">Tanggal BAP</div>
                <div class="text-[var(--ui-fg-muted)]">
                    {!! \App\Support\SpjDisplay::presenceHtml($goods->bap_date?->translatedFormat('d F Y')) !!}
                </div>

                <div class="mt-2 font-medium text-[var(--ui-fg)]">Nomor BAST</div>
                <div class="font-mono text-[var(--theme-content-accent)]">
                    {!! \App\Support\SpjDisplay::presenceHtml($goods->bast_number, 'Belum diterbitkan') !!}
                </div>

                <div class="mt-2 font-medium text-[var(--ui-fg)]">Tanggal BAST</div>
                <div class="text-[var(--ui-fg-muted)]">
                    {!! \App\Support\SpjDisplay::presenceHtml($goods->bast_date?->translatedFormat('d F Y')) !!}
                </div>

                @if($transaction->is_siplah || strtolower((string) $transaction->payment_method) === 'siplah')
                    <div class="mt-2 font-medium text-[var(--ui-fg)]">Nomor Pesanan SiPLah</div>
                    <div class="font-mono text-[var(--theme-content-accent)]">
                        {!! \App\Support\SpjDisplay::presenceHtml($transaction->siplah_order_number) !!}
                    </div>

                    <div class="mt-2 font-medium text-[var(--ui-fg)]">Status Invoice</div>
                    <div class="text-[var(--ui-fg-muted)]">
                        {!! \App\Support\SpjDisplay::presenceHtml($transaction->invoice_status) !!}
                    </div>
                @endif
            @else
                <div class="text-[var(--ui-fg-muted)]">Belum ada data pengadaan barang</div>
            @endif
            @break

        @case('KONSUMSI')
            @php $participants = $transaction->items->flatMap(fn ($item) => $item->participants)->values(); @endphp
            <div class="font-medium text-[var(--ui-fg)]">Jumlah Peserta</div>
            <div class="text-[var(--ui-fg-strong)]">{{ $participants->count() }} orang</div>

            <div class="mt-2 font-medium text-[var(--ui-fg)]">Total Porsi</div>
            <div class="text-[var(--ui-fg-strong)]">{{ $participants->sum('portions') }} porsi</div>

            <div class="mt-2 font-medium text-[var(--ui-fg)]">Penerima Konsumsi (Utama)</div>
            <div class="text-[var(--ui-fg-muted)] truncate">
                {!! \App\Support\SpjDisplay::presenceHtml($participants->firstWhere('is_primary', true)?->name ?? $participants->first()?->name, 'Belum ditentukan') !!}
            </div>

            @if($participants->count())
                <div class="mt-2 font-medium text-[var(--ui-fg)]">Daftar Peserta</div>
                <div class="space-y-1 max-h-40 overflow-y-auto">
                    @foreach($participants as $participant)
                        <div class="flex items-center gap-2 text-[12px]">
                            <span class="font-medium">{{ $participant->name }}</span>
                            <span class="text-[var(--ui-fg-muted)]">({{ $participant->position ?? $participant->nip ?? '-' }})</span>
                            <span class="px-1.5 py-0.5 rounded text-[10px] bg-[var(--ui-surface-muted)]">{{ $participant->portions }} porsi</span>
                            @if($participant->is_primary)
                                <x-ui.badge variant="success" size="xs">Utama</x-ui.badge>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
            @break

        @case('PEMELIHARAAN')
            @if($transaction->workOrder)
            <div class="font-medium text-[var(--ui-fg)]">Jenis Biaya</div>
            <div class="text-[var(--ui-fg-muted)]">
                {!! \App\Support\SpjDisplay::presenceHtml($transaction->workOrder->expense_type) !!}
            </div>

            <div class="mt-2 font-medium text-[var(--ui-fg)]">Uraian Pekerjaan</div>
            <div class="text-[var(--ui-fg-muted)] truncate">
                {!! \App\Support\SpjDisplay::presenceHtml($transaction->workOrder->work_description) !!}
            </div>

            <div class="mt-2 font-medium text-[var(--ui-fg)]">Lokasi Pekerjaan</div>
            <div class="text-[var(--ui-fg-muted)] truncate">
                {!! \App\Support\SpjDisplay::presenceHtml($transaction->workOrder->work_location) !!}
            </div>

                <div class="mt-2 font-medium text-[var(--ui-fg)]">Periode Pekerjaan</div>
                <div class="text-[var(--ui-fg-muted)]">
                    @if($transaction->workOrder->work_started_at)
                        {{ $transaction->workOrder->work_started_at->translatedFormat('d F Y') }}
                    @else
                        <span class="text-rose-500">Belum diisi</span>
                    @endif
                    {{ $transaction->workOrder->work_completed_at ? ' s.d. ' . $transaction->workOrder->work_completed_at->translatedFormat('d F Y') : '' }}
                </div>

                <div class="mt-2 font-medium text-[var(--ui-fg)]">Nomor SPK</div>
                <div class="font-mono text-[var(--theme-content-accent)]">
                    {!! \App\Support\SpjDisplay::presenceHtml($transaction->workOrder->spk_number, 'Belum diterbitkan') !!}
                </div>

                <div class="mt-2 font-medium text-[var(--ui-fg)]">Tanggal SPK</div>
                <div class="text-[var(--ui-fg-muted)]">
                    {!! \App\Support\SpjDisplay::presenceHtml($transaction->workOrder->spk_date?->translatedFormat('d F Y')) !!}
                </div>

                <div class="mt-2 font-medium text-[var(--ui-fg)]">Nomor RAB</div>
                <div class="font-mono text-[var(--theme-content-accent)]">
                    {!! \App\Support\SpjDisplay::presenceHtml($transaction->workOrder->rab_number, 'Belum diterbitkan') !!}
                </div>

                <div class="mt-2 font-medium text-[var(--ui-fg)]">Tanggal RAB</div>
                <div class="text-[var(--ui-fg-muted)]">
                    {!! \App\Support\SpjDisplay::presenceHtml($transaction->workOrder->rab_date?->translatedFormat('d F Y')) !!}
                </div>

                @php $workers = $transaction->workers; @endphp
                @if($workers->count())
                    <div class="mt-2 font-medium text-[var(--ui-fg)]">Pekerja ({{ $workers->count() }})</div>
                    <div class="space-y-1 max-h-40 overflow-y-auto">
                        @foreach($workers as $worker)
                            <div class="flex items-center gap-2 text-[12px]">
                                <span class="font-medium">{{ $worker->name }}</span>
                                <span class="text-[var(--ui-fg-muted)]">({{ $worker->job_description ?? '-' }})</span>
                                <span class="px-1.5 py-0.5 rounded text-[10px] bg-[var(--ui-surface-muted)]">{{ $worker->work_days }} hari</span>
                                <span class="text-[var(--ui-fg-muted)]">{{ \App\Support\SpjDisplay::rupiah($worker->daily_rate) }}/hari</span>
                                @if($worker->is_receipt_recipient)
                                    <x-ui.badge variant="success" size="xs">Penerima</x-ui.badge>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="text-[var(--ui-fg-muted)]">Belum ada data pemeliharaan</div>
            @endif
            @break

        @case('SPPD')
            @php $travels = $transaction->travels; @endphp
            @if($travels->count())
                @foreach($travels as $travel)
                    <div class="border-l-2 border-[var(--theme-accent)] pl-3 py-1.5">
                        <div class="font-medium text-[var(--ui-fg)]">{{ $travel->traveler_name }}</div>
                        <div class="text-[12px] text-[var(--ui-fg-muted)]">
                            <span class="font-medium">Tujuan:</span> {{ $travel->destination }}<br>
                            <span class="font-medium">Maksud:</span> {{ $travel->purpose ?? '-' }}<br>
                            <span class="font-medium">Periode:</span> {{ $travel->departure_date?->translatedFormat('d F Y') }} s.d. {{ $travel->return_date?->translatedFormat('d F Y') }}<br>
                            <span class="font-medium">Transportasi:</span> {{ $travel->transport_mode ?? '-' }}<br>
                            <span class="font-medium">Nomor ST:</span>
                            {!! \App\Support\SpjDisplay::presenceHtml($travel->assignment_letter_number, 'Belum diterbitkan') !!}
                            <br>
                            <span class="font-medium">Tgl ST:</span>
                            {!! \App\Support\SpjDisplay::presenceHtml($travel->assignment_letter_date?->translatedFormat('d F Y')) !!}
                            <br>
                            <span class="font-medium">Nominal:</span> {{ $travel->amount ? \App\Support\SpjDisplay::rupiah($travel->amount) : '-' }}
                        </div>
                    </div>
                @endforeach
            @else
                <div class="text-[var(--ui-fg-muted)]">Belum ada data perjalanan dinas</div>
            @endif
            @break

        @case('HONOR_PEGAWAI')
            @php $honors = $transaction->honors; @endphp
            @if($honors->count())
                @foreach($honors as $honor)
                    <div class="border-l-2 border-[var(--theme-accent)] pl-3 py-1.5">
                        <div class="font-medium text-[var(--ui-fg)]">{{ $honor->name }}</div>
                        <div class="text-[12px] text-[var(--ui-fg-muted)]">
                            <span class="font-medium">Jabatan:</span> {{ $honor->position ?? '-' }}<br>
                            <span class="font-medium">Golongan:</span> {{ $honor->golongan ?? '-' }}<br>
                            <span class="font-medium">NIP/NIK:</span> {{ $honor->nip ?? $honor->nik ?? '-' }}<br>
                            <span class="font-medium">NPWP:</span> {{ $honor->npwp ?? '-' }}<br>
                            <span class="font-medium">Periode:</span> {{ $honor->honor_months }} bulan<br>
                            <span class="font-medium">Tarif:</span> {{ \App\Support\SpjDisplay::rupiah($honor->rate_per_unit) }}/bulan<br>
                            <span class="font-medium">Bruto:</span> {{ \App\Support\SpjDisplay::rupiah($honor->gross_amount) }}<br>
                            <span class="font-medium">Pajak:</span> {{ \App\Support\SpjDisplay::rupiah($honor->tax_amount) }} ({{ $honor->tax_rate * 100 }}%)<br>
                            <span class="font-medium">Netto:</span> {{ \App\Support\SpjDisplay::rupiah($honor->net_amount) }}<br>
                            <span class="font-medium">Rekening:</span> {{ $honor->bank_name ?? '-' }} - {{ $honor->bank_account ?? '-' }}
                        </div>
                    </div>
                @endforeach
            @else
                <div class="text-[var(--ui-fg-muted)]">Belum ada data honor pegawai</div>
            @endif
            @break

        @case('JASA_LAINNYA')
            @php $recipients = $transaction->serviceRecipients; @endphp
            @if($recipients->count())
                @foreach($recipients as $recipient)
                    <div class="border-l-2 border-[var(--theme-accent)] pl-3 py-1.5">
                        <div class="font-medium text-[var(--ui-fg)]">{{ $recipient->name }}</div>
                        <div class="text-[12px] text-[var(--ui-fg-muted)]">
                            <span class="font-medium">NPWP:</span> {{ $recipient->npwp ?? '-' }}<br>
                            <span class="font-medium">Jenis Jasa:</span> {{ $recipient->service_type ?? '-' }}<br>
                            <span class="font-medium">Uraian:</span> {{ $recipient->service_description ?? '-' }}<br>
                            <span class="font-medium">Volume:</span> {{ $recipient->quantity }} {{ $recipient->unit }}<br>
                            @if($recipient->rental_days)
                                <span class="font-medium">Hari Sewa:</span> {{ $recipient->rental_days }}<br>
                                <span class="font-medium">Tarif Harian:</span> {{ \App\Support\SpjDisplay::rupiah($recipient->daily_rate) }}<br>
                            @endif
                            <span class="font-medium">Jumlah:</span> {{ \App\Support\SpjDisplay::rupiah($recipient->amount) }}<br>
                            <span class="font-medium">Pajak:</span> {{ \App\Support\SpjDisplay::rupiah($recipient->tax_amount) }}<br>
                            <span class="font-medium">Netto:</span> {{ \App\Support\SpjDisplay::rupiah($recipient->net_amount) }}<br>
                            <span class="font-medium">No Kuitansi:</span> {{ $recipient->receipt_number ?? '-' }}<br>
                            <span class="font-medium">Ref Pembayaran:</span> {{ $recipient->payment_reference ?? '-' }}<br>
                            <span class="font-medium">No Perjanjian:</span> {{ $recipient->agreement_number ?? '-' }}<br>
                            <span class="font-medium">Tgl Perjanjian:</span> {{ $recipient->agreement_date?->translatedFormat('d F Y') ?? '-' }}<br>
                            <span class="font-medium">Periode:</span> {{ $recipient->usage_started_at?->translatedFormat('d F Y') ?? '-' }} s.d. {{ $recipient->usage_completed_at?->translatedFormat('d F Y') ?? '-' }}
                            @if($recipient->is_receipt_recipient)
                                <br><x-ui.badge variant="success" size="xs">Penerima Kuitansi</x-ui.badge>
                            @endif
                        </div>
                    </div>
                @endforeach
            @else
                <div class="text-[var(--ui-fg-muted)]">Belum ada data jasa lainnya</div>
            @endif
            @break

        @default
            <div class="text-[var(--ui-fg-muted)]">Kategori tidak dikenali: {{ $category }}</div>
    @endswitch
</div>