    @if($isK7c)
    @php
        $k7cWidths = ['70%', '30%'];
        $k7cMetaWidths = ['55%', '45%'];
    @endphp
    <div class="statement">
        <p>Pada hari ini {{ $k7c['weekday_date'] }}, yang bertanda tangan di bawah ini, kami Kepala Sekolah yang ditunjuk berdasarkan Surat Keputusan No. - (diisi manual), Nama : {{ $principalName }}, Jabatan : Kepala Sekolah, melakukan pemeriksaan kas kepada : Nama : {{ $treasurerName }}, Jabatan : Bendahara / Pemegang Kas, yang berdasarkan Surat Keputusan No. - (diisi manual) ditugaskan dalam pengurusan uang Bantuan Operasional Sekolah (BOS).</p>
        <p>Berdasarkan pemeriksaan kas serta bukti-bukti dalam pengurusan ini, kami menemui kenyataan sebagai berikut :</p>
    </div>
    <div style="margin-top:12px;font-weight:700;">Jumlah uang yang dihitung dihadapan bendahara / Pemegang Kas adalah :</div>
    <table class="report-table">
        @include('periodic-reports.partials.reports.colgroup', ['widths' => $k7cWidths])
        <thead>
            <tr>
                <th>Uraian</th>
                <th>Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>a. Uang kertas bank, uang logam</td>
                <td class="money">Rp. {{ $rupiah($k7c['cash']) }}</td>
            </tr>
            <tr>
                <td>b. Saldo Bank</td>
                <td class="money">Rp. {{ $rupiah($k7c['bank']) }}</td>
            </tr>
            <tr>
                <td>c. Surat Berharga dll</td>
                <td class="money">Rp. {{ $rupiah($k7c['securities']) }}</td>
            </tr>
            <tr>
                <td><strong>Jumlah</strong></td>
                <td class="money"><strong>Rp. {{ $rupiah($k7c['total']) }}</strong></td>
            </tr>
            <tr>
                <td>Saldo uang menurut Buku Kas Umum</td>
                <td class="money">Rp. {{ $rupiah($k7c['book']) }}</td>
            </tr>
            <tr>
                <td><strong>Perbedaan antara saldo kas dan saldo buku</strong></td>
                <td class="money"><strong>Rp. {{ $rupiah($k7c['diff']) }}</strong></td>
            </tr>
        </tbody>
    </table>
    <table class="meta-table page-break-avoid">
        @include('periodic-reports.partials.reports.colgroup', ['widths' => $k7cMetaWidths])
        <tr>
            <td>Tanggal</td>
            <td>: {{ $k7c['closing_date'] }}</td>
        </tr>
    </table>
    @endif
