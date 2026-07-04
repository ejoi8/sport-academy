<x-filament::page>
    @php($skills = $this->skills)
    @php($suggestion = $this->suggestion)
    @php($coachOptions = $this->coachOptions)

    <style>
        .rt { --rt-border:#e5e7eb; --rt-bg:#fff; --rt-soft:#f9fafb; --rt-muted:#6b7280; --rt-accent:#16a34a; --rt-accent-soft:#dcfce7; --rt-danger:#dc2626; --rt-warn:#b45309; display:flex; flex-direction:column; gap:1rem; padding-bottom:5rem; }
        .dark .rt { --rt-border:#374151; --rt-bg:#1f2937; --rt-soft:#111827; --rt-muted:#9ca3af; --rt-accent:#22c55e; --rt-accent-soft:#064e3b; }
        .rt input, .rt select, .rt textarea { border:1px solid var(--rt-border); border-radius:.5rem; padding:.4rem .6rem; background:var(--rt-bg); color:inherit; font-size:.85rem; }
        .rt select:disabled, .rt input:disabled { opacity:.55; cursor:not-allowed; }
        .rt-head { display:flex; flex-wrap:wrap; gap:.75rem; align-items:center; justify-content:space-between; }
        .rt-controls { display:flex; flex-wrap:wrap; gap:.9rem; align-items:flex-end; }
        .rt-fieldgroup { display:flex; flex-direction:column; gap:.25rem; }
        .rt-fieldlabel { font-size:.64rem; text-transform:uppercase; letter-spacing:.08em; color:var(--rt-muted); font-weight:700; }
        .rt-datenav { display:inline-flex; align-items:center; gap:.4rem; }
        .rt-weekday { font-size:.75rem; color:var(--rt-muted); min-width:1.6rem; text-align:center; }
        .rt-summary { font-size:.8rem; color:var(--rt-muted); display:flex; gap:.5rem; align-items:center; }
        .rt-dirty { color:var(--rt-warn); font-weight:600; }
        .rt-saved { color:var(--rt-accent); font-weight:600; }
        .rt-list, .rt-added { display:flex; flex-direction:column; gap:.5rem; }
        .rt-item { border:1px solid var(--rt-border); border-radius:.75rem; background:var(--rt-bg); overflow:hidden; }
        .rt-item.open { border-color:var(--rt-accent); box-shadow:0 0 0 1px var(--rt-accent) inset; }
        .rt-item.done { border-left:4px solid var(--rt-accent); }
        .rt-item.done .rt-row { background:var(--rt-accent-soft); }
        .rt-item.excused { border-left:4px solid var(--rt-border); }
        .rt-item.excused .rt-row { opacity:.6; }
        .rt-done { color:var(--rt-accent); font-weight:700; }
        .rt-row { display:flex; align-items:center; gap:.75rem; padding:.7rem 1rem; cursor:pointer; }
        .rt-row:focus-visible { outline:2px solid var(--rt-accent); outline-offset:-2px; }
        .rt-row .chev { color:var(--rt-muted); transition:transform .15s; }
        .rt-item.open .rt-row .chev { transform:rotate(180deg); }
        .rt-name { font-weight:600; flex:1; display:flex; flex-direction:column; gap:.1rem; min-width:0; }
        .rt-sub { font-size:.7rem; color:var(--rt-muted); font-weight:400; }
        .rt-badge { font-size:.68rem; padding:.15rem .5rem; border-radius:999px; font-weight:700; white-space:nowrap; }
        .rt-badge.pay-active { background:var(--rt-accent-soft); color:var(--rt-accent); }
        .rt-badge.pay-pending { background:#fef3c7; color:var(--rt-warn); }
        .rt-badge.pay-overdue { background:#fee2e2; color:#b91c1c; }
        .rt-badge.credits { background:var(--rt-soft); color:var(--rt-muted); border:1px solid var(--rt-border); }
        .rt-badge.credits.over { background:#fee2e2; color:#b91c1c; border-color:transparent; }
        .rt-badge.extra { background:#e0e7ff; color:#4338ca; }
        .rt-badge.walkin { background:#e0e7ff; color:#4338ca; }
        .dark .rt-badge.pay-pending { background:#422006; }
        .dark .rt-badge.pay-overdue { background:#450a0a; color:#fecaca; }
        .dark .rt-badge.credits.over { background:#450a0a; color:#fecaca; }
        .dark .rt-badge.extra, .dark .rt-badge.walkin { background:#312e81; color:#c7d2fe; }
        .rt-rowmeta { font-size:.73rem; color:var(--rt-muted); white-space:nowrap; }
        .rt-remove { border:0; background:transparent; color:var(--rt-muted); cursor:pointer; font-size:1rem; line-height:1; padding:.25rem; }
        .rt-card { padding:1rem; border-top:1px dashed var(--rt-border); background:var(--rt-soft); display:flex; flex-direction:column; gap:1rem; }
        .rt-cardtop { display:flex; flex-wrap:wrap; gap:1rem; align-items:center; }
        .rt-field { display:inline-flex; align-items:center; gap:.4rem; font-size:.8rem; color:var(--rt-muted); }
        .rt-att { display:flex; gap:.5rem; flex-wrap:wrap; }
        .rt-att button { border:1px solid var(--rt-border); border-radius:.5rem; padding:.4rem 1rem; background:var(--rt-bg); cursor:pointer; font-size:.8rem; color:inherit; }
        .rt-att button.sel { border-color:var(--rt-accent); background:var(--rt-accent); color:#fff; }
        .rt-att button.sel.absent { border-color:var(--rt-danger); background:var(--rt-danger); }
        .rt-att button.sel.late, .rt-att button.sel.excused { border-color:var(--rt-warn); background:var(--rt-warn); }
        .rt-skills { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:.55rem 1.5rem; }
        .rt-skill { display:flex; align-items:center; justify-content:space-between; gap:.75rem; }
        .rt-skill .n { font-size:.83rem; }
        .rt-pills { display:inline-flex; gap:.25rem; }
        .rt-pill { width:2rem; height:2rem; border:1px solid var(--rt-border); border-radius:.5rem; background:var(--rt-bg); cursor:pointer; font-size:.82rem; color:inherit; }
        .rt-pill.sel { border-color:var(--rt-accent); background:var(--rt-accent); color:#fff; font-weight:700; }
        .rt-pill:disabled { opacity:.4; cursor:not-allowed; }
        .rt-note { width:100%; min-height:2.5rem; resize:vertical; }
        .rt-section { border-top:1px solid var(--rt-border); padding-top:1rem; display:flex; flex-direction:column; gap:.75rem; }
        .rt-section-head { display:flex; align-items:center; justify-content:space-between; }
        .rt-section-title { font-weight:600; font-size:.9rem; }
        .rt-btn { border:1px solid var(--rt-accent); color:var(--rt-accent); background:transparent; border-radius:.5rem; padding:.35rem .75rem; cursor:pointer; font-size:.78rem; font-weight:600; }
        .rt-btn.ghost { border-color:var(--rt-border); color:var(--rt-muted); }
        .rt-btn:disabled { opacity:.4; cursor:not-allowed; }
        .rt-add { border:1px dashed var(--rt-border); border-radius:.75rem; padding:1rem; background:var(--rt-soft); display:flex; flex-direction:column; gap:.75rem; }
        .rt-results { display:flex; flex-direction:column; gap:.3rem; }
        .rt-result { display:flex; align-items:center; gap:.6rem; padding:.5rem .65rem; border:1px solid var(--rt-border); border-radius:.5rem; background:var(--rt-bg); font-size:.8rem; }
        .rt-result .meta { flex:1; }
        .rt-tag { font-size:.68rem; padding:.12rem .45rem; border-radius:999px; background:#eef2ff; color:#4338ca; white-space:nowrap; }
        .dark .rt-tag { background:#312e81; color:#c7d2fe; }
        .rt-muted { color:var(--rt-muted); font-size:.8rem; }
        .rt-new { border-top:1px dashed var(--rt-border); padding-top:.75rem; display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; }
        .rt-warn { width:100%; font-size:.78rem; color:var(--rt-warn); background:#fffbeb; border:1px solid #fde68a; border-radius:.5rem; padding:.4rem .6rem; }
        .dark .rt-warn { background:#2a1e05; border-color:#78550c; }
        .rt-callout { border:1px dashed var(--rt-border); border-radius:.75rem; padding:1.25rem; text-align:center; color:var(--rt-muted); font-size:.85rem; }
        .rt-newsession { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; padding:.6rem .75rem; border:1px solid var(--rt-border); border-radius:.6rem; background:var(--rt-bg); font-size:.82rem; }
        .rt-savebar { position:sticky; bottom:0; display:flex; justify-content:flex-end; gap:.75rem; padding:.75rem 0; background:linear-gradient(to top, var(--rt-bg) 55%, transparent); }
        .rt-save { background:var(--rt-accent); color:#fff; border:0; border-radius:.6rem; padding:.65rem 1.6rem; font-weight:700; cursor:pointer; box-shadow:0 6px 16px rgba(0,0,0,.18); }
        .rt-save:disabled { opacity:.5; cursor:not-allowed; box-shadow:none; }
        .rt-delete { border:1px solid var(--rt-danger); color:var(--rt-danger); background:transparent; border-radius:.6rem; padding:.65rem 1.2rem; font-weight:600; cursor:pointer; }
        /* session accordion */
        .rt-sessions { display:flex; flex-direction:column; gap:.6rem; }
        .rt-listlabel { font-size:.7rem; text-transform:uppercase; letter-spacing:.08em; color:var(--rt-muted); font-weight:700; }
        .rt-sc { border:1px solid var(--rt-border); border-radius:12px; background:var(--rt-bg); }
        .rt-sc.open { border-color:var(--rt-accent); box-shadow:0 0 0 1px var(--rt-accent) inset; }
        .rt-sc.locked { opacity:.5; pointer-events:none; }
        .rt-sc-head { display:flex; align-items:center; gap:.7rem; padding:.8rem 1rem; cursor:pointer; }
        .rt-sc-chev { color:var(--rt-muted); font-size:.8rem; transition:transform .15s; width:.9rem; text-align:center; }
        .rt-sc.open .rt-sc-chev { transform:rotate(90deg); color:var(--rt-accent); }
        .rt-sc-title { font-weight:650; }
        .rt-sc-time { color:var(--rt-muted); font-weight:500; }
        .rt-sc-spacer { flex:1; }
        .rt-sc-meta { font-size:.77rem; color:var(--rt-muted); white-space:nowrap; }
        .rt-status { display:inline-flex; align-items:center; gap:.35rem; font-size:.68rem; font-weight:700; padding:.2rem .55rem; border-radius:999px; white-space:nowrap; }
        .rt-status .led { width:.42rem; height:.42rem; border-radius:50%; }
        .rt-status.saved { background:var(--rt-accent-soft); color:var(--rt-accent); }
        .rt-status.saved .led { background:var(--rt-accent); }
        .rt-status.pending { background:var(--rt-soft); color:var(--rt-muted); border:1px solid var(--rt-border); }
        .rt-status.pending .led { background:#cbd5e1; }
        .rt-sc-body { border-top:1px dashed var(--rt-border); padding:1rem; display:flex; flex-direction:column; gap:.9rem; background:var(--rt-soft); }
        .rt-sc.new .rt-sc-head { color:var(--rt-accent); font-weight:600; }
        .rt-sc.new .rt-sc-chev.plus { color:var(--rt-accent); font-weight:800; transform:none; }
        /* light coach strip */
        .rt-coachstrip { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; font-size:.8rem; color:var(--rt-muted); }
        .rt-coachstrip .rt-cs-label { font-weight:600; color:inherit; }
        .rt-cs-sep { width:1px; height:1.1rem; background:var(--rt-border); }
        .rt-linkbtn { border:0; background:transparent; color:var(--rt-accent); font-weight:600; font-size:.8rem; cursor:pointer; padding:0; }
        .rt-linkbtn.muted { color:var(--rt-muted); }
        .rt-linkbtn:disabled { opacity:.4; cursor:not-allowed; }
    </style>

    <div class="rt">
        @php($sessions = $this->sessionsOnDate)
        <div class="rt-head">
            <label class="rt-fieldgroup">
                <span class="rt-fieldlabel">Date</span>
                <div class="rt-datenav">
                    <input type="date" wire:model.live="date" @disabled($dirty)>
                    <span class="rt-weekday">{{ $date ? \Illuminate\Support\Carbon::parse($date)->format('D') : '' }}</span>
                    <button type="button" class="rt-btn ghost" wire:click="goToday" @disabled($dirty)>Today</button>
                </div>
            </label>
            <div class="rt-summary">
                <span>{{ count($sessions) }} session{{ count($sessions) === 1 ? '' : 's' }}@if(count($sessions)) · {{ collect($sessions)->where('recorded', true)->count() }} recorded @endif</span>
            </div>
        </div>

        <div class="rt-sessions">
            <div class="rt-listlabel">Sessions on {{ $date ? \Illuminate\Support\Carbon::parse($date)->format('l, j M') : 'this day' }}</div>

            @forelse($sessions as $s)
                @php($isOpen = $offeringId === $s['id'] && ! $creatingSession)
                <div class="rt-sc @if($isOpen) open @elseif($dirty) locked @endif">
                    <div class="rt-sc-head" wire:click="toggleSession({{ $s['id'] }})">
                        <span class="rt-sc-chev">▸</span>
                        <span class="rt-sc-title">{{ $s['program'] }} <span class="rt-sc-time">· {{ $s['time'] }}</span></span>
                        <span class="rt-sc-spacer"></span>
                        <span class="rt-sc-meta">{{ $s['enrolled'] }} enrolled</span>
                        @if($s['recorded'])
                            <span class="rt-status saved"><span class="led"></span>Saved</span>
                        @else
                            <span class="rt-status pending"><span class="led"></span>Not recorded</span>
                        @endif
                    </div>
                    @if($isOpen)
                        <div class="rt-sc-body">
                            @include('filament.pages.partials.run-training-recorder')
                        </div>
                    @endif
                </div>
            @empty
                <div class="rt-callout">No sessions scheduled on this day.</div>
            @endforelse

            <div class="rt-sc new @if($creatingSession) open @elseif($dirty) locked @endif">
                <div class="rt-sc-head" wire:click="toggleNewSession">
                    <span class="rt-sc-chev plus">＋</span>
                    <span>Create new session</span>
                    <span class="rt-sc-spacer"></span>
                    <span class="rt-sc-meta">off-schedule / one-off clinic</span>
                </div>
                @if($creatingSession)
                    <div class="rt-sc-body">
                        @include('filament.pages.partials.run-training-recorder')
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament::page>
