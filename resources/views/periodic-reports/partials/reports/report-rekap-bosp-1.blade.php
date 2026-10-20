@if ($isRekap && isset($rekapBosp))
    @php
        /*
        | Lebar kolom Rekapitulasi Realisasi Penggunaan Dana BOSP.
        | Dapat dikustom melalui config('spj.periodic_report_column_widths.rekap_bosp');
        | kunci yang tidak diisi memakai nilai default di bawah.
        | `no`/`standar_nasional`/`sub_program`/`jumlah` = kolom grid,
        | `identity` = blok identitas sekolah, `meta` = blok saldo.
        */
        $rekapDefaultWidths = [
            'no' => '4%',
            'standar_nasional' => '16%',
            'sub_program' => '5.5%',
            'jumlah' => '14%',
            'identity' => ['25%', '75%'],
            'meta' => ['60%', '40%'],
        ];
        $rekapColumnWidths = array_replace(
            $rekapDefaultWidths,
            (array) config('spj.periodic_report_column_widths.rekap_bosp', []),
        );

        $rekapIdentityWidths = (array) $rekapColumnWidths['identity'];
        $rekapMetaWidths = (array) $rekapColumnWidths['meta'];
        $snpRows = $rekapSnpRows ?? [];
        $subPrograms = $rekapSubPrograms ?? [];
        $rekapCols = range(1, 12);
        $rekapGridWidths = array_merge(
            [(string) $rekapColumnWidths['no'], (string) $rekapColumnWidths['standar_nasional']],
            array_fill(0, count($rekapCols), (string) $rekapColumnWidths['sub_program']),
            [(string) $rekapColumnWidths['jumlah']],
        );
    @endphp

    <h1 class="doc-title">REKAPITULASI REALISASI PENGGUNAAN DANA BOSP</h1>
    <div class="doc-subtitle">PERIODE TANGGAL : {{ $rekapTitle['range'] ?? '-' }}</div>
    <div class="doc-subtitle">{{ $rekapTitle['phase'] ?? '' }}</div>
    <table class="bku-identity-block page-break-avoid">
        @include('periodic-reports.partials.reports.colgroup', ['widths' => $rekapIdentityWidths])
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
            <td>:
                {{ str_starts_with((string) $school->district, 'Kec.') ? $school->district : 'Kec. ' . $school->district }}
            </td>
        </tr>
        <tr>
            <td>Kabupaten/Kota</td>
            <td>:
                {{ str_starts_with((string) $school->regency, 'Kab.') ? $school->regency : 'Kab. ' . $school->regency }}
            </td>
        </tr>
        <tr>
            <td>Provinsi</td>
            <td>:
                {{ str_starts_with((string) $school->province, 'Prov.') ? $school->province : 'Prov. ' . $school->province }}
            </td>
        </tr>
        <tr>
            <td>Sumber Dana</td>
            <td>: {{ $fundSource }}</td>
        </tr>
    </table>

    <table class="report-table rekap-bosp-table">
        @include('periodic-reports.partials.reports.colgroup', ['widths' => $rekapGridWidths])
        <thead>
            <tr>
                <th rowspan="3">No.<br>Urut</th>
                <th rowspan="3">Standar Nasional<br>Pendidikan</th>
                <th colspan="12">Sub Program</th>
                <th rowspan="3">Jumlah</th>
            </tr>
            <tr>
                @foreach ($rekapCols as $col)
                    <th>{{ $subPrograms[$col] ?? '' }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach ($rekapCols as $col)
                    <th>{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($snpRows as $rowIndex => $snpLabel)
                @php
                    $rowNo = $rowIndex + 1;
                @endphp
                <tr>
                    <td class="integer">{{ $rowNo }}</td>
                    <td>{{ $snpLabel }}</td>
                    @foreach ($rekapCols as $col)
                        <td class="money">{{ $rupiah($rekapBosp['grid'][$rowNo][$col] ?? 0) }}</td>
                    @endforeach
                    <td class="money"><strong>{{ $rupiah($rekapBosp['rowTotals'][$rowNo] ?? 0) }}</strong></td>
                </tr>
            @endforeach
            <tr>
                <td class="integer"></td>
                <td><strong>JUMLAH</strong></td>
                @foreach ($rekapCols as $col)
                    <td class="money"><strong>{{ $rupiah($rekapBosp['colTotals'][$col] ?? 0) }}</strong></td>
                @endforeach
                <td class="money"><strong>{{ $rupiah($rekapBosp['grand'] ?? 0) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <table class="meta-table page-break-avoid">
        @include('periodic-reports.partials.reports.colgroup', ['widths' => $rekapMetaWidths])
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
