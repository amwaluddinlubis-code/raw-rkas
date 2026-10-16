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
    {{-- Blok generik hanya bila belum ada blok khusus (BKU/pajak, rekap,
        A-1, K7/K7B/K7C/SPTJM, atau BPK Dinas yang membawa bloknya sendiri)
        agar tanda tangan tidak tampil ganda. --}}
    @elseif($isA1)
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
                {{ $bosA1Title['place'] ?? '-' }}, {{ $bosA1Title['signed_date'] ?? '-' }}<br>
                Pemegang Kas Sekolah
                <div class="signature-space"></div>
                <div class="signature-name">{{ $treasurerName }}</div>
                @if($treasurerNip)
                    <div>NIP. {{ $treasurerNip }}</div>
                @endif
            </td>
        </tr>
    </table>
    @elseif($isK7b)
    <table class="signature-table">
        <tr>
            <td>
                Yang diperiksa,<br>
                Bendahara / Pemegang Kas
                <div class="signature-space"></div>
                <div class="signature-name">{{ $treasurerName }}</div>
                @if($treasurerNip)
                    <div>NIP. {{ $treasurerNip }}</div>
                @endif
            </td>
            <td>
                {{ $k7b['closing_date'] }}<br>
                Yang Memeriksa, Kepala Sekolah
                <div class="signature-space"></div>
                <div class="signature-name">{{ $principalName }}</div>
                @if($principalNip)
                    <div>NIP. {{ $principalNip }}</div>
                @endif
            </td>
        </tr>
    </table>
    @elseif($isK7c)
    <table class="signature-table">
        <tr>
            <td>
                Bendahara / Pemegang Kas
                <div class="signature-space"></div>
                <div class="signature-name">{{ $treasurerName }}</div>
                @if($treasurerNip)
                    <div>NIP. {{ $treasurerNip }}</div>
                @endif
            </td>
            <td>
                {{ $k7c['closing_date'] }}<br>
                Kepala Sekolah
                <div class="signature-space"></div>
                <div class="signature-name">{{ $principalName }}</div>
                @if($principalNip)
                    <div>NIP. {{ $principalNip }}</div>
                @endif
            </td>
        </tr>
    </table>
    @elseif($isK2)
    <table class="signature-table">
        <tr>
            <td style="text-align:left;border:none;padding-bottom:0;">
                Diketahui Oleh :
            </td>
            <td style="text-align:left;border:none;padding-bottom:0;">
                Diketahui Oleh :
            </td>
            <td style="text-align:left;border:none;padding-bottom:0;">
                {{ $k2Title['place'] }}, {{ $k2Title['signed_date'] }}
            </td>
        </tr>
        <tr>
            <td>
                Komite Sekolah
                <div class="signature-space"></div>
                <div class="signature-name">{{ $committeeName }}</div>
            </td>
            <td>
                Kepala Sekolah
                <div class="signature-space"></div>
                <div class="signature-name">{{ $principalName }}</div>
                @if($principalNip)
                    <div>NIP. {{ $principalNip }}</div>
                @endif
            </td>
            <td>
                Bendahara
                <div class="signature-space"></div>
                <div class="signature-name">{{ $treasurerName }}</div>
                @if($treasurerNip)
                    <div>NIP. {{ $treasurerNip }}</div>
                @endif
            </td>
        </tr>
    </table>
    @elseif($isRpd)
    <table class="signature-table">
        <tr>
            <td style="text-align:left;border:none;padding-bottom:0;">
                Mengetahui,
            </td>
            <td style="text-align:left;border:none;padding-bottom:0;">
                {{ $rpdTitle['place'] }}, {{ $rpdTitle['signed_date'] }}
            </td>
        </tr>
        <tr>
            <td>
                KEPALA {{ mb_strtoupper($school->name) }}
                <div class="signature-space"></div>
                <div class="signature-name">{{ $principalName }}</div>
                @if($principalNip)
                    <div>NIP. {{ $principalNip }}</div>
                @endif
            </td>
            <td>
                BENDAHARA
                <div class="signature-space"></div>
                <div class="signature-name">{{ $treasurerName }}</div>
                @if($treasurerNip)
                    <div>NIP. {{ $treasurerNip }}</div>
                @endif
            </td>
        </tr>
    </table>
    @elseif($isK7)
    <table class="signature-table k7-sign">
        <tr>
            <td>
                Komite Sekolah
                <div class="signature-space"></div>
                <div class="signature-name">{{ $committeeName }}</div>
            </td>
            <td>
                Mengetahui<br>
                Kepala Sekolah
                <div class="signature-space"></div>
                <div class="signature-name">{{ $principalName }}</div>
                @if($principalNip)
                    <div>NIP. {{ $principalNip }}</div>
                @endif
            </td>
            <td>
                {{ $k7['place'] }}, {{ $k7['signed_date'] }}<br>
                Bendahara
                <div class="signature-space"></div>
                <div class="signature-name">{{ $treasurerName }}</div>
                @if($treasurerNip)
                    <div>NIP. {{ $treasurerNip }}</div>
                @endif
            </td>
        </tr>
    </table>
    @elseif($isSptjm)
    <table class="signature-table">
        <tr>
            <td>
                {{ $sptjm['place'] }}, {{ $sptjm['signed_date'] }}<br>
                Kepala {{ $school->name }}
                <div class="signature-space"></div>
                <div>Materai 10.000</div>
                <div class="signature-name">{{ $principalName }}</div>
                @if($principalNip)
                    <div>NIP. {{ $principalNip }}</div>
                @endif
            </td>
        </tr>
    </table>
    @elseif(! $isLedger && $presentation !== 'tax' && ! $isBpk && ! $isK7b && ! $isK7c && ! $isK7 && ! $isK2 && ! $isRpd && ! $isSptjm)
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
