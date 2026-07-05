@php
    $present = $attendance['present'] ?? 0;
    $late = $attendance['late'] ?? 0;
    $absent = $attendance['absent'] ?? 0;
    $excused = $attendance['excused'] ?? 0;
    $total = array_sum($attendance);
    $attended = $present + $late;
    $overall = $summary->isNotEmpty() ? round($summary->avg('average'), 1) : null;

    $creditLine = 'Sessions: purchased '.$credits['purchased'].' · attended '.$credits['attended'];
    if ($credits['owed'] > 0) {
        $creditLine .= ' · owed '.$credits['owed'];
    }
    if ($credits['over'] > 0) {
        $creditLine .= ' · over '.$credits['over'];
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $student->name }} — Progress Report</title>
    <style>
        :root { --ink:#1f2937; --muted:#6b7280; --line:#e5e7eb; --accent:#16a34a; --soft:#f0fdf4; }
        * { box-sizing:border-box; }
        body { font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:var(--ink); margin:0; background:#f3f4f6; }
        .sheet { max-width:800px; margin:1.5rem auto; background:#fff; padding:2.25rem; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
        .topbar { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid var(--accent); padding-bottom:1rem; margin-bottom:1.5rem; }
        .topbar h1 { font-size:1.35rem; margin:0; }
        .topbar .sub { color:var(--muted); font-size:.85rem; margin-top:.25rem; }
        .academy { text-align:right; font-weight:700; color:var(--accent); }
        .academy .gen { display:block; font-weight:400; color:var(--muted); font-size:.75rem; margin-top:.25rem; }
        h2 { font-size:.95rem; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); margin:1.75rem 0 .6rem; }
        .grid { display:grid; grid-template-columns:repeat(2,1fr); gap:.35rem 1.5rem; font-size:.9rem; }
        .grid .k { color:var(--muted); }
        .cards { display:flex; gap:.75rem; flex-wrap:wrap; }
        .card { flex:1; min-width:90px; border:1px solid var(--line); border-radius:6px; padding:.6rem .75rem; text-align:center; }
        .card .n { font-size:1.4rem; font-weight:700; }
        .card .l { font-size:.72rem; color:var(--muted); text-transform:uppercase; letter-spacing:.03em; }
        table { width:100%; border-collapse:collapse; font-size:.9rem; }
        th, td { text-align:left; padding:.5rem .5rem; border-bottom:1px solid var(--line); }
        th { font-size:.72rem; text-transform:uppercase; letter-spacing:.03em; color:var(--muted); }
        td.num, th.num { text-align:center; white-space:nowrap; }
        .cat { background:var(--soft); font-weight:700; font-size:.8rem; }
        .bar { position:relative; height:8px; background:var(--line); border-radius:999px; overflow:hidden; width:90px; display:inline-block; vertical-align:middle; }
        .bar > span { position:absolute; inset:0 auto 0 0; background:var(--accent); }
        .muted { color:var(--muted); }
        .toolbar { max-width:800px; margin:1.5rem auto -0.5rem; text-align:right; }
        .btn { background:var(--accent); color:#fff; border:0; padding:.5rem 1rem; border-radius:6px; font-weight:600; cursor:pointer; }
        .empty { color:var(--muted); font-style:italic; padding:.5rem 0; }
        @media print {
            body { background:#fff; }
            .sheet { box-shadow:none; margin:0; max-width:none; border-radius:0; padding:0; }
            .toolbar { display:none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <div class="sheet">
        <div class="topbar">
            <div>
                <h1>Student Progress Report</h1>
                <div class="sub">{{ $student->name }}@if($student->age) · {{ $student->age }} yrs @endif @if($student->ic_number) · {{ $student->ic_number }}@endif</div>
                <div class="sub">{{ $creditLine }}</div>
            </div>
            <div class="academy">
                {{ config('app.name') }}
                <span class="gen">Generated {{ now()->format('j M Y') }}</span>
            </div>
        </div>

        <h2>Student</h2>
        <div class="grid">
            <div><span class="k">Name:</span> {{ $student->name }}</div>
            <div><span class="k">Gender:</span> {{ $student->gender?->getLabel() ?? '—' }}</div>
            <div><span class="k">Guardian:</span> {{ $student->guardian_name ?? '—' }}</div>
            <div><span class="k">Phone:</span> {{ $student->guardian_phone ?? '—' }}</div>
            <div><span class="k">Parent account:</span> {{ $student->parent?->name ?? '—' }}</div>
            <div><span class="k">Date of birth:</span> {{ $student->dob?->format('j M Y') ?? '—' }}</div>
        </div>

        <h2>Attendance</h2>
        <div class="cards">
            <div class="card"><div class="n">{{ $total }}</div><div class="l">Sessions</div></div>
            <div class="card"><div class="n">{{ $attended }}</div><div class="l">Attended</div></div>
            <div class="card"><div class="n">{{ $late }}</div><div class="l">Late</div></div>
            <div class="card"><div class="n">{{ $absent }}</div><div class="l">Absent</div></div>
            <div class="card"><div class="n">{{ $excused }}</div><div class="l">Excused</div></div>
        </div>

        <h2>Skills assessment @if($overall !== null)<span class="muted">· overall avg {{ $overall }} / 5</span>@endif</h2>
        @if($summary->isEmpty())
            <div class="empty">No assessments recorded yet.</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Skill</th>
                        <th class="num">Times</th>
                        <th class="num">Latest</th>
                        <th>Average</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($summary->groupBy('category') as $category => $skills)
                        <tr><td class="cat" colspan="4">{{ $category }}</td></tr>
                        @foreach($skills as $row)
                            <tr>
                                <td>{{ $row['skill'] }}</td>
                                <td class="num">{{ $row['count'] }}</td>
                                <td class="num">{{ $row['latest'] ?? '—' }}</td>
                                <td>
                                    <span class="bar"><span style="width: {{ ($row['average'] / 5) * 100 }}%"></span></span>
                                    <strong>{{ $row['average'] }}</strong> <span class="muted">/ 5</span>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</body>
</html>
