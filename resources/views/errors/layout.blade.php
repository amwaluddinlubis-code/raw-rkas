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
    <style>
        :root {
            --err-bg: #0d1a31;
            --err-bg-deep: #081225;
            --err-surface: #132340;
            --err-line: #263b5d;
            --err-text: #f1f5f9;
            --err-muted: #9aabc0;
            --err-accent: #38c5d6;
            --err-action: #0f4fc4;
            --err-action-hover: #1769e8;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
        }

        body {
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 32px 20px;
            color: var(--err-text);
            background:
                radial-gradient(640px 340px at 50% 18%, rgb(56 197 214 / .14), transparent 70%),
                radial-gradient(900px 600px at 50% 110%, rgb(15 79 196 / .22), transparent 70%),
                linear-gradient(180deg, var(--err-bg) 0%, var(--err-bg-deep) 100%);
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", Inter, "Segoe UI", system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        .err-card {
            width: 100%;
            max-width: 560px;
            text-align: center;
        }

        .err-mark {
            display: inline-grid;
            place-items: center;
            width: 56px;
            height: 56px;
            border: 1px solid var(--err-line);
            border-radius: 16px;
            background: rgb(19 35 64 / .72);
            color: var(--err-accent);
            font-size: 26px;
            font-weight: 700;
            box-shadow: 0 12px 32px rgb(0 0 0 / .35);
        }

        .err-code {
            margin: 28px 0 0;
            font-size: clamp(72px, 18vw, 128px);
            font-weight: 200;
            letter-spacing: -.04em;
            line-height: 1;
            background: linear-gradient(180deg, #ffffff 30%, var(--err-muted));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .err-title {
            margin: 12px 0 0;
            font-size: 21px;
            font-weight: 600;
            letter-spacing: -.01em;
        }

        .err-hint {
            margin: 10px auto 0;
            max-width: 42ch;
            color: var(--err-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .err-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: center;
            margin-top: 30px;
        }

        .err-btn {
            display: inline-block;
            min-width: 148px;
            padding: 12px 22px;
            border: 0;
            border-radius: 999px;
            background: var(--err-action);
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: background-color .15s ease, transform .15s ease;
        }

        .err-btn:hover {
            background: var(--err-action-hover);
            transform: translateY(-1px);
        }

        .err-btn:focus-visible {
            outline: 2px solid var(--err-accent);
            outline-offset: 3px;
        }

        .err-meta {
            margin-top: 34px;
            color: var(--err-muted);
            font-size: 12px;
            letter-spacing: .02em;
        }

        @media (prefers-reduced-motion: reduce) {
            .err-btn {
                transition: none;
            }

            .err-btn:hover {
                transform: none;
            }
        }
    </style>
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
