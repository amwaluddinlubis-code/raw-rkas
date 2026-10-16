    @if($isK2)
    <table class="report-table">
        <thead>
            <tr>
                <th rowspan="2">No</th>
                <th rowspan="2">Uraian</th>
                <th rowspan="2">Jumlah<br>(dalam Rp)</th>
                <th colspan="4">Triwulan</th>
            </tr>
            <tr>
                <th>I</th>
                <th>II</th>
                <th>III</th>
                <th>IV</th>
            </tr>
            <tr>
                <th>1</th>
                <th>2</th>
                <th>3</th>
                <th>4</th>
                <th>5</th>
                <th>6</th>
                <th>7</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="integer">I</td>
                <td colspan="6"><strong>Program Sekolah</strong></td>
            </tr>
            @foreach($k2['programs'] as $prog)
            <tr>
                <td class="integer">{{ $prog['no'] }}</td>
                <td>{{ $prog['label'] }}</td>
                <td class="money">{{ $rupiah($prog['jumlah']) }}</td>
                <td class="money">{{ $rupiah($prog['tw'][1]) }}</td>
                <td class="money">{{ $rupiah($prog['tw'][2]) }}</td>
                <td class="money">{{ $rupiah($prog['tw'][3]) }}</td>
                <td class="money">{{ $rupiah($prog['tw'][4]) }}</td>
            </tr>
            @endforeach
            <tr>
                <td class="integer">II</td>
                <td colspan="6"><strong>Belanja Lainnya</strong></td>
            </tr>
            <tr>
                <td class="integer">2.1</td>
                <td></td>
                <td class="money"></td>
                <td class="money"></td>
                <td class="money"></td>
                <td class="money"></td>
                <td class="money"></td>
            </tr>
            <tr>
                <td class="integer"></td>
                <td><strong>JUMLAH</strong></td>
                <td class="money"><strong>{{ $rupiah($k2['totals']['jumlah']) }}</strong></td>
                <td class="money"><strong>{{ $rupiah($k2['totals']['tw'][1]) }}</strong></td>
                <td class="money"><strong>{{ $rupiah($k2['totals']['tw'][2]) }}</strong></td>
                <td class="money"><strong>{{ $rupiah($k2['totals']['tw'][3]) }}</strong></td>
                <td class="money"><strong>{{ $rupiah($k2['totals']['tw'][4]) }}</strong></td>
            </tr>
        </tbody>
    </table>
    @if(! $k2['has_data'])
    <div class="note">
        Data anggaran RKAS belum tersedia — jalankan sinkronisasi ARKAS terlebih dahulu.
    </div>
    @endif
    @endif
