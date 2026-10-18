    @if ($isK7)
        @php
            $k7cols = ['jumlah', 'rutin', 'pusat', 'provinsi', 'kab_kota', 'bantuan_lain', 'sumber_lainnya'];
            $k7money = function (array $amounts, string $col, bool $zeroDash) use ($rupiah) {
                $value = (float) ($amounts[$col] ?? 0);
                if ($value == 0.0) {
                    return $zeroDash ? '-' : '';
                }
                return $rupiah($value);
            };
        @endphp
        <table class="report-table k7-table">
        @php
            $k7Widths = ['60px', '30%'];
            foreach ($k7cols as $k7WidthCol) {
                $k7WidthTotal = 0.0;
                foreach (($k7['rows'] ?? []) as $k7WidthRow) {
                    $k7WidthTotal += (float) ($k7WidthRow['amounts'][$k7WidthCol] ?? 0);
                }
                $k7Widths[] = $k7WidthTotal != 0.0 ? '11%' : '5.5%';
            }
        @endphp
        <colgroup>
            @foreach ($k7Widths as $k7Width)
                <col style="width: {{ $k7Width }}">
            @endforeach
        </colgroup>

            <thead>
                <tr>
                    <th rowspan="3">No. Kode</th>
                    <th rowspan="3">Uraian Kegiatan</th>
                    <th rowspan="3">Jumlah</th>
                    <th colspan="6">Penggunaan Dana Per Sumber Dana</th>
                </tr>
                <tr>
                    <th rowspan="2">Rutin</th>
                    <th colspan="3">Bantuan Operasional Sekolah (BOS)</th>
                    <th rowspan="2">Bantuan Lain</th>
                    <th rowspan="2">Sumber Pendapatan Lainnya</th>
                </tr>
                <tr>
                    <th>Pusat</th>
                    <th>Provinsi</th>
                    <th>Kab/Kota</th>
                </tr>
                <tr>
                    <th class="integer">1</th>
                    <th class="integer">2</th>
                    <th class="integer">3</th>
                    <th class="integer">4</th>
                    <th class="integer">5</th>
                    <th class="integer">6</th>
                    <th class="integer">7</th>
                    <th class="integer">8</th>
                    <th class="integer">9</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($k7['rows'] as $k7row)
                    <tr class="k7-{{ $k7row['kind'] }}">
                        <td class="k7-code">{{ $k7row['code'] }}</td>
                        <td @if ($k7row['bold']) class="k7-bold" @endif>{{ $k7row['label'] }}</td>
                        @foreach ($k7cols as $k7col)
                            <td class="money @if ($k7row['bold']) k7-bold @endif">
                                {{ $k7money($k7row['amounts'], $k7col, $k7row['zero_dash']) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if ($k7['unmapped'] > 0)
            <div class="note">
                Belanja Rp. {{ $rupiah($k7['unmapped']) }} ({{ $k7['unmappedCount'] }} transaksi) tanpa kode kegiatan
                01–08 dan tidak masuk JUMLAH — cocokkan manual dengan pembukuan sebelum ditandatangani.
            </div>
        @endif

    @endif
