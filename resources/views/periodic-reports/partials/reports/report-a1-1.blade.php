    @if ($isA1)
        @php
            $a1District = trim((string) ($school->district ?? ''));
            if ($a1District !== '' && !str_starts_with($a1District, 'Kec.')) {
                $a1District = 'Kec. ' . $a1District;
            }
            $a1Desa = trim((string) ($school->desa ?? ''));
            $a1DesaKec = trim($a1Desa . ($a1District !== '' ? ' / ' . $a1District : ''), ' /') ?: '-';
            $a1IdentityWidths = ['15%', '85%'];
        @endphp
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;">
            <div style="flex:1;text-align:center;">
                <h1 class="doc-title">REKAPITULASI REALISASI PENGGUNAAN DANA BOS REGULER</h1>
                <div class="doc-subtitle">PERIODE TANGGAL : {{ $bosA1Title['range'] ?? '-' }}</div>
            </div>
            <table style="border-collapse:collapse;font-size:11px;white-space:nowrap;">
                <tr>
                    <td style="border:1px solid #111827;padding:2px 10px;text-align:center;">Format BOS A-1</td>
                </tr>
                <tr>
                    <td style="border:1px solid #111827;padding:2px 10px;text-align:center;">Diisi oleh sekolah</td>
                </tr>
                <tr>
                    <td style="border:1px solid #111827;padding:2px 10px;text-align:center;">Dikirim ke TIM Menajemen BOS
                        Kab/Kota</td>
                </tr>
            </table>
        </div>
        <table class="bku-identity-block page-break-avoid">
            @include('periodic-reports.partials.reports.colgroup', ['widths' => $a1IdentityWidths])
            <tr>
                <td>NPSN</td>
                <td>: {{ $school->npsn ?? '-' }}</td>
            </tr>
            <tr>
                <td>Nama Sekolah</td>
                <td>: {{ $school->name }}</td>
            </tr>
            <tr>
                <td>Desa / Kecamatan</td>
                <td>: {{ $a1DesaKec }}</td>
            </tr>
            <tr>
                <td>Kabupaten/Kota</td>
                <td>: {{ $school->regency ?: '-' }}</td>
            </tr>
            <tr>
                <td>Provinsi</td>
                <td>: {{ $school->province ?: '-' }}</td>
            </tr>
            <tr>
                <td>Sumber Dana</td>
                <td>: {{ $fundSource }}</td>
            </tr>
        </table>
    @endif
