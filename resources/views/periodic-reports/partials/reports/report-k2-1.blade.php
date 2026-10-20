    @if ($isK2)
        @php
            $k2DesaKec =
                trim(trim((string) ($school->desa ?? '')) . ' / ' . trim((string) ($school->district ?? '')), ' /') ?:
                '-';
            $k2IdentityWidths = ['15%', '85a%'];
        @endphp
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;">
            <div style="flex:1;text-align:center;">
                <h1 class="doc-title">RENCANA KEGIATAN DAN ANGGARAN SEKOLAH ( RKAS )</h1>
                <div class="doc-subtitle">TAHUN ANGGARAN {{ $year->year }}</div>
            </div>
            <table style="border-collapse:collapse;font-size:11px;white-space:nowrap;">
                <tr>
                    <td style="border:1px solid #111827;padding:2px 10px;text-align:center;">Format BOS K-2</td>
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
            @include('periodic-reports.partials.reports.colgroup', ['widths' => $k2IdentityWidths])
            <tr>
                <td>Nama Sekolah</td>
                <td>: {{ $school->name }}</td>
            </tr>
            <tr>
                <td>Desa/Kecamatan</td>
                <td>: {{ $k2DesaKec }}</td>
            </tr>
            <tr>
                <td>Kabupaten</td>
                <td>: {{ $school->regency ?: '-' }}</td>
            </tr>
            <tr>
                <td>Provinsi</td>
                <td>: {{ $school->province ?: '-' }}</td>
            </tr>
            <tr>
                <td>Triwulan / Bulan</td>
                <td>: {{ $k2['quarter_label'] }} / {{ $k2['month_range'] }}</td>
            </tr>
            <tr>
                <td>Sumber Dana</td>
                <td>: {{ $fundSource }}</td>
            </tr>
        </table>
    @endif
