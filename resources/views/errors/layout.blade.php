@php
    $code = $code ?? '500';
    $title = $title ?? 'Terjadi kesalahan.';
    $hint = $hint ?? 'Coba muat ulang halaman, atau kembali ke halaman utama.';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>{{ $code }} · {{ $title }} — APP SPJ-BOSP</title>

</head>
<body>
    <main class="err-card">
        <div class="err-mark" aria-hidden="true">§</div>
        <p class="err-code">{{ $code }}</p>
        <h1 class="err-title">{{ $title }}</h1>
        <p class="err-hint">{{ $hint }}</p>
        <div class="err-actions">
            <a class="err-btn" href="{{ url('/') }}">Halaman Utama</a>
        </div>
        <p class="err-meta">APP SPJ-BOSP</p>
    </main>
</body>
</html>
