@php
    $rupiah = fn ($value) => number_format((float) $value, 0, ',', '.');
    $principalName = trim((string) ($profile->principal_name ?? '')) ?: '........................................';
    $principalNip = trim((string) ($profile->principal_nip ?? ''));
    $treasurerName = trim((string) ($profile->treasurer_name ?? '')) ?: '........................................';
    $treasurerNip = trim((string) ($profile->treasurer_nip ?? ''));
    $committeeName = trim((string) ($profile->committee_name ?? '')) ?: '........................................';
    $isLedger = in_array($presentation, ['bku_ledger', 'cash_ledger'], true);
    $isRekap = $presentation === 'rekap_bosp';
    $isBpk = $presentation === 'bpk_bos';
    $isA1 = $presentation === 'bos_a1' && isset($bosA1);
    $isK7b = $presentation === 'k7b' && isset($k7b);
    $isK7c = $presentation === 'k7c' && isset($k7c);
    $isK7 = $presentation === 'k7' && isset($k7);
    $isK2 = $presentation === 'k2' && isset($k2);
    $isRpd = $presentation === 'rpd' && isset($rpd);
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
    @if($isK2)
    @php
        $k2DesaKec = trim(trim((string) ($school->desa ?? '')).' / '.trim((string) ($school->district ?? '')), ' /') ?: '-';
    @endphp
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;">
        <div style="flex:1;text-align:center;">
            <h1 class="doc-title">RENCANA KEGIATAN DAN ANGGARAN SEKOLAH ( RKAS )</h1>
            <div class="doc-subtitle">TAHUN ANGGARAN {{ $year->year }}</div>
            <div class="doc-subtitle">Dikirim ke TIM Menajemen BOS Kab/Kota</div>
        </div>
        <table style="border-collapse:collapse;font-size:11px;white-space:nowrap;">
            <tr><td style="border:1px solid #111827;padding:2px 10px;text-align:center;">Format BOS K-2</td></tr>
            <tr><td style="border:1px solid #111827;padding:2px 10px;text-align:center;">Diisi oleh sekolah</td></tr>
        </table>
    </div>
    <table class="bku-identity-block page-break-avoid">
        <tr>
            <td>Nama Sekolah</td>
            <td>: {{ $school->name }}</td>
        </tr>
        <tr>
            <td>Desa/Kecamatan</td>
            <td>: {{ $k2DesaKec }}</td>
        </tr>
        <tr>
            <td>Kabupaten</td>
            <td>: {{ $school->regency ?: '-' }}</td>
        </tr>
        <tr>
            <td>Provinsi</td>
            <td>: {{ $school->province ?: '-' }}</td>
        </tr>
        <tr>
            <td>Triwulan / Bulan</td>
            <td>: {{ $k2['quarter_label'] }} / {{ $k2['month_range'] }}</td>
        </tr>
        <tr>
            <td>Sumber Dana</td>
            <td>: {{ $fundSource }}</td>
        </tr>
    </table>
    @endif
    @if($isRpd)
    @php
        $rpdDesaKec = trim(trim((string) ($school->desa ?? '')).' / '.trim((string) ($school->district ?? '')), ' /') ?: '-';
    @endphp
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;">
        <div style="flex:1;text-align:center;">
            <h1 class="doc-title">RINCIAN PENGGUNAAN DANA BOS (RPD BOS)</h1>
            <div class="doc-subtitle">{{ $rpd['month_range'] }}</div>
        </div>
        <table style="border-collapse:collapse;font-size:11px;white-space:nowrap;">
            <tr><td style="border:1px solid #111827;padding:2px 10px;text-align:center;"><strong>RPD</strong></td></tr>
        </table>
    </div>
    <table class="bku-identity-block page-break-avoid">
        <tr>
            <td>Nama Sekolah</td>
            <td>: {{ $school->name }}</td>
        </tr>
        <tr>
            <td>Desa / Kec</td>
            <td>: {{ $rpdDesaKec }}</td>
        </tr>
        <tr>
            <td>Kab / Kota</td>
            <td>: {{ $school->regency ?: '-' }}</td>
        </tr>
        <tr>
            <td>Provinsi</td>
            <td>: {{ $school->province ?: '-' }}</td>
        </tr>
        <tr>
            <td>TRIWULAN</td>
            <td>: {{ $rpd['quarter_label'] }}</td>
        </tr>
    </table>
    @endif
    @if($isK7)
    @php
        $k7District = trim((string) ($school->district ?? ''));
        if ($k7District !== '' && ! str_starts_with($k7District, 'Kec.')) {
            $k7District = 'Kec. '.$k7District;
        }
        $k7Desa = trim((string) ($school->desa ?? ''));
        $k7DesaKec = trim($k7Desa.($k7District !== '' ? ' / '.$k7District : ''), ' /') ?: '-';
    @endphp
    <div class="k7-title">
        <div>{{ $k7['title']['heading'] }}</div>
        <div>{{ $k7['title']['year_line'] }}</div>
        <div>{{ $k7['title']['range_line'] }}</div>
    </div>
    <div class="k7-ident page-break-avoid">
        <table class="k7-ident-left">
            <tr><td>NPSN</td><td>: {{ $school->npsn ?? '-' }}</td></tr>
            <tr><td>Nama Sekolah</td><td>: {{ $school->name }}</td></tr>
            <tr><td>Desa/Kecamatan</td><td>: {{ $k7DesaKec }}</td></tr>
            <tr><td>Kabupaten</td><td>: {{ $school->regency ?: '-' }}</td></tr>
            <tr><td>Provinsi</td><td>: {{ $school->province ?: '-' }}</td></tr>
        </table>
        <table class="k7-formbox">
            <tr><td><strong>Formulir Bos-K7</strong></td></tr>
            <tr><td>Diisi Oleh Sekolah</td></tr>
            <tr><td>Dikirim Ke Tim Manajemen Bos Kab/Kota</td></tr>
        </table>
    </div>
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
    @if(! $isRekap && ! $isA1 && ! $isK7b && ! $isK7c && ! $isK7 && ! $isK2 && ! $isRpd && ! $isSptjm)
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

    @if($isRpd)
    @php
        $rpdMonths = array_keys($rpd['month_names']);
        $rpdColspan = 5 + count($rpdMonths);
    @endphp
    <table class="report-table">
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
                @foreach($rpd['month_names'] as $rpdMonthName)
                <th>{{ $rpdMonthName }}</th>
                @endforeach
            </tr>
            <tr>
                @for($i = 1; $i <= $rpdColspan; $i++)
                <th>{{ $i }}</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="integer">I</td>
                <td colspan="{{ $rpdColspan - 1 }}"><strong>PROGRAM SEKOLAH</strong></td>
            </tr>
            @foreach($rpd['rows'] as $rpdRow)
            <tr>
                <td class="integer">{{ $rpdRow['no'] }}</td>
                <td class="integer">{{ $rpdRow['code'] }}</td>
                <td>
                    @if($rpdRow['level'] === 'item')
                        {{ $rpdRow['label'] }}
                    @else
                        <strong>{{ $rpdRow['label'] }}</strong>
                    @endif
                </td>
                <td class="integer">{{ $rpdRow['level'] === 'item' && $rpdRow['volume'] > 0 ? rtrim(rtrim(number_format($rpdRow['volume'], 2, ',', '.'), '0'), ',') : '' }}</td>
                <td>{{ $rpdRow['level'] === 'item' ? $rpdRow['unit'] : '' }}</td>
                <td class="money">{{ $rpdRow['level'] === 'item' && $rpdRow['unit_price'] > 0 ? $rupiah($rpdRow['unit_price']) : '' }}</td>
                <td class="money">
                    @if($rpdRow['level'] === 'item')
                        {{ $rupiah($rpdRow['jumlah']) }}
                    @else
                        <strong>{{ $rupiah($rpdRow['jumlah']) }}</strong>
                    @endif
                </td>
                @foreach($rpdMonths as $rpdMonth)
                <td class="money">
                    @if($rpdRow['level'] === 'item')
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
                @foreach($rpdMonths as $rpdMonth)
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
                @foreach($rpdMonths as $rpdMonth)
                <td class="money"></td>
                @endforeach
            </tr>
            <tr>
                <td colspan="6"><strong>Sub Total Penggunaan Dana II</strong></td>
                <td class="money"><strong>0</strong></td>
                @foreach($rpdMonths as $rpdMonth)
                <td class="money"><strong>0</strong></td>
                @endforeach
            </tr>
            <tr>
                <td colspan="6"><strong>JUMLAH TOTAL (I + II)</strong></td>
                <td class="money"><strong>{{ $rupiah($rpd['totals']['jumlah']) }}</strong></td>
                @foreach($rpdMonths as $rpdMonth)
                <td class="money"><strong>{{ $rupiah($rpd['totals']['months'][$rpdMonth]) }}</strong></td>
                @endforeach
            </tr>
        </tbody>
    </table>
    @if(! $rpd['has_data'])
    <div class="note">
        Data anggaran RKAS belum tersedia — jalankan sinkronisasi ARKAS terlebih dahulu.
    </div>
    @endif
    @endif

    @include('periodic-reports.partials.reports.k7-table')


    @include('periodic-reports.partials.reports.sptjm')
    @if(! in_array($presentation, ['bku_ledger', 'tax'], true) && ! $isRekap && ! $isBpk && ! $isA1 && ! $isK7b && ! $isK7c && ! $isK7 && ! $isK2 && ! $isRpd && ! $isSptjm)
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

    @if(! $isLedger && ! $isRekap && ! $isBpk && ! $isA1 && ! $isK7b && ! $isK7c && ! $isK7 && ! $isK2 && ! $isRpd && ! $isSptjm)
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
        A-1, K7/K7B/K7C/SPTJM, atau BPK Dinas yang membawa bloknya sendiri)
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
    @elseif($isK2)
    <table class="signature-table">
        <tr>
            <td style="text-align:left;border:none;padding-bottom:0;">
                Diketahui Oleh :
            </td>
            <td style="text-align:left;border:none;padding-bottom:0;">
                Diketahui Oleh :
            </td>
            <td style="text-align:left;border:none;padding-bottom:0;">
                {{ $k2Title['place'] }}, {{ $k2Title['signed_date'] }}
            </td>
        </tr>
        <tr>
            <td>
                Komite Sekolah
                <div class="signature-space"></div>
                <div class="signature-name">{{ $committeeName }}</div>
            </td>
            <td>
                Kepala Sekolah
                <div class="signature-space"></div>
                <div class="signature-name">{{ $principalName }}</div>
                @if($principalNip)
                    <div>NIP. {{ $principalNip }}</div>
                @endif
            </td>
            <td>
                Bendahara
                <div class="signature-space"></div>
                <div class="signature-name">{{ $treasurerName }}</div>
                @if($treasurerNip)
                    <div>NIP. {{ $treasurerNip }}</div>
                @endif
            </td>
        </tr>
    </table>
    @elseif($isRpd)
    <table class="signature-table">
        <tr>
            <td style="text-align:left;border:none;padding-bottom:0;">
                Mengetahui,
            </td>
            <td style="text-align:left;border:none;padding-bottom:0;">
                {{ $rpdTitle['place'] }}, {{ $rpdTitle['signed_date'] }}
            </td>
        </tr>
        <tr>
            <td>
                KEPALA {{ mb_strtoupper($school->name) }}
                <div class="signature-space"></div>
                <div class="signature-name">{{ $principalName }}</div>
                @if($principalNip)
                    <div>NIP. {{ $principalNip }}</div>
                @endif
            </td>
            <td>
                BENDAHARA
                <div class="signature-space"></div>
                <div class="signature-name">{{ $treasurerName }}</div>
                @if($treasurerNip)
                    <div>NIP. {{ $treasurerNip }}</div>
                @endif
            </td>
        </tr>
    </table>
    @elseif($isK7)
    <table class="signature-table k7-sign">
        <tr>
            <td>
                Komite Sekolah
                <div class="signature-space"></div>
                <div class="signature-name">{{ $committeeName }}</div>
            </td>
            <td>
                Mengetahui<br>
                Kepala Sekolah
                <div class="signature-space"></div>
                <div class="signature-name">{{ $principalName }}</div>
                @if($principalNip)
                    <div>NIP. {{ $principalNip }}</div>
                @endif
            </td>
            <td>
                {{ $k7['place'] }}, {{ $k7['signed_date'] }}<br>
                Bendahara
                <div class="signature-space"></div>
                <div class="signature-name">{{ $treasurerName }}</div>
                @if($treasurerNip)
                    <div>NIP. {{ $treasurerNip }}</div>
                @endif
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
    @elseif(! $isLedger && $presentation !== 'tax' && ! $isBpk && ! $isK7b && ! $isK7c && ! $isK7 && ! $isK2 && ! $isRpd && ! $isSptjm)
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
