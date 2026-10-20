    @if($isSptjm)
    <table class="meta-table page-break-avoid">
        @include('periodic-reports.partials.reports.colgroup', ['widths' => ['30%', '70%']])
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
        @include('periodic-reports.partials.reports.colgroup', ['widths' => ['30%', '70%']])
        <tr>
            <td>Tempat / Tanggal</td>
            <td>: {{ $sptjm['place'] }}, {{ $sptjm['signed_date'] }}</td>
        </tr>
    </table>
    @endif
