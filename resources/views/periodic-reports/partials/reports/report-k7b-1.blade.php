    @if($isK7b)
    <h1 class="doc-title">REGISTER PENUTUPAN KAS</h1>
    <div class="doc-subtitle">Formulir BOS-K7B</div>
    <table class="meta-table page-break-avoid">
        @include('periodic-reports.partials.reports.colgroup', ['widths' => ['55%', '45%']])
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
