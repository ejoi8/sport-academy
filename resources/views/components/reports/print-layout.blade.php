@props(['title', 'subtitle' => null])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        :root { --ink:#1f2937; --muted:#6b7280; --line:#e5e7eb; --accent:#16a34a; }
        * { box-sizing:border-box; }
        body { font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:var(--ink); margin:0; background:#f3f4f6; }
        .sheet { max-width:800px; margin:1.5rem auto; background:#fff; padding:2.25rem; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
        .topbar { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid var(--accent); padding-bottom:1rem; margin-bottom:1.5rem; }
        .topbar h1 { font-size:1.35rem; margin:0; }
        .topbar .sub { color:var(--muted); font-size:.85rem; margin-top:.25rem; }
        .academy { text-align:right; font-weight:700; color:var(--accent); }
        .academy .gen { display:block; font-weight:400; color:var(--muted); font-size:.75rem; margin-top:.25rem; }
        h2 { font-size:.95rem; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); margin:1.75rem 0 .6rem; }
        .cards { display:flex; gap:.75rem; flex-wrap:wrap; }
        .card { flex:1; min-width:120px; border:1px solid var(--line); border-radius:6px; padding:.75rem .9rem; }
        .card .n { font-size:1.4rem; font-weight:700; }
        .card .l { font-size:.72rem; color:var(--muted); text-transform:uppercase; letter-spacing:.03em; }
        .card.good .n { color:var(--accent); }
        .card.warn .n { color:#b45309; }
        .muted { color:var(--muted); }
        table { width:100%; border-collapse:collapse; font-size:.9rem; margin-top:.5rem; }
        th, td { text-align:left; padding:.5rem .6rem; border-bottom:1px solid var(--line); }
        th { color:var(--muted); font-weight:600; font-size:.78rem; text-transform:uppercase; letter-spacing:.03em; }
        td.num, th.num { text-align:right; font-variant-numeric:tabular-nums; }
        tfoot td { font-weight:700; border-top:2px solid var(--line); border-bottom:none; }
        .printbar { margin-top:2rem; text-align:right; }
        .printbar button { background:var(--accent); color:#fff; border:0; border-radius:6px; padding:.5rem 1rem; font-size:.85rem; cursor:pointer; }
        @media print { body { background:#fff; } .sheet { box-shadow:none; margin:0; max-width:none; } .printbar { display:none; } }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="topbar">
            <div>
                <h1>{{ $title }}</h1>
                @if($subtitle)<div class="sub">{{ $subtitle }}</div>@endif
            </div>
            <div class="academy">{{ config('app.name') ?: 'Academy' }}<span class="gen">Generated {{ now()->format('j M Y, H:i') }}</span></div>
        </div>

        {{ $slot }}

        <div class="printbar"><button onclick="window.print()">Print</button></div>
    </div>
</body>
</html>
