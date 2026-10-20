    @if ($isA1)
        @php
            $a1Money = fn($value) => 'Rp ' . ($value > 0 ? $rupiah($value) : '-');
            $a1Widths = ['5%', '30%', '11%', '11%', '11%', '11%', '10%', '11%'];
        @endphp
        <table class="report-table">
            @include('periodic-reports.partials.reports.colgroup', ['widths' => $a1Widths])
            <thead>
                <tr>
                    <th rowspan="3">NO</th>
                    <th rowspan="3">PROGRAM/KEGIATAN</th>
                    <th colspan="5">JENIS BELANJA</th>
                    <th rowspan="3">TOTAL</th>
                </tr>
                <tr>
                    <th rowspan="2">BELANJA PEGAWAI</th>
                    <th rowspan="2">BARANG DAN JASA</th>
                    <th colspan="3">BELANJA MODAL</th>
                </tr>
                <tr>
                    <th>PERALATAN DAN MESIN</th>
                    <th>ASET TETAP LAINNYA BOS</th>
                    <th>JUMLAH</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bosA1['rows'] as $a1row)
                    <tr>
                        <td class="integer text-center">{{ $a1row['no'] }}</td>
                        <td>{{ $a1row['program'] }}</td>
                        <td class="money">{{ $a1Money($a1row['pegawai']) }}</td>
                        <td class="money">{{ $a1Money($a1row['barang_jasa']) }}</td>
                        <td class="money">{{ $a1Money($a1row['modal_mesin']) }}</td>
                        <td class="money">{{ $a1Money($a1row['modal_aset']) }}</td>
                        <td class="money">{{ $a1Money($a1row['modal_jumlah']) }}</td>
                        <td class="money"><strong>{{ $a1Money($a1row['total']) }}</strong></td>
                    </tr>
                @endforeach
                <tr>
                    <td class="integer"></td>
                    <td><strong>TOTAL</strong></td>
                    <td class="money"><strong>{{ $a1Money($bosA1['totals']['pegawai']) }}</strong></td>
                    <td class="money"><strong>{{ $a1Money($bosA1['totals']['barang_jasa']) }}</strong></td>
                    <td class="money"><strong>{{ $a1Money($bosA1['totals']['modal_mesin']) }}</strong></td>
                    <td class="money"><strong>{{ $a1Money($bosA1['totals']['modal_aset']) }}</strong></td>
                    <td class="money"><strong>{{ $a1Money($bosA1['totals']['modal_jumlah']) }}</strong></td>
                    <td class="money"><strong>{{ $a1Money($bosA1['totals']['grand']) }}</strong></td>
                </tr>
            </tbody>
        </table>
    @endif
