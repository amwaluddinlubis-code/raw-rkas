    @if ($isK2)
        @php
            $k2Semester = ($k2['columns'] ?? 'quarter') === 'semester';
            $k2Widths = $k2Semester
                ? ['6%', '34%', '30%', '15%', '15%']
                : ['6%', '34%', '16%', '11%', '11%', '11%', '11%'];
        @endphp
        <table class="report-table">
            @include('periodic-reports.partials.reports.colgroup', ['widths' => $k2Widths])
            <thead>
                <tr>
                    <th rowspan="2" class="text-center">No</th>
                    <th rowspan="2">Uraian</th>
                    <th rowspan="2">Jumlah<br>(dalam Rp)</th>
                    <th colspan="{{ $k2Semester ? 2 : 4 }}">{{ $k2['group_label'] }}</th>
                </tr>
                <tr>
                    @if ($k2Semester)
                        <th>I</th>
                        <th>II</th>
                    @else
                        <th>I</th>
                        <th>II</th>
                        <th>III</th>
                        <th>IV</th>
                    @endif
                </tr>
                <tr>
                    <th>1</th>
                    <th>2</th>
                    <th>3</th>
                    @foreach ($k2Semester ? [4, 5] : [4, 5, 6, 7] as $k2Column)
                        <th>{{ $k2Column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="integer text-center">I</td>
                    <td colspan="{{ $k2Semester ? 4 : 6 }}"><strong>Program Sekolah</strong></td>
                </tr>
                @foreach ($k2['programs'] as $prog)
                    <tr>
                        <td class="integer text-center">{{ $prog['no'] }}</td>
                        <td> - {{ $prog['label'] }}</td>
                        <td class="money">{{ $rupiah($prog['jumlah']) }}</td>
                        @if ($k2Semester)
                            <td class="money">{{ $rupiah($prog['semester'][1]) }}</td>
                            <td class="money">{{ $rupiah($prog['semester'][2]) }}</td>
                        @else
                            <td class="money">{{ $rupiah($prog['tw'][1]) }}</td>
                            <td class="money">{{ $rupiah($prog['tw'][2]) }}</td>
                            <td class="money">{{ $rupiah($prog['tw'][3]) }}</td>
                            <td class="money">{{ $rupiah($prog['tw'][4]) }}</td>
                        @endif
                    </tr>
                @endforeach
                <tr>
                    <td class="integer"></td>
                    <td><strong>JUMLAH</strong></td>
                    <td class="money"><strong>{{ $rupiah($k2['totals']['jumlah']) }}</strong></td>
                    @if ($k2Semester)
                        <td class="money"><strong>{{ $rupiah($k2['totals']['semester'][1]) }}</strong></td>
                        <td class="money"><strong>{{ $rupiah($k2['totals']['semester'][2]) }}</strong></td>
                    @else
                        <td class="money"><strong>{{ $rupiah($k2['totals']['tw'][1]) }}</strong></td>
                        <td class="money"><strong>{{ $rupiah($k2['totals']['tw'][2]) }}</strong></td>
                        <td class="money"><strong>{{ $rupiah($k2['totals']['tw'][3]) }}</strong></td>
                        <td class="money"><strong>{{ $rupiah($k2['totals']['tw'][4]) }}</strong></td>
                    @endif
                </tr>
            </tbody>
        </table>
        @if (!$k2['has_data'])
            <div class="note">
                Data anggaran RKAS belum tersedia — jalankan sinkronisasi ARKAS terlebih dahulu.
            </div>
        @endif
    @endif
