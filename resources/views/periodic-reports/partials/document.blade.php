@php
    $rupiah = fn ($value) => number_format((float) $value, 0, ',', '.');
    $principalName = trim((string) ($profile->principal_name ?? '')) ?: '........................................';
    $principalNip = trim((string) ($profile->principal_nip ?? ''));
    $treasurerName = trim((string) ($profile->treasurer_name ?? '')) ?: '........................................';
    $treasurerNip = trim((string) ($profile->treasurer_nip ?? ''));
    $isLedger = in_array($presentation, ['bku_ledger', 'cash_ledger'], true);
    $isRekap = $presentation === 'rekap_bosp';
    $isBpk = $presentation === 'bpk_bos';
    $ledgerTitle = $presentation === 'cash_ledger' ? 'BUKU PEMBANTU KAS' : 'BUKU KAS UMUM (BKU)';
    $closingSubject = $presentation === 'cash_ledger' ? 'Buku Pembantu Kas' : 'Buku Kas Umum';
@endphp

<article class="document-sheet">
    @if($isLedger || $presentation === 'tax')
        <h1 class="doc-title">{{ $presentation === 'tax' ? 'BUKU PEMBANTU PAJAK' : $ledgerTitle }}</h1>
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
    @if(! $isRekap)
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
    @endif

    @if($isRekap && isset($rekapBosp))
        <h1 class="doc-title">REKAPITULASI REALISASI PENGGUNAAN DANA BOSP</h1>
        <div class="doc-subtitle">PERIODE TANGGAL : {{ $rekapTitle['range'] ?? '-' }}</div>
        <div class="doc-subtitle">{{ $rekapTitle['phase'] ?? '' }}</div>
        <table class="bku-identity-block page-break-avoid">
            <tr>
                <td>NPSN</td>
                <td>: {{ $school->npsn ?? '-' }}</td>
            </tr>
            <tr>
                <td>Nama Sekolah</td>
                <td>: {{ $school->name }}</td>
            </tr>
            <tr>
                <td>Kecamatan</td>
                <td>: {{ str_starts_with((string) $school->district, 'Kec.') ? $school->district : 'Kec. '.$school->district }}</td>
            </tr>
            <tr>
                <td>Kabupaten/Kota</td>
                <td>: {{ str_starts_with((string) $school->regency, 'Kab.') ? $school->regency : 'Kab. '.$school->regency }}</td>
            </tr>
            <tr>
                <td>Provinsi</td>
                <td>: {{ str_starts_with((string) $school->province, 'Prov.') ? $school->province : 'Prov. '.$school->province }}</td>
            </tr>
            <tr>
                <td>Sumber Dana</td>
                <td>: {{ $fundSource }}</td>
            </tr>
        </table>
        @php
            $snpRows = $rekapSnpRows ?? [];
            $subPrograms = $rekapSubPrograms ?? [];
            $rekapCols = range(1, 12);
        @endphp
        <table class="report-table rekap-bosp-table">
            <thead>
                <tr>
                    <th rowspan="3">No.<br>Urut</th>
                    <th rowspan="3">Standar Nasional<br>Pendidikan</th>
                    <th colspan="12">SUB PROGRAM</th>
                    <th rowspan="3">Jumlah</th>
                </tr>
                <tr>
                    @foreach($rekapCols as $col)
                        <th>{{ $subPrograms[$col] }}</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach($rekapCols as $col)
                        <th>{{ $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($snpRows as $rowIndex => $snpLabel)
                    @php
                        $rowNo = $rowIndex + 1;
                    @endphp
                    <tr>
                        <td class="integer">{{ $rowNo }}</td>
                        <td>{{ $snpLabel }}</td>
                        @foreach($rekapCols as $col)
                            <td class="money">{{ $rupiah($rekapBosp['grid'][$rowNo][$col] ?? 0) }}</td>
                        @endforeach
                        <td class="money"><strong>{{ $rupiah($rekapBosp['rowTotals'][$rowNo] ?? 0) }}</strong></td>
                    </tr>
                @endforeach
                <tr>
                    <td class="integer"></td>
                    <td><strong>JUMLAH</strong></td>
                    @foreach($rekapCols as $col)
                        <td class="money"><strong>{{ $rupiah($rekapBosp['colTotals'][$col] ?? 0) }}</strong></td>
                    @endforeach
                    <td class="money"><strong>{{ $rupiah($rekapBosp['grand'] ?? 0) }}</strong></td>
                </tr>
            </tbody>
        </table>
        <table class="meta-table page-break-avoid">
            <tr>
                <td>Saldo periode sebelumnya</td>
                <td>: Rp. {{ $rupiah($rekapBosp['prev'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>Total penerimaan dana BOSP periode ini</td>
                <td>: Rp. {{ $rupiah($rekapBosp['received'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>Total penggunaan dana BOSP periode ini</td>
                <td>: Rp. {{ $rupiah($rekapBosp['used'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>Akhir saldo BOSP periode ini</td>
                <td>: Rp. {{ $rupiah($rekapBosp['closing'] ?? 0) }}</td>
            </tr>
        </table>
    @endif

    @if(! in_array($presentation, ['bku_ledger', 'tax'], true) && ! $isRekap && ! $isBpk)
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

    @if(! empty($bpkData))
        @include('periodic-reports.partials.bpk-dinas')
    @endif


    @if($presentation === 'statement')
        <div class="statement">
            @foreach($statement as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>
    @endif

    @if(! $isLedger && ! $isRekap && ! $isBpk)
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
        <table class="report-table{{ $isLedger ? ' bku-table' : '' }}">
            @if($isLedger)
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

    @if($isLedger && isset($bkuClosing))
        <div class="bku-closing page-break-avoid">
            <p>Pada hari ini {{ $bkuClosing['weekday_date'] }} {{ $closingSubject }} Ditutup dengan keadaan/posisi buku sebagai berikut :</p>
            <table class="bku-closing-table">
                @if($presentation === 'cash_ledger')
                    <tr><td class="bku-closing-label"><strong>Saldo Kas Tunai</strong></td><td>: Rp. {{ $rupiah($bkuClosing['cash']) }}</td></tr>
                @else
                    <tr><td class="bku-closing-label"><strong>Saldo Buku Kas Umum</strong></td><td>: Rp. {{ $rupiah($bkuClosing['total']) }}</td></tr>
                @endif
                <tr><td class="bku-closing-label">Terdiri Dari :</td><td></td></tr>
                @if($presentation !== 'cash_ledger')
                    <tr><td class="bku-closing-label">- Saldo Bank</td><td>: Rp. {{ $rupiah($bkuClosing['bank']) }}</td></tr>
                @endif
                <tr><td class="bku-closing-label">- Saldo Kas Tunai</td><td>: Rp. {{ $rupiah($bkuClosing['cash']) }}</td></tr>
                <tr><td class="bku-closing-label"><strong>Jumlah</strong></td><td>: Rp. {{ $rupiah($bkuClosing['total']) }}</td></tr>
            </table>
        </div>
    @endif

    @if($isLedger || $presentation === 'tax')
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
    @endif
    @if($isRekap)
    <table class="signature-table">
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
                Bendahara /<br>
                Penanggungjawab Kegiatan
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
