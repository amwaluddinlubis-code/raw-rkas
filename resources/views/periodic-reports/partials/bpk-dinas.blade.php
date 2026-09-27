        @php($bpk = $bpkData)
        <?php $bpkZero = fn ($value) => (float) $value > 0 ? $rupiah($value) : '-'; ?>
        <?php $bpkBlank = fn ($value) => (float) $value > 0 ? $rupiah($value) : ''; ?>
        <h1 class="doc-title">DINAS PENDIDIKAN DAN KEBUDAYAAN<br>PEMERINTAH KABUPATEN {{ mb_strtoupper((string) $school->regency) }}<br>{{ $bpkTitle['title'] ?? 'REKAP REALISASI PENGGUNAAN DANA BOS' }}</h1>
        <table class="meta-table page-break-avoid">
            <tr><td colspan="2"><strong>DATA SEKOLAH</strong></td></tr>
            <tr><td>1&ensp;Nama Sekolah</td><td>{{ $bpkSchoolName ?? '' }}</td></tr>
            <tr><td>2&ensp;Alamat</td><td>{{ $bpkSchoolAddress ?? '' }}</td></tr>
            <tr><td>3&ensp;Nama Kepala Sekolah</td><td>{{ $principalName }}</td></tr>
            <tr><td>4&ensp;NIP Kepala Sekolah</td><td>{{ $principalNip }}</td></tr>
            <tr><td>5&ensp;Nama Bendahara</td><td>{{ $treasurerName }}</td></tr>
            <tr><td>6&ensp;NIP Bendahara</td><td>{{ $treasurerNip }}</td></tr>
            <tr><td>7&ensp;No. Rekening</td><td>……………………</td></tr>
        </table>
        <table class="report-table bpk-dinas-table">
            <thead>
                <tr><th colspan="6">DATA REALISASI KEUANGAN</th></tr>
                <tr><th>NO</th><th>KETERANGAN</th><th>TANGGAL</th><th>MASUK</th><th>KELUAR</th><th>SALDO</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td class="integer">1</td>
                    <td><strong>SALDO AWAL {{ $bpk['openingDate'] }} (Saldo Bank + Saldo Kas Tunai)</strong></td>
                    <td>{{ $bpk['openingDate'] }}</td>
                    <td class="money"></td>
                    <td class="money"></td>
                    <td class="money"><strong>{{ $bpkZero($bpk['openingBank'] + $bpk['openingCash']) }}</strong></td>
                </tr>
                <tr><td></td><td>a&ensp;Saldo Kas Bank :</td><td></td><td class="money"></td><td class="money"></td><td class="money">{{ $bpkZero($bpk['openingBank']) }}</td></tr>
                <tr><td></td><td>&ensp;&ensp;BOS Reguler</td><td></td><td class="money"></td><td class="money"></td><td class="money">{{ $bpkBlank($bpk['openingBank']) }}</td></tr>
                <tr><td></td><td>&ensp;&ensp;BOS Kinerja</td><td></td><td class="money"></td><td class="money"></td><td class="money"></td></tr>
                <tr><td></td><td>b&ensp;Saldo Kas Tunai</td><td></td><td class="money"></td><td class="money"></td><td class="money">{{ $bpkZero($bpk['openingCash']) }}</td></tr>
                <tr>
                    <td class="integer">2</td>
                    <td><strong>PENDAPATAN OPERASIONAL:</strong></td>
                    <td></td><td class="money"></td><td class="money"></td>
                    <td class="money"><strong>{{ $bpkZero($bpk['masukTotal']) }}</strong></td>
                </tr>
                <tr>
                    <td></td><td>a&ensp;Pendapatan Dana BOS :</td><td></td>
                    <td class="money"><strong>{{ $bpkZero($bpk['terimaTotal']) }}</strong></td>
                    <td class="money"></td><td class="money"></td>
                </tr>
                @foreach($bpk['terimaRows'] as $row)
                    <tr><td></td><td>&ensp;&ensp;{{ $row['uraian'] }}</td><td>{{ $row['date'] }}</td><td class="money">{{ $rupiah($row['amount']) }}</td><td class="money"></td><td class="money"></td></tr>
                @endforeach
                <tr><td></td><td>&ensp;&ensp;BOS Reguler</td><td></td><td class="money">{{ $bpkBlank($bpk['terimaReguler']) }}</td><td class="money"></td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;BOS Kinerja</td><td></td><td class="money">{{ $bpkBlank($bpk['terimaKinerja']) }}</td><td class="money"></td><td class="money"></td></tr>
                <tr>
                    <td></td><td>b&ensp;Pendapatan Bunga Bank</td><td></td>
                    <td class="money"><strong>{{ $bpkZero($bpk['bungaTotal']) }}</strong></td>
                    <td class="money"></td><td class="money"></td>
                </tr>
                @foreach($bpk['bungaRows'] as $row)
                    <tr><td></td><td>&ensp;&ensp;{{ $row['label'] }}</td><td>{{ $row['endDate'] }}</td><td class="money">{{ $rupiah($row['amount']) }}</td><td class="money"></td><td class="money"></td></tr>
                @endforeach
                <tr><td></td><td>c&ensp;Pendapatan lain-lain :</td><td></td><td class="money"></td><td class="money"></td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;Setor Tunai</td><td></td><td class="money">{{ $bpkBlank($bpk['setorTotal']) }}</td><td class="money"></td><td class="money"></td></tr>
                <tr>
                    <td class="integer">3</td>
                    <td><strong>BELANJA OPERASIONAL :</strong></td>
                    <td></td><td class="money"></td><td class="money"></td>
                    <td class="money"><strong>{{ $bpkZero($bpk['opTotal']) }}</strong></td>
                </tr>
                <tr><td></td><td>a&ensp;Belanja Persediaan Barang Pakai Habis :</td><td></td><td class="money"></td><td class="money"><strong>{{ $bpkZero($bpk['op']['persediaan']) }}</strong></td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;BOS Reguler</td><td></td><td class="money"></td><td class="money">{{ $bpkBlank($bpk['opReguler']['persediaan']) }}</td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;BOS Kinerja</td><td></td><td class="money"></td><td class="money"></td><td class="money"></td></tr>
                <tr><td></td><td>b&ensp;Belanja Perjalanan Dinas :</td><td></td><td class="money"></td><td class="money"><strong>{{ $bpkZero($bpk['op']['perjalanan']) }}</strong></td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;BOS Reguler</td><td></td><td class="money"></td><td class="money">{{ $bpkBlank($bpk['opReguler']['perjalanan']) }}</td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;BOS Kinerja</td><td></td><td class="money"></td><td class="money"></td><td class="money"></td></tr>
                <tr><td></td><td>c&ensp;Belanja Pemeliharaan :</td><td></td><td class="money"></td><td class="money"><strong>{{ $bpkZero($bpk['op']['pemeliharaan']) }}</strong></td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;1. Belanja Pemeliharaan yang menambah Nilai gedung/ bangunan</td><td></td><td class="money"></td><td class="money">{{ $bpkZero(0) }}</td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;Bos Reguler</td><td></td><td class="money"></td><td class="money"></td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;KIB B</td><td></td><td class="money"></td><td class="money"></td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;KIB C</td><td></td><td class="money"></td><td class="money"></td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;KIB D</td><td></td><td class="money"></td><td class="money"></td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;Bos Kinerja</td><td></td><td class="money"></td><td class="money"></td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;2. Belanja Pemeliharaan yang tidak menambah nilai gedung / bangunan</td><td></td><td class="money"></td><td class="money">{{ $bpkZero($bpk['op']['pemeliharaan']) }}</td><td class="money"></td></tr>
                <tr><td></td><td>d&ensp;Belanja Koran, Listrik, Air, Telepon :</td><td></td><td class="money"></td><td class="money"><strong>{{ $bpkZero($bpk['op']['koran']) }}</strong></td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;BOS Reguler</td><td></td><td class="money"></td><td class="money">{{ $bpkBlank($bpk['opReguler']['koran']) }}</td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;BOS Kinerja</td><td></td><td class="money"></td><td class="money"></td><td class="money"></td></tr>
                <tr><td></td><td>e&ensp;Belanja Makan dan Minum :</td><td></td><td class="money"></td><td class="money"><strong>{{ $bpkZero($bpk['op']['makan']) }}</strong></td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;BOS Reguler</td><td></td><td class="money"></td><td class="money">{{ $bpkBlank($bpk['opReguler']['makan']) }}</td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;BOS Kinerja</td><td></td><td class="money"></td><td class="money"></td><td class="money"></td></tr>
                <tr><td></td><td>f&ensp;Belanja Jasa :</td><td></td><td class="money"></td><td class="money"><strong>{{ $bpkZero($bpk['op']['jasa']) }}</strong></td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;BOS Reguler</td><td></td><td class="money"></td><td class="money">{{ $bpkBlank($bpk['opReguler']['jasa']) }}</td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;BOS Kinerja</td><td></td><td class="money"></td><td class="money"></td><td class="money"></td></tr>
                <tr><td></td><td>g&ensp;Beban Bunga Bank</td><td></td><td class="money"></td><td class="money"><strong>{{ $bpkZero($bpk['bebanBunga']) }}</strong></td><td class="money"></td></tr>
                <tr>
                    <td class="integer">4</td>
                    <td><strong>BELANJA MODAL :</strong></td>
                    <td></td><td class="money"></td><td class="money"></td>
                    <td class="money"><strong>{{ $bpkZero($bpk['modalTotal']) }}</strong></td>
                </tr>
                <tr><td></td><td>a&ensp;KIB A (TANAH)</td><td></td><td class="money"></td><td class="money"><strong>{{ $bpkZero($bpk['modal']['A']) }}</strong></td><td class="money"></td></tr>
                <tr><td></td><td>b&ensp;KIB B (PERALATAN DAN MESIN)</td><td></td><td class="money"></td><td class="money"><strong>{{ $bpkZero($bpk['modal']['B']) }}</strong></td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;BOS Reguler</td><td></td><td class="money"></td><td class="money">{{ $bpkBlank($bpk['modalReguler']['B']) }}</td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;BOS Kinerja</td><td></td><td class="money"></td><td class="money"></td><td class="money"></td></tr>
                <tr><td></td><td>c&ensp;KIB C (GEDUNG DAN BANGUNAN)</td><td></td><td class="money"></td><td class="money"><strong>{{ $bpkZero($bpk['modal']['C']) }}</strong></td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;BOS Reguler</td><td></td><td class="money"></td><td class="money"></td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;BOS Kinerja</td><td></td><td class="money"></td><td class="money"></td><td class="money"></td></tr>
                <tr><td></td><td>d&ensp;KIB D (SALURAN, JARINGAN DAN IRIGASI)</td><td></td><td class="money"></td><td class="money"><strong>{{ $bpkZero($bpk['modal']['D']) }}</strong></td><td class="money"></td></tr>
                <tr><td></td><td>e&ensp;KIB E (ASET TETAP LAINNYA)</td><td></td><td class="money"></td><td class="money"><strong>{{ $bpkZero($bpk['modal']['E']) }}</strong></td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;BOS Reguler</td><td></td><td class="money"></td><td class="money">{{ $bpkBlank($bpk['modalReguler']['E']) }}</td><td class="money"></td></tr>
                <tr><td></td><td>&ensp;&ensp;BOS Kinerja</td><td></td><td class="money"></td><td class="money"></td><td class="money"></td></tr>
                <tr><td></td><td>f&ensp;KIB F (KONSTRUKSI)</td><td></td><td class="money"></td><td class="money"><strong>{{ $bpkZero($bpk['modal']['F']) }}</strong></td><td class="money"></td></tr>
                <tr>
                    <td class="integer">5</td>
                    <td><strong>SALDO AKHIR {{ $bpk['finalDate'] }} (Total)</strong></td>
                    <td><strong>{{ $bpk['finalDate'] }}</strong></td>
                    <td class="money"></td><td class="money"></td>
                    <td class="money"><strong>{{ $bpkZero($bpk['closing']) }}</strong></td>
                </tr>
                <tr><td></td><td>Terdiri Dari :</td><td></td><td class="money"></td><td class="money"></td><td class="money"></td></tr>
                <tr><td></td><td>a&ensp;Saldo Kas Bank</td><td></td><td class="money"></td><td class="money"></td><td class="money"><strong>{{ $bpkZero($bpk['closingBank']) }}</strong></td></tr>
                <tr><td></td><td>b&ensp;Saldo Kas Tunai</td><td></td><td class="money"></td><td class="money"></td><td class="money"><strong>{{ $bpkZero($bpk['closingCash']) }}</strong></td></tr>
            </tbody>
        </table>
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
                    Dibuat Oleh,<br>
                    Bendahara BOS
                    <div class="signature-space"></div>
                    <div class="signature-name">{{ $treasurerName }}</div>
                    @if($treasurerNip)
                        <div>NIP. {{ $treasurerNip }}</div>
                    @endif
                </td>
            </tr>
        </table>
        <div class="note">
            <strong>Catatan :</strong><br>
            Adanya pengembalian sisa uang ke No. Rekening (A/C) : …………………… sebesar Rp ………………… Pada tanggal ……… Dengan bukti setoran
        </div>
