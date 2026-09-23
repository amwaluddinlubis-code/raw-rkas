@php
    $rupiah = fn ($value) => number_format((float) $value, 0, ',', '.');
    $principalName = trim((string) ($profile->principal_name ?? '')) ?: '........................................';
    $principalNip = trim((string) ($profile->principal_nip ?? ''));
    $treasurerName = trim((string) ($profile->treasurer_name ?? '')) ?: '........................................';
    $treasurerNip = trim((string) ($profile->treasurer_nip ?? ''));
@endphp

<article class="document-sheet">
    @if($presentation === 'bku_ledger' || $presentation === 'tax')
        <h1 class="doc-title">{{ $presentation === 'tax' ? 'BUKU PEMBANTU PAJAK' : 'BUKU KAS UMUM (BKU)' }}</h1>
        @if($presentation === 'tax')
            <div class="doc-subtitle">BKU - PAJAK</div>
        @endif
        <table class="bku-identity-block page-break-avoid">
            <tr>
                <td>
                    <table class="bku-identity">
                        <tr><td>NPSN</td><td>: {{ $school->npsn ?? '-' }}</td></tr>
                        <tr><td>Nama Sekolah</td><td>: {{ $school->name }}</td></tr>
                        <tr><td>Alamat</td><td>: {{ $school->address ?: '-' }}</td></tr>
                        <tr><td>Desa / Kelurahan</td><td>: {{ $school->desa ?: '-' }}</td></tr>
                        <tr><td>Kecamatan</td><td>: {{ $school->district ?: '-' }}</td></tr>
                        <tr><td>Kabupaten / Kota</td><td>: {{ $school->regency ?: '-' }}</td></tr>
                        <tr><td>Provinsi</td><td>: {{ $school->province ?: '-' }}</td></tr>
                    </table>
                </td>
                <td>
                    <table class="bku-identity">
                        <tr><td>Sumber Dana</td><td>: {{ $bkuMeta['sumber_dana'] ?? $fundSource }}</td></tr>
                        <tr><td>Tahun Anggaran</td><td>: {{ $bkuMeta['tahun'] ?? $year->year }}</td></tr>
                        <tr><td>Periode</td><td>: {{ $bkuMeta['periode'] ?? $summary['period_label'] }}</td></tr>
                        <tr><td>Tanggal Periode</td><td>: {{ $bkuMeta['tanggal'] ?? '-' }}</td></tr>
                        <tr><td>Jumlah Transaksi</td><td>: {{ number_format((int) ($bkuMeta['jumlah_transaksi'] ?? 0), 0, ',', '.') }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>
    @else
    <header class="doc-header">
        <div class="school">{{ $school->name }}</div>
        <div class="address">
            {{ $school->address ?: '-' }}
            @if($school->district || $school->regency)
                · {{ collect([$school->district, $school->regency, $school->province])->filter()->implode(', ') }}
            @endif
        </div>
        @if($school->npsn)
            <div class="address">NPSN {{ $school->npsn }}</div>
        @endif
    </header>

    <h1 class="doc-title">{{ $report['label'] }}</h1>
    <div class="doc-subtitle">{{ $summary['period_label'] }}</div>
    @endif

    @if(! in_array($presentation, ['bku_ledger', 'tax'], true))
    <table class="meta-table page-break-avoid">
        <tr>
            <td>Tahun Anggaran</td>
            <td>: {{ $year->year }}</td>
            <td>Sumber Dana</td>
            <td>: {{ $fundSource }}</td>
        </tr>
        <tr>
            <td>Periode</td>
            <td>: {{ $summary['date_from'] }} s.d. {{ $summary['date_to'] }}</td>
            <td>Jumlah Transaksi</td>
            <td>: {{ number_format((int) $summary['transaction_count'], 0, ',', '.') }}</td>
        </tr>
    </table>
    @endif

    @if($presentation === 'statement')
        <div class="statement">
            @foreach($statement as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>
    @endif

    @if($presentation !== 'bku_ledger')
    <table class="summary-table page-break-avoid">
        <thead>
            <tr>
                <th>Nilai Bruto</th>
                <th>Total Pajak</th>
                <th>Nilai Dibayarkan</th>
                <th>PPN</th>
                <th>PPh 21</th>
                <th>PPh 22</th>
                <th>PPh 23</th>
                <th>PPh 4(2)</th>
                <th>SSPD</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="money">{{ $rupiah($summary['gross']) }}</td>
                <td class="money">{{ $rupiah($summary['tax']) }}</td>
                <td class="money">{{ $rupiah($summary['net']) }}</td>
                <td class="money">{{ $rupiah($summary['ppn']) }}</td>
                <td class="money">{{ $rupiah($summary['pph21']) }}</td>
                <td class="money">{{ $rupiah($summary['pph22']) }}</td>
                <td class="money">{{ $rupiah($summary['pph23']) }}</td>
                <td class="money">{{ $rupiah($summary['pph4']) }}</td>
                <td class="money">{{ $rupiah($summary['sspd']) }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    @if(count($columns) > 0)
        @if($presentation === 'statement')
            <div style="margin-top:12px;font-weight:700;">Rekap Pendukung</div>
        @endif
        <table class="report-table{{ $presentation === 'bku_ledger' ? ' bku-table' : '' }}">
            @if($presentation === 'bku_ledger')
                <colgroup>
                    <col style="width:72px">
                    <col style="width:68px">
                    <col style="width:112px">
                    <col style="width:62px">
                    <col>
                    <col style="width:94px">
                    <col style="width:94px">
                    <col style="width:94px">
                </colgroup>
            @endif
            <thead>
                <tr>
                    @foreach($columns as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr class="{{ $row['row_class'] ?? '' }}">
                        @foreach($columns as $column)
                            @php
                                $value = $row[$column['key']] ?? null;
                                $type = $column['type'];
                            @endphp
                            <td class="{{ $type }}">
                                @if($type === 'money')
                                    {{ $value === '' || $value === null ? '' : $rupiah($value) }}
                                @elseif($type === 'integer')
                                    {{ $value === '' || $value === null ? '' : number_format((int) $value, 0, ',', '.') }}
                                @elseif($type === 'nowrap')
                                    <span class="nowrap">{{ $value ?: '-' }}</span>
                                @elseif($type === 'stacked')
                                    {!! $value ?: '-' !!}
                                @else
                                    {{ $value ?: '-' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ max(1, count($columns)) }}" class="empty-row">Tidak ada transaksi pada periode ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif

    @if($presentation === 'bku_ledger' && isset($bkuClosing))
        <div class="bku-closing page-break-avoid">
            <p>Pada hari ini {{ $bkuClosing['weekday_date'] }} Buku Kas Umum Ditutup dengan keadaan/posisi buku sebagai berikut :</p>
            <table class="bku-closing-table">
                <tr><td class="bku-closing-label"><strong>Saldo Buku Kas Umum</strong></td><td>: Rp. {{ $rupiah($bkuClosing['total']) }}</td></tr>
                <tr><td class="bku-closing-label">Terdiri Dari :</td><td></td></tr>
                <tr><td class="bku-closing-label">- Saldo Bank</td><td>: Rp. {{ $rupiah($bkuClosing['bank']) }}</td></tr>
                <tr><td class="bku-closing-label">- Saldo Kas Tunai</td><td>: Rp. {{ $rupiah($bkuClosing['cash']) }}</td></tr>
                <tr><td class="bku-closing-label"><strong>Jumlah</strong></td><td>: Rp. {{ $rupiah($bkuClosing['total']) }}</td></tr>
            </table>
        </div>
    @endif

    @if($presentation === 'bku_ledger' || $presentation === 'tax')
    <table class="signature-table bku-signature">
        <tr>
            <td>
                Menyetujui,<br>
                Kepala Sekolah
                <div class="signature-space"></div>
                <div class="signature-name">{{ $principalName }}</div>
                @if($principalNip)
                    <div>NIP. {{ $principalNip }}</div>
                @endif
            </td>
            <td>
                @if($school->district)Kec. {{ $school->district }}, @endif{{ $bkuClosing['signed_date'] ?? '' }}<br>
                Bendahara,
                <div class="signature-space"></div>
                <div class="signature-name">{{ $treasurerName }}</div>
                @if($treasurerNip)
                    <div>NIP. {{ $treasurerNip }}</div>
                @endif
            </td>
        </tr>
    </table>
    @else
    <table class="signature-table">
        <tr>
            <td>
                Mengetahui,<br>
                Kepala Sekolah
                <div class="signature-space"></div>
                <div class="signature-name">{{ $principalName }}</div>
                @if($principalNip)
                    <div>NIP. {{ $principalNip }}</div>
                @endif
            </td>
            <td>
                Bendahara BOSP
                <div class="signature-space"></div>
                <div class="signature-name">{{ $treasurerName }}</div>
                @if($treasurerNip)
                    <div>NIP. {{ $treasurerNip }}</div>
                @endif
            </td>
        </tr>
    </table>
    @endif

    <div class="note">
        Dicetak dari APP-SPJ pada {{ $generatedAt->format('d-m-Y H:i') }}. Nilai laporan bersumber dari transaksi pada konteks sekolah, tahun anggaran, dan sumber dana aktif. Dokumen harus dicocokkan dengan bukti fisik/rekening koran dan ketentuan instansi sebelum ditandatangani.
    </div>
</article>
