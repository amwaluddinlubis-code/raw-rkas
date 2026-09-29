<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $year }}</title>
    <style>
        @page {
            size: folio landscape;
            margin: 10mm 9mm 13mm 9mm;

            @bottom-left {
                content: "{{ $footer_tag }} - NPSN : {{ $school['npsn'] }}, Nama Sekolah : {{ $school['name'] }}";
                font-size: 7px;
                color: #4b5563;
            }

            @bottom-right {
                content: "Halaman " counter(page) " dari " counter(pages);
                font-size: 7px;
                color: #4b5563;
            }
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111827;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8px;
            line-height: 1.3;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }

        h1 {
            font-size: 13px;
            text-align: center;
            margin: 0;
        }

        h2 {
            font-size: 11px;
            text-align: center;
            margin: 2px 0 0;
        }

        .rev-line {
            text-align: center;
            font-size: 9px;
            margin: 2px 0 8px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .report-detail-table {
            table-layout: fixed;
        }

        th,
        td {
            border: 1px solid #111827;
            padding: 2px 4px;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            text-align: center;
            vertical-align: middle;
            font-weight: bold;
        }

        th.volume,
        td.volume,
        th.satuan,
        td.satuan {
            text-align: center;
        }

        th.volume,
        td.volume {
            width: 5%;
        }

        th.satuan,
        td.satuan {
            width: 7%;
        }

        .num {
            text-align: right;
            white-space: nowrap;
        }

        .center {
            text-align: center;
        }

        .bold {
            font-weight: bold;
        }

        .ident td {
            border: 0;
            padding: 1px 4px;
        }

        .lvl-program {
            background: #f8d7da;
            font-weight: bold;
        }

        .lvl-subprogram {
            background: #d4edda;
            font-weight: bold;
        }

        .lvl-activity {
            background: #fff3cd;
            font-weight: bold;
        }

        .section-title {
            font-size: 10px;
            font-weight: bold;
            margin: 10px 0 4px;
        }

        .legend {
            font-size: 7px;
            font-style: italic;
            margin-top: 2px;
        }

        .ttd {
            margin-top: 18px;
            width: 100%;
        }

        .ttd td {
            border: 0;
            text-align: center;
            vertical-align: top;
        }

        .ttd .name {
            font-weight: bold;
            margin: 7px 0 0;
        }

        .ttd .signature-image-slot {
            align-items: end;
            display: flex;
            height: 48px;
            justify-content: center;
        }

        .ttd .signature-image {
            display: block;
            max-height: 46px;
            max-width: 180px;
            object-fit: contain;
        }

        .ttd .signature-line {
            border-bottom: 1px solid #111827;
            height: 5px;
            margin: 0 auto 1px;
            min-width: 180px;
            width: 50%;
        }

        .ttd .nip {
            margin: 0;
        }

        .place {
            text-align: right;
            margin-bottom: 4px;
            margin-left: 67%;
            text-align: center;
            width: 33%;
        }
    </style>
</head>

<body>
    @php($uang = fn($v) => number_format((float) $v, 0, ',', '.'))
    @php($vol = fn($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ','))
    @php($fundCols = ['BOSP REGULER', 'BOSP DAERAH', 'AFIRMASI', 'KINERJA', 'SiLPA', 'BOSP LAINNYA'])
    @php($monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'])
    @php(
    $detailWidths = match ($scope) {
        'triwulan' => ['3%', '8%', '5%', '', '4%', '6%', '4%', '7%', '6%', '6%', '6%', '6%'],
        'triwulan-bulanan' => ['3%', '8%', '5%', '', '4%', '6%', '5%', '7%', '6%', '6%', '6%'],
        'tahap' => ['3%', '8%', '5%', '', '6%', '6%', '5%', '7%', '6.5%', '6.5%'],
        default => ['3%', '8%', '5%', '', '4%', '6%', '5%', '6%']
    }
)

    <h1>{{ $title }}</h1>
    <h2>TAHUN ANGGARAN : {{ $year }}</h2>
    <p class="rev-line">{{ $revision_label }}{{ $revision_date !== '-' ? ' · ' . $revision_date : '' }}</p>

    <table class="ident">
        <tr>
            <td style="width: 90px;">NPSN</td>
            <td style="width: 12px;">:</td>
            <td>{{ $school['npsn'] }}</td>
        </tr>
        <tr>
            <td>Nama Sekolah</td>
            <td>:</td>
            <td>{{ $school['name'] }}</td>
        </tr>
        <tr>
            <td>Alamat</td>
            <td>:</td>
            <td>{{ $school['address'] }}</td>
        </tr>
        <tr>
            <td>Kabupaten</td>
            <td>:</td>
            <td>{{ $school['regency'] }}</td>
        </tr>
        <tr>
            <td>Provinsi</td>
            <td>:</td>
            <td>{{ $school['province'] }}</td>
        </tr>
        @if ($scope === 'triwulan')
            <tr>
                <td>Triwulan</td>
                <td>:</td>
                <td>{{ $scope_label }}</td>
            </tr>
        @elseif ($scope === 'tahap')
            <tr>
                <td>Tahap</td>
                <td>:</td>
                <td>{{ $scope_label }}</td>
            </tr>
        @elseif ($scope === 'triwulan-bulanan')
            <tr>
                <td>Triwulan</td>
                <td>:</td>
                <td>{{ $scope_label }}</td>
            </tr>
        @elseif ($scope === 'bulanan')
            <tr>
                <td>Bulan</td>
                <td>:</td>
                <td>{{ $scope_label }}{{ $year }}</td>
            </tr>
        @endif
        <tr>
            <td>Sumber Dana</td>
            <td>:</td>
            <td>{{ $fund_name }}</td>
        </tr>
    </table>

    <p class="section-title">{{ $penerimaan_heading }}</p>
    <p style="margin: 0 0 4px; font-weight: bold;">{{ $penerimaan_source_label }}</p>
    <table style="width: 60%;">
        <thead>
            <tr>
                <th>No. Kode</th>
                <th>Penerimaan</th>
                <th>Jumlah</th>
            </tr>
        </thead>
        @php($penerimaanRows = $scope === 'tahunan' ? $penerimaan : array_values(array_filter($penerimaan, fn($row) => $row['active'])))
        @foreach ($penerimaanRows as $row)
            <tr>
                <td class="center">{{ $row['code'] }}</td>
                <td>{{ $row['label'] }}{{ $row['active'] ? '' : ' **' }}</td>
                <td class="num">{{ $uang($row['amount']) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="2" class="bold">Total Penerimaan</td>
            <td class="num bold">{{ $uang($totals['jumlah']) }}</td>
        </tr>
    </table>
    <p class="legend">* belum pengesahan, ** belum aktivasi anggaran, ~ penerimaan dan belanja tidak sesuai</p>

    <p class="section-title">B. BELANJA</p>

    @if ($scope === 'tahunan')
        <table class="report-detail-table">
            <colgroup>
                <col style="width: 3%">
                <col style="width: 8%">
                <col style="width: 5%">
                <col style="width: 24%">
                <col style="width: 7%">
                <col style="width: 6.5%">
                <col style="width: 6.5%">
                @for ($column = 0; $column < 10; $column++)
                    <col style="width: 4%">
                @endfor
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="3">No. Urut</th>
                    <th rowspan="3">Kode Rekening</th>
                    <th rowspan="3">Kode Kegiatan</th>
                    <th rowspan="3">Uraian Kegiatan</th>
                    <th rowspan="3">Jumlah</th>
                    <th colspan="12">Sumber Dana dan Alokasi Anggaran</th>
                </tr>
                <tr>
                    @foreach ($fundCols as $fundCol)
                        <th colspan="2">{{ $fundCol }}</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($fundCols as $fundCol)
                        <th>Belanja Operasi</th>
                        <th>Belanja Modal</th>
                    @endforeach
                </tr>
            </thead>
            @foreach ($lines as $line)
                <tr
                    class="{{ $line['level'] === 'program' ? 'lvl-program' : ($line['level'] === 'subprogram' ? 'lvl-subprogram' : ($line['level'] === 'activity' ? 'lvl-activity' : '')) }}">
                    <td class="center">{{ $line['no'] }}</td>
                    <td>{{ $line['level'] === 'item' || $line['level'] === 'account' ? $line['account_code'] : '' }}
                    </td>
                    <td>{{ $line['activity_code'] }}</td>
                    <td class="{{ in_array($line['level'], ['program', 'subprogram', 'activity']) ? 'bold' : '' }}">
                        {{ $line['name'] }}</td>
                    <td
                        class="num {{ in_array($line['level'], ['program', 'subprogram', 'activity']) ? 'bold' : '' }}">
                        {{ $uang($line['jumlah']) }}</td>
                    @for ($c = 0; $c < 6; $c++)
                        <td class="num">{{ $c === $fund_column ? $uang($line['operasi']) : '0' }}</td>
                        <td class="num">{{ $c === $fund_column ? $uang($line['modal']) : '0' }}</td>
                    @endfor
                </tr>
            @endforeach
            <tr>
                <td colspan="4" class="center bold">Jumlah</td>
                <td class="num bold">{{ $uang($totals['jumlah']) }}</td>
                @for ($c = 0; $c < 6; $c++)
                    <td class="num bold">{{ $c === $fund_column ? $uang($totals['operasi']) : '0' }}</td>
                    <td class="num bold">{{ $c === $fund_column ? $uang($totals['modal']) : '0' }}</td>
                @endfor
            </tr>
        </table>
    @else
        <table class="report-detail-table">
            <colgroup>
                @foreach ($detailWidths as $width)
                    <col style="width: {{ $width }}">
                @endforeach
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2">No. Urut</th>
                    <th rowspan="2">Kode Rekening</th>
                    <th rowspan="2">Kode Program</th>
                    <th class="uraian" rowspan="2">Uraian</th>
                    <th colspan="3">Rincian Perhitungan</th>
                    <th rowspan="2">Jumlah</th>
                    @if ($scope === 'triwulan')
                        <th colspan="4">Triwulan</th>
                    @elseif ($scope === 'tahap')
                        <th colspan="2">Tahap</th>
                    @elseif ($scope === 'triwulan-bulanan')
                        <th colspan="3">Bulan dalam Triwulan</th>
                    @endif
                </tr>
                <tr>
                    <th class="volume">Volume</th>
                    <th class="satuan">Satuan</th>
                    <th>Tarif Harga</th>
                    @if ($scope === 'triwulan')
                        <th>1</th>
                        <th>2</th>
                        <th>3</th>
                        <th>4</th>
                    @elseif ($scope === 'tahap')
                        <th>1</th>
                        <th>2</th>
                    @elseif ($scope === 'triwulan-bulanan')
                        @foreach ($quarter_months as $quarterMonth)
                            <th>{{ $monthNames[$quarterMonth - 1] ?? $quarterMonth }}</th>
                        @endforeach
                    @endif
                </tr>
            </thead>
            @foreach ($lines as $line)
                <tr
                    class="{{ $line['level'] === 'program' ? 'lvl-program' : ($line['level'] === 'subprogram' ? 'lvl-subprogram' : ($line['level'] === 'activity' ? 'lvl-activity' : '')) }}">
                    <td class="center">{{ $line['no'] }}</td>
                    <td>{{ $line['level'] === 'item' || $line['level'] === 'account' ? $line['account_code'] : '' }}
                    </td>
                    <td>{{ $line['activity_code'] }}</td>
                    <td
                        class="uraian {{ in_array($line['level'], ['program', 'subprogram', 'activity']) ? 'bold' : '' }}">
                        {{ $line['name'] }}</td>
                    <td class="volume">{{ $line['level'] === 'item' ? $vol($line['volume']) : '' }}</td>
                    <td class="satuan">{{ $line['level'] === 'item' ? $line['unit'] : '' }}</td>
                    <td class="num">{{ $line['level'] === 'item' ? $uang($line['unit_price']) : '' }}</td>
                    <td
                        class="num {{ in_array($line['level'], ['program', 'subprogram', 'activity']) ? 'bold' : '' }}">
                        {{ $uang(in_array($scope, ['bulanan', 'triwulan-bulanan'], true) ? $line['scoped'] : $line['jumlah']) }}
                    </td>
                    @if ($scope === 'triwulan')
                        @for ($q = 1; $q <= 4; $q++)
                            <td class="num">{{ $uang($line['tw'][$q]) }}</td>
                        @endfor
                    @elseif ($scope === 'tahap')
                        <td class="num">{{ $uang($line['tahap'][0]) }}</td>
                        <td class="num">{{ $uang($line['tahap'][1]) }}</td>
                    @elseif ($scope === 'triwulan-bulanan')
                        @foreach ($quarter_months as $quarterMonth)
                            <td class="num">{{ $uang($line['months'][$quarterMonth] ?? 0) }}</td>
                        @endforeach
                    @endif
                </tr>
            @endforeach
            <tr>
                <td colspan="7" class="center bold">Jumlah</td>
                <td class="num bold">
                    {{ $uang(in_array($scope, ['bulanan', 'triwulan-bulanan'], true) ? $totals['scoped'] : $totals['jumlah']) }}
                </td>
                @if ($scope === 'triwulan')
                    @for ($q = 1; $q <= 4; $q++)
                        <td class="num bold">{{ $uang($totals['tw'][$q]) }}</td>
                    @endfor
                @elseif ($scope === 'tahap')
                    <td class="num bold">{{ $uang($totals['tahap'][0]) }}</td>
                    <td class="num bold">{{ $uang($totals['tahap'][1]) }}</td>
                @elseif ($scope === 'triwulan-bulanan')
                    @foreach ($quarter_months as $quarterMonth)
                        <td class="num bold">{{ $uang($totals['months'][$quarterMonth] ?? 0) }}</td>
                    @endforeach
                @endif
            </tr>
        </table>
    @endif

    <p class="place">{{ $place_date }}</p>
    <table class="ttd">
        <tr>
            <td style="width: 33%;">Komite Sekolah</td>
            <td style="width: 34%;">Kepala Sekolah</td>
            <td style="width: 33%;">Bendahara Sekolah</td>
        </tr>
        <tr>
            <td>
                <div class="signature-image-slot">
                    @if ($signatories['committee_signature'])
                        <img class="signature-image" src="{{ $signatories['committee_signature'] }}"
                            alt="Tanda tangan komite sekolah">
                    @endif
                </div>
                <p class="name">
                    {{ $signatories['committee_name'] !== '' ? $signatories['committee_name'] : '&nbsp;' }}</p>
                <p class="signature-line"></p>
            </td>
            <td>
                <div class="signature-image-slot">
                    @if ($signatories['principal_signature'])
                        <img class="signature-image" src="{{ $signatories['principal_signature'] }}"
                            alt="Tanda tangan kepala sekolah">
                    @endif
                </div>
                <p class="name">{{ $signatories['principal_name'] !== '' ? $signatories['principal_name'] : '_' }}
                </p>
                <p class="signature-line"></p>
                @if ($signatories['principal_nip'] !== '')
                    <p class="nip">NIP: {{ $signatories['principal_nip'] }}</p>
                @endif
            </td>
            <td>
                <div class="signature-image-slot">
                    @if ($signatories['treasurer_signature'])
                        <img class="signature-image" src="{{ $signatories['treasurer_signature'] }}"
                            alt="Tanda tangan bendahara sekolah">
                    @endif
                </div>
                <p class="name">{{ $signatories['treasurer_name'] !== '' ? $signatories['treasurer_name'] : '_' }}
                </p>
                <p class="signature-line"></p>
                @if ($signatories['treasurer_nip'] !== '')
                    <p class="nip">NIP: {{ $signatories['treasurer_nip'] }}</p>
                @endif
            </td>
        </tr>
    </table>

</body>

</html>
