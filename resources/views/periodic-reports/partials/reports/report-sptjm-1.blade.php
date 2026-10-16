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
