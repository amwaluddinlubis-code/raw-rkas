@php
    $rupiah = fn ($value) => number_format((float) $value, 0, ',', '.');
    $principalName = trim((string) ($profile->principal_name ?? '')) ?: '........................................';
    $principalNip = trim((string) ($profile->principal_nip ?? ''));
    $treasurerName = trim((string) ($profile->treasurer_name ?? '')) ?: '........................................';
    $treasurerNip = trim((string) ($profile->treasurer_nip ?? ''));
    $isLedger = in_array($presentation, ['bku_ledger', 'cash_ledger'], true);
    $isRekap = $presentation === 'rekap_bosp';
    $isBpk = $presentation === 'bpk_bos';
    $isA1 = $presentation === 'bos_a1' && isset($bosA1);
    $isK7b = $presentation === 'k7b' && isset($k7b);
    $isK7c = $presentation === 'k7c' && isset($k7c);
    $isK7a = $presentation === 'k7a' && isset($k7a);
    $isK7 = $presentation === 'k7' && isset($k7);
    $isSptjm = $presentation === 'sptjm_doc' && isset($sptjm);
    $ledgerTitle = $presentation === 'cash_ledger' ? 'BUKU PEMBANTU KAS' : 'BUKU KAS UMUM (BKU)';
    $closingSubject = $presentation === 'cash_ledger' ? 'Buku Pembantu Kas' : 'Buku Kas Umum';
@endphp

