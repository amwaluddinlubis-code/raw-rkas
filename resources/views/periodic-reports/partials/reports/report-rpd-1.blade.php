    @if($isRpd)
    @php
        $rpdDesaKec = trim(trim((string) ($school->desa ?? '')).' / '.trim((string) ($school->district ?? '')), ' /') ?: '-';
    @endphp
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;">
        <div style="flex:1;text-align:center;">
            <h1 class="doc-title">RINCIAN PENGGUNAAN DANA BOS (RPD BOS)</h1>
            <div class="doc-subtitle">{{ $rpd['month_range'] }}</div>
        </div>
        <table style="border-collapse:collapse;font-size:11px;white-space:nowrap;">
            <tr><td style="border:1px solid #111827;padding:2px 10px;text-align:center;"><strong>RPD</strong></td></tr>
        </table>
    </div>
    <table class="bku-identity-block page-break-avoid">
        <tr>
            <td>Nama Sekolah</td>
            <td>: {{ $school->name }}</td>
        </tr>
        <tr>
            <td>Desa / Kec</td>
            <td>: {{ $rpdDesaKec }}</td>
        </tr>
        <tr>
            <td>Kab / Kota</td>
            <td>: {{ $school->regency ?: '-' }}</td>
        </tr>
        <tr>
            <td>Provinsi</td>
            <td>: {{ $school->province ?: '-' }}</td>
        </tr>
        <tr>
            <td>TRIWULAN</td>
            <td>: {{ $rpd['quarter_label'] }}</td>
        </tr>
    </table>
    @endif
