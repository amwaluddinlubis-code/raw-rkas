@php
    $rupiah = fn($value) => number_format((float) $value, 0, ',', '.');
    $principalName = trim((string) ($profile->principal_name ?? '')) ?: '........................................';
    $principalNip = trim((string) ($profile->principal_nip ?? ''));
    $treasurerName = trim((string) ($profile->treasurer_name ?? '')) ?: '........................................';
    $treasurerNip = trim((string) ($profile->treasurer_nip ?? ''));
    $committeeName = trim((string) ($profile->committee_name ?? '')) ?: '........................................';
    $isLedger = in_array($presentation, ['bku_ledger', 'cash_ledger'], true);
    $isRekap = $presentation === 'rekap_bosp';
    $isBpk = $presentation === 'bpk_bos';
    $isA1 = $presentation === 'bos_a1' && isset($bosA1);
    $isK7b = $presentation === 'k7b' && isset($k7b);
    $isK7c = $presentation === 'k7c' && isset($k7c);
    $isK7 = $presentation === 'k7' && isset($k7);
    $isK2 = $presentation === 'k2' && isset($k2);
    $isRpd = $presentation === 'rpd' && isset($rpd);
    $isSptjm = $presentation === 'sptjm_doc' && isset($sptjm);
    $ledgerTitle = $presentation === 'cash_ledger' ? 'BUKU PEMBANTU KAS' : 'BUKU KAS UMUM (BKU)';
    $closingSubject = $presentation === 'cash_ledger' ? 'Buku Pembantu Kas' : 'Buku Kas Umum';
    $identityWidths = ['50%', '50%'];
    $bkuIdentityWidths = ['30%', '70%'];
    $summaryWidths = ['14%', '12%', '14%', '10%', '10%', '10%', '10%', '10%', '10%'];
    $genericMetaWidths = ['18%', '32%', '18%', '32%'];
    $bkuClosingWidths = ['60%', '40%'];
    $signatureTwoWidths = ['50%', '50%'];
@endphp