<article class="document-sheet">
    @if($isLedger || $presentation === 'tax')
        <h1 class="doc-title">{{ $presentation === 'tax' ? 'BUKU PEMBANTU PAJAK' : $ledgerTitle }}</h1>
        @if($presentation === 'tax')
            <div class="doc-subtitle">BKU - PAJAK</div>
        @endif
        <table class="bku-identity-block page-break-avoid">
            <tr>
                <td>
                    <table class="bku-identity">
                        <tr><td>NPSN</td><td>: {{ $school->npsn ?? '-' }}</td></tr>
                        <tr><td>Nama Sekolah</td><td>: {{ $school->name }}</td></tr>
                        <tr><td>Alamat</td><td>: {{ $school->address ?: '-' }}</td></tr>
                        <tr><td>Desa / Kelurahan</td><td>: {{ $school->desa ?: '-' }}</td></tr>
                        <tr><td>Kecamatan</td><td>: {{ $school->district ?: '-' }}</td></tr>
                        <tr><td>Kabupaten / Kota</td><td>: {{ $school->regency ?: '-' }}</td></tr>
                        <tr><td>Provinsi</td><td>: {{ $school->province ?: '-' }}</td></tr>
                    </table>
                </td>
                <td>
                    <table class="bku-identity">
                        <tr><td>Sumber Dana</td><td>: {{ $bkuMeta['sumber_dana'] ?? $fundSource }}</td></tr>
                        <tr><td>Tahun Anggaran</td><td>: {{ $bkuMeta['tahun'] ?? $year->year }}</td></tr>
                        <tr><td>Periode</td><td>: {{ $bkuMeta['periode'] ?? $summary['period_label'] }}</td></tr>
                        <tr><td>Tanggal Periode</td><td>: {{ $bkuMeta['tanggal'] ?? '-' }}</td></tr>
                        <tr><td>Jumlah Transaksi</td><td>: {{ number_format((int) ($bkuMeta['jumlah_transaksi'] ?? 0), 0, ',', '.') }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>
    @else
    @if($isA1)
    @php
        $a1District = trim((string) ($school->district ?? ''));
        if ($a1District !== '' && ! str_starts_with($a1District, 'Kec.')) {
            $a1District = 'Kec. '.$a1District;
        }
        $a1Desa = trim((string) ($school->desa ?? ''));
        $a1DesaKec = trim($a1Desa.($a1District !== '' ? ' / '.$a1District : ''), ' /') ?: '-';
    @endphp
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;">
        <div style="flex:1;text-align:center;">
            <h1 class="doc-title">REKAPITULASI REALISASI PENGGUNAAN DANA BOS REGULER</h1>
            <div class="doc-subtitle">PERIODE TANGGAL : {{ $bosA1Title['range'] ?? '-' }}</div>
        </div>
        <table style="border-collapse:collapse;font-size:11px;white-space:nowrap;">
            <tr><td style="border:1px solid #111827;padding:2px 10px;text-align:center;">Format BOS A-1</td></tr>
            <tr><td style="border:1px solid #111827;padding:2px 10px;text-align:center;">Diisi oleh sekolah</td></tr>
            <tr><td style="border:1px solid #111827;padding:2px 10px;text-align:center;">Dikirim ke TIM Menajemen BOS Kab/Kota</td></tr>
        </table>
    </div>
    <table class="bku-identity-block page-break-avoid">
        <tr>
            <td>NPSN</td>
            <td>: {{ $school->npsn ?? '-' }}</td>
        </tr>
        <tr>
            <td>Nama Sekolah</td>
            <td>: {{ $school->name }}</td>
        </tr>
        <tr>
            <td>Desa / Kecamatan</td>
            <td>: {{ $a1DesaKec }}</td>
        </tr>
        <tr>
            <td>Kabupaten/Kota</td>
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
    @endif
    @if($isK7b)
    <h1 class="doc-title">REGISTER PENUTUPAN KAS</h1>
    <div class="doc-subtitle">Formulir BOS-K7B</div>
    <table class="meta-table page-break-avoid">
        <tr>
            <td>Tanggal Penutupan Kas</td>
            <td>: {{ $k7b['closing_date'] }}</td>
        </tr>
        <tr>
            <td>Nama Penutup Kas (Pemegang Kas)</td>
            <td>: {{ $treasurerName }}</td>
        </tr>
        <tr>
            <td>Tanggal Penutupan Kas Yang Lalu</td>
            <td>: {{ $k7b['prev_closing'] }}</td>
        </tr>
    </table>
    @endif
    @if($isK7c)
    <h1 class="doc-title">BERITA ACARA PEMERIKSAAN KAS</h1>
    <div class="doc-subtitle">Formulir BOS-K7C</div>
    @endif
    @if($isK7a)
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;">
        <div style="flex:1;text-align:center;">
            <h1 class="doc-title">REKAPITULASI REALISASI PENGGUNAAN DANA BOS</h1>
            <div class="doc-subtitle">PERIODE {{ $summary['period_label'] }}</div>
        </div>
        <table style="border-collapse:collapse;font-size:11px;white-space:nowrap;">
            <tr><td style="border:1px solid #111827;padding:2px 10px;text-align:center;">BOS K7A</td></tr>
            <tr><td style="border:1px solid #111827;padding:2px 10px;text-align:center;">Diisi oleh sekolah</td></tr>
            <tr><td style="border:1px solid #111827;padding:2px 10px;text-align:center;">Dikirim ke TIM Manajemen BOS Kab/Kota</td></tr>
        </table>
    </div>
    <table class="bku-identity-block page-break-avoid">
        <tr>
            <td>NPSN</td>
            <td>: {{ $school->npsn ?? '-' }}</td>
        </tr>
        <tr>
            <td>Nama Sekolah</td>
            <td>: {{ $school->name }}</td>
        </tr>
        <tr>
            <td>Kecamatan</td>
            <td>: {{ $school->district ?: '-' }}</td>
        </tr>
        <tr>
            <td>Kabupaten/Kota</td>
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
    @endif
    @if($isK7)
    <h1 class="doc-title">REALISASI PENGGUNAAN DANA TIAP JENIS ANGGARAN</h1>
    <div class="doc-subtitle">Format K7 — PERIODE {{ $summary['period_label'] }}</div>
    <table class="bku-identity-block page-break-avoid">
        <tr>
            <td>Nama Sekolah</td>
            <td>: {{ $school->name }}</td>
        </tr>
        <tr>
            <td>NPSN</td>
            <td>: {{ $school->npsn ?? '-' }}</td>
        </tr>
        <tr>
            <td>Sumber Dana</td>
            <td>: {{ $fundSource }}</td>
        </tr>
    </table>
    @endif
    @if($isSptjm)
    <header class="doc-header">
        <div class="school">{{ $school->name }}</div>
        <div class="address">
            {{ $school->address ?: '-' }}
            @if($school->district || $school->regency)
                · {{ collect([$school->district, $school->regency, $school->province])->filter()->implode(', ') }}
            @endif
        </div>
        @if($school->npsn)
            <div class="address">NPSN {{ $school->npsn }}</div>
        @endif
    </header>
    <h1 class="doc-title">SURAT PERNYATAAN TANGGUNG JAWAB MUTLAK</h1>
    @endif
    @if(! $isRekap && ! $isA1 && ! $isK7b && ! $isK7c && ! $isK7a && ! $isK7 && ! $isSptjm)
    <header class="doc-header">
        <div class="school">{{ $school->name }}</div>
        <div class="address">
            {{ $school->address ?: '-' }}
            @if($school->district || $school->regency)
                · {{ collect([$school->district, $school->regency, $school->province])->filter()->implode(', ') }}
            @endif
        </div>
        @if($school->npsn)
            <div class="address">NPSN {{ $school->npsn }}</div>
        @endif
    </header>

    <h1 class="doc-title">{{ $report['label'] }}</h1>
    <div class="doc-subtitle">{{ $summary['period_label'] }}</div>
    @endif
    @endif

    @if($isRekap && isset($rekapBosp))
        <h1 class="doc-title">REKAPITULASI REALISASI PENGGUNAAN DANA BOSP</h1>
        <div class="doc-subtitle">PERIODE TANGGAL : {{ $rekapTitle['range'] ?? '-' }}</div>
        <div class="doc-subtitle">{{ $rekapTitle['phase'] ?? '' }}</div>
        <table class="bku-identity-block page-break-avoid">
            <tr>
                <td>NPSN</td>
                <td>: {{ $school->npsn ?? '-' }}</td>
            </tr>
            <tr>
                <td>Nama Sekolah</td>
                <td>: {{ $school->name }}</td>
            </tr>
            <tr>
                <td>Kecamatan</td>
                <td>: {{ str_starts_with((string) $school->district, 'Kec.') ? $school->district : 'Kec. '.$school->district }}</td>
            </tr>
            <tr>
                <td>Kabupaten/Kota</td>
                <td>: {{ str_starts_with((string) $school->regency, 'Kab.') ? $school->regency : 'Kab. '.$school->regency }}</td>
            </tr>
            <tr>
                <td>Provinsi</td>
                <td>: {{ str_starts_with((string) $school->province, 'Prov.') ? $school->province : 'Prov. '.$school->province }}</td>
            </tr>
            <tr>
                <td>Sumber Dana</td>
                <td>: {{ $fundSource }}</td>
            </tr>
        </table>
        @php
            $snpRows = $rekapSnpRows ?? [];
            $subPrograms = $rekapSubPrograms ?? [];
            $rekapCols = range(1, 12);
        @endphp
        <table class="report-table rekap-bosp-table">
            <thead>
                <tr>
                    <th rowspan="3">No.<br>Urut</th>
                    <th rowspan="3">Standar Nasional<br>Pendidikan</th>
                    <th colspan="12">SUB PROGRAM</th>
                    <th rowspan="3">Jumlah</th>
                </tr>
                <tr>
                    @foreach($rekapCols as $col)
                        <th>{{ $subPrograms[$col] }}</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach($rekapCols as $col)
                        <th>{{ $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($snpRows as $rowIndex => $snpLabel)
                    @php
                        $rowNo = $rowIndex + 1;
                    @endphp
                    <tr>
                        <td class="integer">{{ $rowNo }}</td>
                        <td>{{ $snpLabel }}</td>
                        @foreach($rekapCols as $col)
                            <td class="money">{{ $rupiah($rekapBosp['grid'][$rowNo][$col] ?? 0) }}</td>
                        @endforeach
                        <td class="money"><strong>{{ $rupiah($rekapBosp['rowTotals'][$rowNo] ?? 0) }}</strong></td>
                    </tr>
                @endforeach
                <tr>
                    <td class="integer"></td>
                    <td><strong>JUMLAH</strong></td>
                    @foreach($rekapCols as $col)
                        <td class="money"><strong>{{ $rupiah($rekapBosp['colTotals'][$col] ?? 0) }}</strong></td>
                    @endforeach
                    <td class="money"><strong>{{ $rupiah($rekapBosp['grand'] ?? 0) }}</strong></td>
                </tr>
            </tbody>
        </table>
        <table class="meta-table page-break-avoid">
            <tr>
                <td>Saldo periode sebelumnya</td>
                <td>: Rp. {{ $rupiah($rekapBosp['prev'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>Total penerimaan dana BOSP periode ini</td>
                <td>: Rp. {{ $rupiah($rekapBosp['received'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>Total penggunaan dana BOSP periode ini</td>
                <td>: Rp. {{ $rupiah($rekapBosp['used'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>Akhir saldo BOSP periode ini</td>
                <td>: Rp. {{ $rupiah($rekapBosp['closing'] ?? 0) }}</td>
            </tr>
        </table>
    @endif

    @if($isA1)
    @php
        $a1Money = fn ($value) => 'Rp '.($value > 0 ? $rupiah($value) : '-');
    @endphp
    <table class="report-table">
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
            @foreach($bosA1['rows'] as $a1row)
                <tr>
                    <td class="integer">{{ $a1row['no'] }}</td>
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

    @if($isK7b)
    <table class="meta-table page-break-avoid">
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

    @if($isK7c)
    <div class="statement">
        <p>Pada hari ini {{ $k7c['weekday_date'] }}, yang bertanda tangan di bawah ini, kami Kepala Sekolah yang ditunjuk berdasarkan Surat Keputusan No. - (diisi manual), Nama : {{ $principalName }}, Jabatan : Kepala Sekolah, melakukan pemeriksaan kas kepada : Nama : {{ $treasurerName }}, Jabatan : Bendahara / Pemegang Kas, yang berdasarkan Surat Keputusan No. - (diisi manual) ditugaskan dalam pengurusan uang Bantuan Operasional Sekolah (BOS).</p>
        <p>Berdasarkan pemeriksaan kas serta bukti-bukti dalam pengurusan ini, kami menemui kenyataan sebagai berikut :</p>
    </div>
    <div style="margin-top:12px;font-weight:700;">Jumlah uang yang dihitung dihadapan bendahara / Pemegang Kas adalah :</div>
    <table class="report-table">
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
        <tr>
            <td>Tanggal</td>
            <td>: {{ $k7c['closing_date'] }}</td>
        </tr>
    </table>
    @endif

    @if($isK7a)
    <table class="report-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Komponen Penggunaan Dana BOS</th>
                <th>Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($k7a['rows'] as $k7arow)
                <tr>
                    <td class="integer">{{ $k7arow['no'] }}</td>
                    <td>{{ $k7arow['label'] }}</td>
                    <td class="money">{{ $rupiah($k7arow['amount']) }}</td>
                </tr>
            @endforeach
            <tr>
                <td class="integer"></td>
                <td><strong>JUMLAH</strong></td>
                <td class="money"><strong>{{ $rupiah($k7a['total']) }}</strong></td>
            </tr>
        </tbody>
    </table>
    @if($k7a['unmapped'] > 0)
    <div class="note">
        Belanja Rp. {{ $rupiah($k7a['unmapped']) }} ({{ $k7a['unmappedCount'] }} baris kas) belum terpetakan ke komponen di atas dan tidak masuk JUMLAH — cocokkan manual dengan keluaran K7A resmi sebelum ditandatangani.
    </div>
    @endif
    @endif

    @if($isK7)
    @php
        $k7months = array_keys($k7['months']);
    @endphp
    <table class="report-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Jenis Anggaran</th>
                @foreach($k7months as $k7month)
                    <th>{{ $k7['months'][$k7month] }}</th>
                @endforeach
                <th>Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @php $k7no = 0; @endphp
            @foreach($k7['rows'] as $k7row)
                @php $k7no++; @endphp
                <tr>
                    <td class="integer">{{ $k7no }}</td>
                    <td>{{ $k7row['label'] }}</td>
                    @foreach($k7months as $k7month)
                        <td class="money">{{ $rupiah($k7row['months'][$k7month] ?? 0) }}</td>
                    @endforeach
                    <td class="money"><strong>{{ $rupiah($k7row['total']) }}</strong></td>
                </tr>
            @endforeach
            <tr>
                <td class="integer"></td>
                <td><strong>JUMLAH</strong></td>
                @foreach($k7months as $k7month)
                    <td class="money"><strong>{{ $rupiah($k7['colTotals'][$k7month] ?? 0) }}</strong></td>
                @endforeach
                <td class="money"><strong>{{ $rupiah($k7['grand']) }}</strong></td>
            </tr>
        </tbody>
    </table>
    @if($k7['unmapped'] > 0)
    <div class="note">
        Belanja Rp. {{ $rupiah($k7['unmapped']) }} ({{ $k7['unmappedCount'] }} baris kas) berkode rekening di luar 5.1.01/5.1.02/5.2 dan tidak masuk JUMLAH — cocokkan manual dengan pembukuan sebelum ditandatangani.
    </div>
    @endif
    <div class="statement">
        <p>Lampiran Format K7 ini dibuat oleh sekolah sebagai pernyataan tanggung jawab atas realisasi penggunaan dana pada {{ $summary['period_label'] }} sebesar Rp. {{ $rupiah($k7['grand']) }} dan dikirim kepada Tim Manajemen BOS Kabupaten/Kota bersama administrasi BOS lainnya setiap triwulan.</p>
        <p>Dokumen ini ditandatangani oleh Kepala Sekolah di atas materai dan disimpan di sekolah untuk diperlihatkan kepada pengawas, Tim Manajemen BOS Kabupaten/Kota, dan para pemeriksa lainnya apabila diperlukan.</p>
    </div>
    @endif

    @if($isSptjm)
    <table class="meta-table page-break-avoid">
        <tr>
            <td>Nama</td>
            <td>: {{ $principalName }}</td>
        </tr>
        <tr>
            <td>NIK</td>
            <td>: .................................... (diisi manual)</td>
        </tr>
        <tr>
            <td>Jabatan</td>
            <td>: Kepala {{ $school->name }}</td>
        </tr>
        <tr>
            <td>Alamat</td>
            <td>: {{ $school->address ?: '-' }}</td>
        </tr>
    </table>
    <div class="statement">
        <p>Dengan ini menyatakan dengan sesungguhnya bahwa saya bertanggung jawab penuh atas penggunaan dana Bantuan Operasional Sekolah (BOS) Tahun Anggaran {{ $sptjm['year'] }} pada {{ $sptjm['period_label'] }} (penerimaan Rp. {{ $rupiah($sptjm['received']) }}, penggunaan Rp. {{ $rupiah($sptjm['used']) }}).</p>
        <p>Apabila di kemudian hari, atas penggunaan dana dimaksud mengakibatkan kerugian negara, maka saya bersedia dituntut penggantian kerugian negara dimaksud sesuai dengan ketentuan peraturan perundang-undangan.</p>
        <p>Bukti-bukti pengeluaran terkait penggunaan dana dimaksud saya simpan sesuai dengan ketentuan untuk kelengkapan administrasi dan keperluan pemeriksaan aparat pengawas fungsional dan/atau lainnya.</p>
        <p>Demikian surat pernyataan ini dibuat dengan sesungguhnya.</p>
    </div>
    <table class="meta-table page-break-avoid">
        <tr>
            <td>Tempat / Tanggal</td>
            <td>: {{ $sptjm['place'] }}, {{ $sptjm['signed_date'] }}</td>
        </tr>
    </table>
    @endif

    @if(! in_array($presentation, ['bku_ledger', 'tax'], true) && ! $isRekap && ! $isBpk && ! $isA1 && ! $isK7b && ! $isK7c && ! $isK7a && ! $isK7 && ! $isSptjm)
    <table class="meta-table page-break-avoid">
        <tr>
            <td>Tahun Anggaran</td>
            <td>: {{ $year->year }}</td>
            <td>Sumber Dana</td>
            <td>: {{ $fundSource }}</td>
        </tr>
        <tr>
            <td>Periode</td>
            <td>: {{ $summary['date_from'] }} s.d. {{ $summary['date_to'] }}</td>
            <td>Jumlah Transaksi</td>
            <td>: {{ number_format((int) $summary['transaction_count'], 0, ',', '.') }}</td>
        </tr>
    </table>
    @endif

    @if(! empty($bpkData))
        @include('periodic-reports.partials.bpk-dinas')
    @endif


    @if($presentation === 'statement')
        <div class="statement">
            @foreach($statement as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>
    @endif

    @if(! $isLedger && ! $isRekap && ! $isBpk && ! $isA1 && ! $isK7b && ! $isK7c && ! $isK7a && ! $isK7 && ! $isSptjm)
    <table class="summary-table page-break-avoid">
        <thead>
            <tr>
                <th>Nilai Bruto</th>
                <th>Total Pajak</th>
                <th>Nilai Dibayarkan</th>
                <th>PPN</th>
                <th>PPh 21</th>
                <th>PPh 22</th>
                <th>PPh 23</th>
                <th>PPh 4(2)</th>
                <th>SSPD</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="money">{{ $rupiah($summary['gross']) }}</td>
                <td class="money">{{ $rupiah($summary['tax']) }}</td>
                <td class="money">{{ $rupiah($summary['net']) }}</td>
                <td class="money">{{ $rupiah($summary['ppn']) }}</td>
                <td class="money">{{ $rupiah($summary['pph21']) }}</td>
                <td class="money">{{ $rupiah($summary['pph22']) }}</td>
                <td class="money">{{ $rupiah($summary['pph23']) }}</td>
                <td class="money">{{ $rupiah($summary['pph4']) }}</td>
                <td class="money">{{ $rupiah($summary['sspd']) }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    @if(count($columns) > 0)
        @if($presentation === 'statement')
            <div style="margin-top:12px;font-weight:700;">Rekap Pendukung</div>
        @endif
        <table class="report-table{{ $isLedger ? ' bku-table' : '' }}">
            @if($isLedger)
                <colgroup>
                    <col style="width:72px">
                    <col style="width:68px">
                    <col style="width:112px">
                    <col style="width:62px">
                    <col>
                    <col style="width:94px">
                    <col style="width:94px">
                    <col style="width:94px">
                </colgroup>
            @endif
            <thead>
                <tr>
                    @foreach($columns as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr class="{{ $row['row_class'] ?? '' }}">
                        @foreach($columns as $column)
                            @php
                                $value = $row[$column['key']] ?? null;
                                $type = $column['type'];
                            @endphp
                            <td class="{{ $type }}">
                                @if($type === 'money')
                                    {{ $value === '' || $value === null ? '' : $rupiah($value) }}
                                @elseif($type === 'integer')
                                    {{ $value === '' || $value === null ? '' : number_format((int) $value, 0, ',', '.') }}
                                @elseif($type === 'nowrap')
                                    <span class="nowrap">{{ $value ?: '-' }}</span>
                                @elseif($type === 'stacked')
                                    {!! $value ?: '-' !!}
                                @else
                                    {{ $value ?: '-' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ max(1, count($columns)) }}" class="empty-row">Tidak ada transaksi pada periode ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif

    @if($isLedger && isset($bkuClosing))
        <div class="bku-closing page-break-avoid">
            <p>Pada hari ini {{ $bkuClosing['weekday_date'] }} {{ $closingSubject }} Ditutup dengan keadaan/posisi buku sebagai berikut :</p>
            <table class="bku-closing-table">
                @if($presentation === 'cash_ledger')
                    <tr><td class="bku-closing-label"><strong>Saldo Kas Tunai</strong></td><td>: Rp. {{ $rupiah($bkuClosing['cash']) }}</td></tr>
                @else
                    <tr><td class="bku-closing-label"><strong>Saldo Buku Kas Umum</strong></td><td>: Rp. {{ $rupiah($bkuClosing['total']) }}</td></tr>
                @endif
                <tr><td class="bku-closing-label">Terdiri Dari :</td><td></td></tr>
                @if($presentation !== 'cash_ledger')
                    <tr><td class="bku-closing-label">- Saldo Bank</td><td>: Rp. {{ $rupiah($bkuClosing['bank']) }}</td></tr>
                @endif
                <tr><td class="bku-closing-label">- Saldo Kas Tunai</td><td>: Rp. {{ $rupiah($bkuClosing['cash']) }}</td></tr>
                <tr><td class="bku-closing-label"><strong>Jumlah</strong></td><td>: Rp. {{ $rupiah($bkuClosing['total']) }}</td></tr>
            </table>
        </div>
    @endif

    @if($isLedger || $presentation === 'tax')
    <table class="signature-table bku-signature">
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
                @if($school->district)Kec. {{ $school->district }}, @endif{{ $bkuClosing['signed_date'] ?? '' }}<br>
                Bendahara,
                <div class="signature-space"></div>
                <div class="signature-name">{{ $treasurerName }}</div>
                @if($treasurerNip)
                    <div>NIP. {{ $treasurerNip }}</div>
                @endif
            </td>
        </tr>
    </table>
    @endif
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
        A-1, K7A/K7/K7B/K7C/SPTJM, atau BPK Dinas yang membawa bloknya sendiri)
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
    @elseif($isK7a)
    <table class="signature-table">
        <tr>
            <td>
                Bendahara BOS
                <div class="signature-space"></div>
                <div class="signature-name">{{ $treasurerName }}</div>
                @if($treasurerNip)
                    <div>NIP. {{ $treasurerNip }}</div>
                @endif
            </td>
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
                Komite Sekolah
                <div class="signature-space"></div>
                <div class="signature-name">........................................</div>
            </td>
        </tr>
    </table>
    @elseif($isK7)
    <table class="signature-table">
        <tr>
            <td>
                Bendahara BOS
                <div class="signature-space"></div>
                <div class="signature-name">{{ $treasurerName }}</div>
                @if($treasurerNip)
                    <div>NIP. {{ $treasurerNip }}</div>
                @endif
            </td>
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
                Komite Sekolah
                <div class="signature-space"></div>
                <div class="signature-name">........................................</div>
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
    @elseif(! $isLedger && $presentation !== 'tax' && ! $isBpk && ! $isK7b && ! $isK7c && ! $isK7a && ! $isK7 && ! $isSptjm)
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

    <div class="note">
        Dicetak dari APP-SPJ pada {{ $generatedAt->format('d-m-Y H:i') }}. Nilai laporan bersumber dari transaksi pada konteks sekolah, tahun anggaran, dan sumber dana aktif. Dokumen harus dicocokkan dengan bukti fisik/rekening koran dan ketentuan instansi sebelum ditandatangani.
    </div>
</article>
