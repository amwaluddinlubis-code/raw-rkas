    @if ($isRpd)
        @php
            $rpdMonths = array_keys($rpd['month_names']);
            $rpdBaseWidths = [3, 6, 47, 4, 7, 6, 8];
            $rpdColspan = count($rpdBaseWidths) + count($rpdMonths);
            $rpdMonthCount = max(1, count($rpdMonths));
            $rpdMonthWidth = number_format((100 - array_sum($rpdBaseWidths)) / $rpdMonthCount, 2, '.', '');
            $rpdWidths = array_map(fn(int $width): string => $width . '%', $rpdBaseWidths);
            $rpdWidths = array_merge($rpdWidths, array_fill(0, count($rpdMonths), $rpdMonthWidth . '%'));
        @endphp
        <table class="report-table">
            @include('periodic-reports.partials.reports.colgroup', ['widths' => $rpdWidths])
            <thead>
                <tr>
                    <th rowspan="2">No</th>
                    <th rowspan="2">Kode<br>Program</th>
                    <th rowspan="2">Uraian</th>
                    <th colspan="3">Rincian Perhitungan</th>
                    <th rowspan="2">Jumlah</th>
                    <th colspan="{{ count($rpdMonths) }}">Bulan</th>
                </tr>
                <tr>
                    <th>Vol</th>
                    <th>Satuan</th>
                    <th>Tarif Harga</th>
                    @foreach ($rpd['month_names'] as $rpdMonthName)
                        <th>{{ $rpdMonthName }}</th>
                    @endforeach
                </tr>
                <tr>
                    @for ($i = 1; $i <= $rpdColspan; $i++)
                        <th>{{ $i }}</th>
                    @endfor
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="integer">I</td>
                    <td colspan="{{ $rpdColspan - 1 }}"><strong>PROGRAM SEKOLAH</strong></td>
                </tr>
                @foreach ($rpd['rows'] as $rpdRow)
                    <tr>
                        <td class="integer">{{ $rpdRow['no'] }}</td>
                        <td class="integer">{{ $rpdRow['code'] }}</td>
                        <td>
                            @if ($rpdRow['level'] === 'item')
                                {{ $rpdRow['label'] }}
                            @else
                                <strong>{{ $rpdRow['label'] }}</strong>
                            @endif
                        </td>
                        <td class="integer">
                            {{ $rpdRow['level'] === 'item' && $rpdRow['volume'] > 0 ? rtrim(rtrim(number_format($rpdRow['volume'], 2, ',', '.'), '0'), ',') : '' }}
                        </td>
                        <td>{{ $rpdRow['level'] === 'item' ? $rpdRow['unit'] : '' }}</td>
                        <td class="money">
                            {{ $rpdRow['level'] === 'item' && $rpdRow['unit_price'] > 0 ? $rupiah($rpdRow['unit_price']) : '' }}
                        </td>
                        <td class="money">
                            @if ($rpdRow['level'] === 'item')
                                {{ $rupiah($rpdRow['jumlah']) }}
                            @else
                                <strong>{{ $rupiah($rpdRow['jumlah']) }}</strong>
                            @endif
                        </td>
                        @foreach ($rpdMonths as $rpdMonth)
                            <td class="money">
                                @if ($rpdRow['level'] === 'item')
                                    {{ $rpdRow['months'][$rpdMonth] > 0 ? $rupiah($rpdRow['months'][$rpdMonth]) : '' }}
                                @else
                                    <strong>{{ $rpdRow['months'][$rpdMonth] > 0 ? $rupiah($rpdRow['months'][$rpdMonth]) : '' }}</strong>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                <tr>
                    <td colspan="6"><strong>Sub Total Penggunaan Dana I</strong></td>
                    <td class="money"><strong>{{ $rupiah($rpd['totals']['jumlah']) }}</strong></td>
                    @foreach ($rpdMonths as $rpdMonth)
                        <td class="money"><strong>{{ $rupiah($rpd['totals']['months'][$rpdMonth]) }}</strong></td>
                    @endforeach
                </tr>
                <tr>
                    <td class="integer">II</td>
                    <td colspan="{{ $rpdColspan - 1 }}"><strong>Penggunaan Dana Lainnya</strong></td>
                </tr>
                <tr>
                    <td class="integer"></td>
                    <td class="integer"></td>
                    <td>Belanja….</td>
                    <td class="integer"></td>
                    <td></td>
                    <td class="money"></td>
                    <td class="money">0</td>
                    @foreach ($rpdMonths as $rpdMonth)
                        <td class="money"></td>
                    @endforeach
                </tr>
                <tr>
                    <td colspan="6"><strong>Sub Total Penggunaan Dana II</strong></td>
                    <td class="money"><strong>0</strong></td>
                    @foreach ($rpdMonths as $rpdMonth)
                        <td class="money"><strong>0</strong></td>
                    @endforeach
                </tr>
                <tr>
                    <td colspan="6"><strong>JUMLAH TOTAL (I + II)</strong></td>
                    <td class="money"><strong>{{ $rupiah($rpd['totals']['jumlah']) }}</strong></td>
                    @foreach ($rpdMonths as $rpdMonth)
                        <td class="money"><strong>{{ $rupiah($rpd['totals']['months'][$rpdMonth]) }}</strong></td>
                    @endforeach
                </tr>
            </tbody>
        </table>
        @if (!$rpd['has_data'])
            <div class="note">
                Data anggaran RKAS belum tersedia — jalankan sinkronisasi ARKAS terlebih dahulu.
            </div>
        @endif
    @endif
