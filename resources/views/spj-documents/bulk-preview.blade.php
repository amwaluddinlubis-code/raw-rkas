<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pratinjau Bulk Paket SPJ</title>
    <style>
        @page { size: A4; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 18px; color: #0f172a; font: 14px Arial, sans-serif; background: #e2e8f0; }
        .toolbar { position: sticky; top: 0; z-index: 2; display: flex; justify-content: space-between; align-items: center; gap: 16px; margin: -18px -18px 18px; padding: 12px 18px; background: #fff; border-bottom: 1px solid #cbd5e1; }
        .toolbar button { border: 0; border-radius: 6px; padding: 10px 16px; color: #fff; background: #1d4ed8; font-weight: 700; cursor: pointer; }
        .toolbar button:disabled { color: #475569; background: #cbd5e1; cursor: not-allowed; }
        .preflight { margin: 0 auto 18px; max-width: 210mm; padding: 14px 16px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; }
        .preflight-summary { display: flex; flex-wrap: wrap; gap: 8px 18px; margin-bottom: 12px; font-weight: 700; }
        .preflight-package { padding: 10px 12px; border-radius: 6px; }
        .preflight-package + .preflight-package { margin-top: 8px; }
        .preflight-package.ready { color: #166534; background: #f0fdf4; }
        .preflight-package.blocked { color: #9a3412; background: #fff7ed; }
        .preflight-package ul { margin: 6px 0 0; padding-left: 20px; font-weight: 400; }
        .package { margin: 0 auto 18px; padding: 18px; width: fit-content; max-width: 100%; background: #fff; box-shadow: 0 2px 10px #0f172a22; }
        .package-heading { margin: 0 auto 14px; max-width: 210mm; font-size: 13px; color: #475569; }
        .package-heading strong { color: #0f172a; }
        .spj-preview-page { margin: 0 auto 24px; width: max-content; max-width: none; }
        .spj-preview-page table { background: #fff; }
        @media print {
            body { padding: 0; background: #fff; }
            .toolbar { display: none; }
            .preflight { display: none; }
            .package { margin: 0; padding: 0; width: auto; max-width: none; box-shadow: none; break-after: page; page-break-after: always; }
            .package:last-child { break-after: auto; page-break-after: auto; }
            .package-heading { margin-bottom: 6mm; }
            .spj-preview-page { margin: 0; }
        }
    </style>
    @foreach ($packages as $package)
        {!! $package['styles'] !!}
    @endforeach
</head>
<body>
    <header class="toolbar">
        <div>
            <strong>Pratinjau {{ count($packages) }} paket SPJ</strong><br>
            <span>{{ $readyCount }} siap · {{ $blockedCount }} perlu dilengkapi</span>
            @if ($blockedCount > 0)
                <br><span>Lengkapi paket yang bermasalah sebelum mencetak seluruh pilihan.</span>
            @endif
        </div>
        <button type="button" onclick="window.print()" @disabled($blockedCount > 0)>
            {{ $blockedCount > 0 ? 'Cetak belum tersedia' : 'Cetak semua paket' }}
        </button>
    </header>
    <section class="preflight" aria-label="Pemeriksaan kesiapan paket">
        <div class="preflight-summary">
            <span>{{ $readyCount }} paket siap dicetak</span>
            <span>{{ $blockedCount }} paket perlu dilengkapi</span>
        </div>
        @foreach ($packages as $package)
            <article class="preflight-package {{ $package['ready'] ? 'ready' : 'blocked' }}">
                <strong>Nomor SPJ: {{ $package['number'] }} — {{ $package['ready'] ? 'Siap dicetak' : 'Perlu dilengkapi' }}</strong>
                @if (! $package['ready'])
                    <ul>
                        @foreach ($package['issues'] as $issue)
                            <li><strong>{{ $issue['label'] }}:</strong> {{ $issue['message'] }}</li>
                        @endforeach
                    </ul>
                @endif
            </article>
        @endforeach
    </section>
    @foreach ($packages as $package)
        <section class="package">
            <h2 class="package-heading">Nomor SPJ: <strong>{{ $package['number'] }}</strong></h2>
            {!! $package['html'] !!}
        </section>
    @endforeach
</body>
</html>