<article class="document-sheet">
    @if ($isLedger || $presentation === 'tax')
        <h1 class="doc-title">{{ $presentation === 'tax' ? 'BUKU PEMBANTU PAJAK' : $ledgerTitle }}</h1>
        @if ($presentation === 'tax')
            <div class="doc-subtitle">BKU - PAJAK</div>
        @endif
        <table class="bku-identity-block page-break-avoid">
            @include('periodic-reports.partials.reports.colgroup', ['widths' => $identityWidths])
            <tr>
                <td>
                    <table class="bku-identity">
                        @include('periodic-reports.partials.reports.colgroup', [
                            'widths' => $bkuIdentityWidths,
                        ])
                        <tr>
                            <td>NPSN</td>
                            <td>: {{ $school->npsn ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td>Nama Sekolah</td>
                            <td>: {{ $school->name }}</td>
                        </tr>
                        <tr>
                            <td>Alamat</td>
                            <td>: {{ $school->address ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td>Desa / Kelurahan</td>
                            <td>: {{ $school->desa ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td>Kecamatan</td>
                            <td>: {{ $school->district ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td>Kabupaten / Kota</td>
                            <td>: {{ $school->regency ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td>Provinsi</td>
                            <td>: {{ $school->province ?: '-' }}</td>
                        </tr>
                    </table>
                </td>
                <td>
                    <table class="bku-identity">
                        @include('periodic-reports.partials.reports.colgroup', [
                            'widths' => $bkuIdentityWidths,
                        ])
                        <tr>
                            <td>Sumber Dana</td>
                            <td>: {{ $bkuMeta['sumber_dana'] ?? $fundSource }}</td>
                        </tr>
                        <tr>
                            <td>Tahun Anggaran</td>
                            <td>: {{ $bkuMeta['tahun'] ?? $year->year }}</td>
                        </tr>
                        <tr>
                            <td>Periode</td>
                            <td>: {{ $bkuMeta['periode'] ?? $summary['period_label'] }}</td>
                        </tr>
                        <tr>
                            <td>Tanggal Periode</td>
                            <td>: {{ $bkuMeta['tanggal'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td>Jumlah Transaksi</td>
                            <td>: {{ number_format((int) ($bkuMeta['jumlah_transaksi'] ?? 0), 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    @else
        @include('periodic-reports.partials.reports.report-a1-1')
        @include('periodic-reports.partials.reports.report-k7b-1')
        @include('periodic-reports.partials.reports.report-k7c-1')
        @include('periodic-reports.partials.reports.report-k2-1')
        @include('periodic-reports.partials.reports.report-rpd-1')
        @include('periodic-reports.partials.reports.report-k7-1')
        @include('periodic-reports.partials.reports.report-sptjm-1')
        @if (!$isRekap && !$isA1 && !$isK7b && !$isK7c && !$isK7 && !$isK2 && !$isRpd && !$isSptjm)
            <header class="doc-header">
                <div class="school">{{ $school->name }}</div>
                <div class="address">
                    {{ $school->address ?: '-' }}
                    @if ($school->district || $school->regency)
                        ·
                        {{ collect([$school->district, $school->regency, $school->province])->filter()->implode(', ') }}
                    @endif
                </div>
                @if ($school->npsn)
                    <div class="address">NPSN {{ $school->npsn }}</div>
                @endif
            </header>

            <h1 class="doc-title">{{ $report['label'] }}</h1>
            <div class="doc-subtitle">{{ $summary['period_label'] }}</div>
        @endif
    @endif

    @include('periodic-reports.partials.reports.report-rekap-bosp-1')

    @include('periodic-reports.partials.reports.report-a1-2')

    @include('periodic-reports.partials.reports.report-k7b-2')

    @include('periodic-reports.partials.reports.report-k7c-2')

    @include('periodic-reports.partials.reports.report-k2-2')

    @include('periodic-reports.partials.reports.report-rpd-2')

    @include('periodic-reports.partials.reports.k7-table')

    @include('periodic-reports.partials.reports.sptjm')
    @if (
        !in_array($presentation, ['bku_ledger', 'tax'], true) &&
            !$isRekap &&
            !$isBpk &&
            !$isA1 &&
            !$isK7b &&
            !$isK7c &&
            !$isK7 &&
            !$isK2 &&
            !$isRpd &&
            !$isSptjm)
        <table class="meta-table page-break-avoid">
            @include('periodic-reports.partials.reports.colgroup', ['widths' => $genericMetaWidths])
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

    @include('periodic-reports.partials.reports.report-bpk-1')


    @if ($presentation === 'statement')
        <div class="statement">
            @foreach ($statement as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>
    @endif

    @if (!$isLedger && !$isRekap && !$isBpk && !$isA1 && !$isK7b && !$isK7c && !$isK7 && !$isK2 && !$isRpd && !$isSptjm)
        <table class="summary-table page-break-avoid">
            @include('periodic-reports.partials.reports.colgroup', ['widths' => $summaryWidths])
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

    @if (count($columns) > 0)
        @if ($presentation === 'statement')
            <div style="margin-top:12px;font-weight:700;">Rekap Pendukung</div>
        @endif
        <table class="report-table{{ $isLedger ? ' bku-table' : '' }}">
            @include('periodic-reports.partials.reports.report-ledger-1')
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr class="{{ $row['row_class'] ?? '' }}">
                        @foreach ($columns as $column)
                            @php
                                $value = $row[$column['key']] ?? null;
                                $type = $column['type'];
                            @endphp
                            <td class="{{ $type }}">
                                @if ($type === 'money')
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
                        <td colspan="{{ max(1, count($columns)) }}" class="empty-row">Tidak ada transaksi pada periode
                            ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif

    @if ($isLedger && isset($bkuClosing))
        <div class="bku-closing page-break-avoid">
            <p>Pada hari ini {{ $bkuClosing['weekday_date'] }} {{ $closingSubject }} Ditutup dengan keadaan/posisi
                buku sebagai berikut :</p>
            <table class="bku-closing-table">
                @include('periodic-reports.partials.reports.colgroup', ['widths' => $bkuClosingWidths])
                @if ($presentation === 'cash_ledger')
                    <tr>
                        <td class="bku-closing-label"><strong>Saldo Kas Tunai</strong></td>
                        <td>: Rp. {{ $rupiah($bkuClosing['cash']) }}</td>
                    </tr>
                @else
                    <tr>
                        <td class="bku-closing-label"><strong>Saldo Buku Kas Umum</strong></td>
                        <td>: Rp. {{ $rupiah($bkuClosing['total']) }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="bku-closing-label">Terdiri Dari :</td>
                    <td></td>
                </tr>
                @if ($presentation !== 'cash_ledger')
                    <tr>
                        <td class="bku-closing-label">- Saldo Bank</td>
                        <td>: Rp. {{ $rupiah($bkuClosing['bank']) }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="bku-closing-label">- Saldo Kas Tunai</td>
                    <td>: Rp. {{ $rupiah($bkuClosing['cash']) }}</td>
                </tr>
                <tr>
                    <td class="bku-closing-label"><strong>Jumlah</strong></td>
                    <td>: Rp. {{ $rupiah($bkuClosing['total']) }}</td>
                </tr>
            </table>
        </div>
    @endif

    @if ($isLedger || $presentation === 'tax')
        <table class="signature-table bku-signature">
            @include('periodic-reports.partials.reports.colgroup', ['widths' => $signatureTwoWidths])
            <tr>
                <td>
                    Menyetujui,<br>
                    Kepala Sekolah
                    <div class="signature-space"></div>
                    <div class="signature-name">{{ $principalName }}</div>
                    @if ($principalNip)
                        <div>NIP. {{ $principalNip }}</div>
                    @endif
                </td>
                <td>
                    @if ($school->district)
                        Kec. {{ $school->district }},
                    @endif{{ $bkuClosing['signed_date'] ?? '' }}<br>
                    Bendahara,
                    <div class="signature-space"></div>
                    <div class="signature-name">{{ $treasurerName }}</div>
                    @if ($treasurerNip)
                        <div>NIP. {{ $treasurerNip }}</div>
                    @endif
                </td>
            </tr>
        </table>
    @endif
    @include('periodic-reports.partials.reports.report-rekap-1')

    <div class="note">
        Dicetak dari APP-SPJ pada {{ $generatedAt->format('d-m-Y H:i') }}. Nilai laporan bersumber dari transaksi pada
        konteks sekolah, tahun anggaran, dan sumber dana aktif. Dokumen harus dicocokkan dengan bukti fisik/rekening
        koran dan ketentuan instansi sebelum ditandatangani.
    </div>
</article>
