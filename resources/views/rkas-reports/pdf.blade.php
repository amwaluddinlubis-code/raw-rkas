<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $year }}</title>
    <style>
        @page {
            size: A4 landscape;
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
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #111827;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8px;
            line-height: 1.3;
        }
        h1 { font-size: 13px; text-align: center; margin: 0; }
        h2 { font-size: 11px; text-align: center; margin: 2px 0 0; }
        .rev-line { text-align: center; font-size: 9px; margin: 2px 0 8px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #111827; padding: 2px 4px; vertical-align: top; }
        th { background: #f3f4f6; text-align: center; font-weight: bold; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .ident td { border: 0; padding: 1px 4px; }
        .lvl-program { background: #f8d7da; font-weight: bold; }
        .lvl-subprogram { background: #d4edda; font-weight: bold; }
        .lvl-activity { background: #fff3cd; font-weight: bold; }
        .section-title { font-size: 10px; font-weight: bold; margin: 10px 0 4px; }
        .legend { font-size: 7px; font-style: italic; margin-top: 2px; }
        .ttd { margin-top: 18px; width: 100%; }
        .ttd td { border: 0; text-align: center; vertical-align: top; }
        .ttd .name { font-weight: bold; margin-top: 52px; }
        .place { text-align: right; margin-bottom: 4px; }
    </style>
</head>
<body>
@php($uang = fn($v) => number_format((float) $v, 0, ',', '.'))
@php($vol = fn($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ','))
@php($fundCols = ['BOSP REGULER', 'BOSP DAERAH', 'AFIRMASI', 'KINERJA', 'SiLPA', 'BOSP LAINNYA'])

<h1>{{ $title }}</h1>
<h2>TAHUN ANGGARAN : {{ $year }}</h2>
<p class="rev-line">{{ $revision_label }}{{ $revision_date !== '-' ? ' · ' . $revision_date : '' }}</p>

<table class="ident">
    <tr><td style="width: 90px;">NPSN</td><td style="width: 12px;">:</td><td>{{ $school['npsn'] }}</td></tr>
    <tr><td>Nama Sekolah</td><td>:</td><td>{{ $school['name'] }}</td></tr>
    <tr><td>Alamat</td><td>:</td><td>{{ $school['address'] }}</td></tr>
    <tr><td>Kabupaten</td><td>:</td><td>{{ $school['regency'] }}</td></tr>
    <tr><td>Provinsi</td><td>:</td><td>{{ $school['province'] }}</td></tr>
    @if ($scope === 'triwulan')
        <tr><td>Triwulan</td><td>:</td><td>{{ $scope_label }}</td></tr>
    @elseif ($scope === 'tahap')
        <tr><td>Tahap</td><td>:</td><td>{{ $scope_label }}</td></tr>
    @elseif ($scope === 'bulanan')
        <tr><td>Bulan</td><td>:</td><td>{{ $scope_label }}{{ $year }}</td></tr>
    @endif
    <tr><td>Sumber Dana</td><td>:</td><td>{{ $fund_name }}</td></tr>
</table>

<p class="section-title">A. PENERIMAAN</p>
<p style="margin: 0 0 4px; font-weight: bold;">Sumber Dana :</p>
<table style="width: 60%;">
    <tr><th>No. Kode</th><th>Penerimaan</th><th>Jumlah</th></tr>
    @foreach ($penerimaan as $row)
        <tr>
            <td class="center">{{ $row['code'] }}</td>
            <td>{{ $row['label'] }}{{ $row['active'] ? '' : ' **' }}</td>
            <td class="num">{{ $uang($row['amount']) }}</td>
        </tr>
    @endforeach
    <tr><td colspan="2" class="bold">Total Penerimaan</td><td class="num bold">{{ $uang($totals['jumlah']) }}</td></tr>
</table>
<p class="legend">* belum pengesahan, ** belum aktivasi anggaran, ~ penerimaan dan belanja tidak sesuai</p>

<p class="section-title">B. BELANJA</p>

@if ($scope === 'tahunan')
    <table>
        <tr>
            <th rowspan="2">No. Urut</th><th rowspan="2">Kode Rekening</th><th rowspan="2">Kode Kegiatan</th><th rowspan="2">Uraian Kegiatan</th><th rowspan="2">Jumlah</th>
            <th colspan="12">Sumber Dana dan Alokasi Anggaran</th>
        </tr>
        <tr>
            @foreach ($fundCols as $fundCol)
                <th colspan="2">{{ $fundCol }}</th>
            @endforeach
        </tr>
        <tr>
            <th></th><th></th><th></th><th></th><th></th>
            @foreach ($fundCols as $fundCol)
                <th>Belanja Operasi</th><th>Belanja Modal</th>
            @endforeach
        </tr>
        @foreach ($lines as $line)
            <tr class="{{ $line['level'] === 'program' ? 'lvl-program' : ($line['level'] === 'subprogram' ? 'lvl-subprogram' : ($line['level'] === 'activity' ? 'lvl-activity' : '')) }}">
                <td class="center">{{ $line['no'] }}</td>
                <td>{{ $line['level'] === 'item' || $line['level'] === 'account' ? $line['account_code'] : '' }}</td>
                <td>{{ $line['activity_code'] }}</td>
                <td class="{{ in_array($line['level'], ['program', 'subprogram', 'activity']) ? 'bold' : '' }}">{{ $line['name'] }}</td>
                <td class="num {{ in_array($line['level'], ['program', 'subprogram', 'activity']) ? 'bold' : '' }}">{{ $uang($line['jumlah']) }}</td>
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
    <table>
        <tr>
            <th rowspan="2">No. Urut</th><th rowspan="2">Kode Rekening</th><th rowspan="2">Kode Program</th><th rowspan="2">Uraian</th>
            <th colspan="3">Rincian Perhitungan</th><th rowspan="2">Jumlah</th>
            @if ($scope === 'triwulan')
                <th colspan="4">Triwulan</th>
            @elseif ($scope === 'tahap')
                <th colspan="2">Tahap</th>
            @endif
        </tr>
        <tr>
            <th>Volume</th><th>Satuan</th><th>Tarif Harga</th>
            @if ($scope === 'triwulan')
                <th>1</th><th>2</th><th>3</th><th>4</th>
            @elseif ($scope === 'tahap')
                <th>1</th><th>2</th>
            @endif
        </tr>
        @foreach ($lines as $line)
            <tr class="{{ $line['level'] === 'program' ? 'lvl-program' : ($line['level'] === 'subprogram' ? 'lvl-subprogram' : ($line['level'] === 'activity' ? 'lvl-activity' : '')) }}">
                <td class="center">{{ $line['no'] }}</td>
                <td>{{ $line['level'] === 'item' || $line['level'] === 'account' ? $line['account_code'] : '' }}</td>
                <td>{{ $line['activity_code'] }}</td>
                <td class="{{ in_array($line['level'], ['program', 'subprogram', 'activity']) ? 'bold' : '' }}">{{ $line['name'] }}</td>
                <td class="num">{{ $line['level'] === 'item' ? $vol($line['volume']) : '' }}</td>
                <td>{{ $line['level'] === 'item' ? $line['unit'] : '' }}</td>
                <td class="num">{{ $line['level'] === 'item' ? $uang($line['unit_price']) : '' }}</td>
                <td class="num {{ in_array($line['level'], ['program', 'subprogram', 'activity']) ? 'bold' : '' }}">{{ $uang($scope === 'bulanan' ? $line['scoped'] : $line['jumlah']) }}</td>
                @if ($scope === 'triwulan')
                    @for ($q = 1; $q <= 4; $q++)
                        <td class="num">{{ $uang($line['tw'][$q]) }}</td>
                    @endfor
                @elseif ($scope === 'tahap')
                    <td class="num">{{ $uang($line['tahap'][0]) }}</td>
                    <td class="num">{{ $uang($line['tahap'][1]) }}</td>
                @endif
            </tr>
        @endforeach
        <tr>
            <td colspan="7" class="center bold">Jumlah</td>
            <td class="num bold">{{ $uang($scope === 'bulanan' ? $totals['scoped'] : $totals['jumlah']) }}</td>
            @if ($scope === 'triwulan')
                @for ($q = 1; $q <= 4; $q++)
                    <td class="num bold">{{ $uang($totals['tw'][$q]) }}</td>
                @endfor
            @elseif ($scope === 'tahap')
                <td class="num bold">{{ $uang($totals['tahap'][0]) }}</td>
                <td class="num bold">{{ $uang($totals['tahap'][1]) }}</td>
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
        <td><p class="name">&nbsp;</p><p>___________________________</p></td>
        <td><p class="name">{{ $signatories['principal_name'] !== '' ? $signatories['principal_name'] : '_' }}</p><p>___________________________</p>@if ($signatories['principal_nip'] !== '')<p>NIP: {{ $signatories['principal_nip'] }}</p>@endif</td>
        <td><p class="name">{{ $signatories['treasurer_name'] !== '' ? $signatories['treasurer_name'] : '_' }}</p><p>___________________________</p>@if ($signatories['treasurer_nip'] !== '')<p>NIP: {{ $signatories['treasurer_nip'] }}</p>@endif</td>
    </tr>
</table>
</body>
</html>
