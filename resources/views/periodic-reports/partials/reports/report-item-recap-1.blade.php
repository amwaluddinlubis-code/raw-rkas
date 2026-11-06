@if ($isItemRecap && isset($itemRecap))
    @php
        /*
        | Laporan Barang Habis Pakai (BHP) & Belanja Modal / Aset (BMA) model
        | ekspor ARKAS. Lebar kolom dapat dikustom melalui
        | config('spj.periodic_report_column_widths.item_recap'); kunci yang
        | tidak diisi memakai nilai default di bawah.
        */
        $itemRecapDefaultWidths = [
            'identity' => ['85%', '15%'],
            'table' => ['8%', '8%', '12%', '7%', '9%', '25%', '6%', '6%', '8%', '11%'],
            'signature' => ['50%', '50%'],
        ];
        $itemRecapWidths = array_replace(
            $itemRecapDefaultWidths,
            (array) config('spj.periodic_report_column_widths.item_recap', []),
        );
        $itemRecapIdentityWidths = (array) $itemRecapWidths['identity'];
        $itemRecapTableWidths = (array) $itemRecapWidths['table'];
        $itemRecapSignatureWidths = (array) $itemRecapWidths['signature'];
    @endphp

    <h1 class="doc-title">{{ $itemRecap['title'] }}</h1>
    <div class="doc-subtitle">{{ $itemRecap['periodLine'] }}</div>

    <table class="bku-identity-block page-break-avoid">
        @include('periodic-reports.partials.reports.colgroup', ['widths' => $itemRecapIdentityWidths])
        <tr>
            <td>
                <table class="bku-identity">
                    @include('periodic-reports.partials.reports.colgroup', ['widths' => ['30%', '70%']])
                    <tr>
                        <td>NPSN</td>
                        <td>: {{ $school->npsn ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td>Nama Sekolah</td>
                        <td>: {{ $school->name }}</td>
                    </tr>
                    <tr>
                        <td>Desa/Kecamatan</td>
                        <td>: {{ $itemRecap['villageDistrict'] }}</td>
                    </tr>
                    <tr>
                        <td>Kabupaten / Kota</td>
                        <td>: {{ $school->regency ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td>Provinsi</td>
                        <td>: {{ $school->province ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td>Sumber Dana</td>
                        <td>: {{ $fundSource }}</td>
                    </tr>
                </table>
            </td>
            <td style="text-align: right; vertical-align: top;"><strong>{{ $itemRecap['tag'] }}</strong></td>
        </tr>
    </table>

    <table class="report-table">
        @include('periodic-reports.partials.reports.colgroup', ['widths' => $itemRecapTableWidths])
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Kode Kegiatan</th>
                <th>Kode Rekening</th>
                <th>No. Bukti</th>
                <th>ID Barang</th>
                <th>Uraian Barang</th>
                <th>Jumlah Barang</th>
                <th>Satuan</th>
                <th>Harga Satuan</th>
                <th>Realisasi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['activity'] }}</td>
                    <td>{{ $row['account'] }}</td>
                    <td>{{ $row['evidence'] }}</td>
                    <td>{{ $row['item_code'] }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td class="integer">{{ $row['quantity'] }}</td>
                    <td>{{ $row['unit'] }}</td>
                    <td class="money">{{ $rupiah($row['unit_price']) }}</td>
                    <td class="money">{{ $rupiah($row['amount']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="empty-row">Tidak ada realisasi pada periode ini.</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="9" style="text-align: right;"><strong>Jumlah</strong></td>
                <td class="money"><strong>Rp. {{ $rupiah($itemRecap['total']) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <table class="signature-table">
        @include('periodic-reports.partials.reports.colgroup', ['widths' => $itemRecapSignatureWidths])
        <tr>
            <td>
                Menyetujui,<br>
                Kepala Sekolah
                <div class="signature-space"></div>
                <div class="signature-name">{{ $principalName }}</div>
                @if ($principalNip)
                    <div>NIP. {{ $principalNip }}</div>
                @endif
            </td>
            <td>
                @if ($itemRecap['signedPlace'])
                    {{ $itemRecap['signedPlace'] }},
                @endif{{ $itemRecap['signedDate'] }}<br>
                Bendahara,
                <div class="signature-space"></div>
                <div class="signature-name">{{ $treasurerName }}</div>
                @if ($treasurerNip)
                    <div>NIP. {{ $treasurerNip }}</div>
                @endif
            </td>
        </tr>
    </table>
@endif
