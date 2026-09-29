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
        .package { margin: 0 auto 18px; padding: 18px; width: fit-content; max-width: 100%; background: #fff; box-shadow: 0 2px 10px #0f172a22; }
        .package-heading { margin: 0 auto 14px; max-width: 210mm; font-size: 13px; color: #475569; }
        .package-heading strong { color: #0f172a; }
        .spj-preview-page { margin: 0 auto 24px; width: max-content; max-width: none; }
        .spj-preview-page table { background: #fff; }
        @media print {
            body { padding: 0; background: #fff; }
            .toolbar { display: none; }
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
        <div><strong>Pratinjau {{ count($packages) }} paket SPJ</strong><br><span>Pilih Cetak untuk mencetak semua paket dalam urutan baris terpilih.</span></div>
        <button type="button" onclick="window.print()">Cetak</button>
    </header>
    @foreach ($packages as $package)
        <section class="package">
            <h2 class="package-heading">Nomor SPJ: <strong>{{ $package['number'] }}</strong></h2>
            {!! $package['html'] !!}
        </section>
    @endforeach
</body>
</html>
