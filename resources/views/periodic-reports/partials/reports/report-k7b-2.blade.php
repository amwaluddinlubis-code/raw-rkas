    @if($isK7b)
    @php
        $k7bMetaWidths = ['55%', '45%'];
        $k7bTableWidths = ['60%', '20%', '20%'];
    @endphp
    <table class="meta-table page-break-avoid">
        @include('periodic-reports.partials.reports.colgroup', ['widths' => $k7bMetaWidths])
        <tr>
            <td>Jumlah Total Penerimaan (D)</td>
            <td>: Rp. {{ $rupiah($k7b['total_in']) }}</td>
        </tr>
        <tr>
            <td>Jumlah Total Pengeluaran (K)</td>
            <td>: Rp. {{ $rupiah($k7b['total_out']) }}</td>
        </tr>
        <tr>
            <td>Saldo Buku (A = D - K)</td>
            <td>: Rp. {{ $rupiah($k7b['book']) }}</td>
        </tr>
        <tr>
            <td>Saldo Kas (B)</td>
            <td>: Rp. {{ $rupiah($k7b['bank'] + $k7b['cash']) }}</td>
        </tr>
    </table>
    <div style="margin-top:12px;font-weight:700;">Saldo kas B terdiri dari :</div>
    <table class="report-table">
        @include('periodic-reports.partials.reports.colgroup', ['widths' => $k7bTableWidths])
        <thead>
            <tr>
                <th>Uraian</th>
                <th>Lembar / Keping</th>
                <th>Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach($k7b['paper'] as $paper)
                <tr>
                    <td>Lembaran uang kertas {{ $paper['label'] }}</td>
                    <td class="integer">...... Lembar</td>
                    <td class="money">Rp. ......</td>
                </tr>
            @endforeach
            <tr>
                <td><strong>Sub Jumlah (1)</strong></td>
                <td class="integer"></td>
                <td class="money">Rp. ......</td>
            </tr>
            @foreach($k7b['coins'] as $coin)
                <tr>
                    <td>Keping uang logam {{ $coin['label'] }}</td>
                    <td class="integer">...... Keping</td>
                    <td class="money">Rp. ......</td>
                </tr>
            @endforeach
            <tr>
                <td><strong>Sub Jumlah (2)</strong></td>
                <td class="integer"></td>
                <td class="money">Rp. ......</td>
            </tr>
            <tr>
                <td>Saldo Bank, Surat Berharga dll</td>
                <td class="integer"></td>
                <td class="money">Rp. {{ $rupiah($k7b['bank']) }}</td>
            </tr>
            <tr>
                <td><strong>Sub Jumlah (3)</strong></td>
                <td class="integer"></td>
                <td class="money"><strong>Rp. {{ $rupiah($k7b['bank']) }}</strong></td>
            </tr>
            <tr>
                <td><strong>Jumlah (1+2+3)</strong></td>
                <td class="integer"></td>
                <td class="money"><strong>Rp. {{ $rupiah($k7b['bank'] + $k7b['cash']) }}</strong></td>
            </tr>
            <tr>
                <td><strong>Perbedaan (A-B)</strong></td>
                <td class="integer"></td>
                <td class="money"><strong>Rp. {{ $rupiah($k7b['diff']) }}</strong></td>
            </tr>
        </tbody>
    </table>
    <table class="meta-table page-break-avoid">
        @include('periodic-reports.partials.reports.colgroup', ['widths' => $k7bMetaWidths])
        <tr>
            <td>Penjelasan Perbedaan</td>
            <td>: Nihil — cocok dengan Buku Kas Umum. Rincian pecahan (1)+(2) diisi manual saat opname fisik.</td>
        </tr>
        <tr>
            <td>Tanggal</td>
            <td>: {{ $k7b['closing_date'] }}</td>
        </tr>
    </table>
    @endif
