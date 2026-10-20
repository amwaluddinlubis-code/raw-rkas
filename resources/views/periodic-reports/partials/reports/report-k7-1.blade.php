    @if ($isK7)
        @php
            $k7District = trim((string) ($school->district ?? ''));
            if ($k7District !== '' && !str_starts_with($k7District, 'Kec.')) {
                $k7District = 'Kec. ' . $k7District;
            }
            $k7Desa = trim((string) ($school->desa ?? ''));
            $k7DesaKec = trim($k7Desa . ($k7District !== '' ? ' / ' . $k7District : ''), ' /') ?: '-';
        @endphp
        <div class="k7-title">
            <div>{{ $k7['title']['heading'] }}</div>
            <div>{{ $k7['title']['year_line'] }}</div>
            <div>{{ $k7['title']['range_line'] }}</div>
        </div>
        <div class="k7-ident page-break-avoid">
            <table class="k7-ident-left">
                @include('periodic-reports.partials.reports.colgroup', ['widths' => ['30%', '70%']])
                <tr>
                    <td>NPSN</td>
                    <td>: {{ $school->npsn ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Nama Sekolah</td>
                    <td>: {{ $school->name }}</td>
                </tr>
                <tr>
                    <td>Desa/Kecamatan</td>
                    <td>: {{ $k7DesaKec }}</td>
                </tr>
                <tr>
                    <td>Kabupaten</td>
                    <td>: Kab. {{ $school->regency ?: '-' }}</td>
                </tr>
                <tr>
                    <td>Provinsi</td>
                    <td>: Prov. {{ $school->province ?: '-' }}</td>
                </tr>
            </table>
            <table class="k7-formbox">
                @include('periodic-reports.partials.reports.colgroup', ['widths' => ['100%']])
                <tr>
                    <td><strong>Formulir Bos-K7</strong></td>
                </tr>
                <tr>
                    <td>Diisi Oleh Sekolah</td>
                </tr>
                <tr>
                    <td>Dikirim Ke Tim Manajemen Bos Kab/Kota</td>
                </tr>
            </table>
        </div>
    @endif
