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
        .rt-item.overlimit { border-left:4px solid var(--rt-danger); }
        .rt-overnote { color:#b91c1c; font-weight:600; }
        .dark .rt-overnote { color:#fecaca; }
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
        .rt-badge.credits.full { background:#fef3c7; color:var(--rt-warn); border-color:transparent; }
        .rt-badge.extra { background:#e0e7ff; color:#4338ca; }
        .rt-badge.walkin { background:#e0e7ff; color:#4338ca; }
        .rt-badge.carry { background:#fef3c7; color:var(--rt-warn); }
        .dark .rt-badge.pay-pending { background:#422006; }
        .dark .rt-badge.pay-overdue { background:#450a0a; color:#fecaca; }
        .dark .rt-badge.credits.over { background:#450a0a; color:#fecaca; }
        .dark .rt-badge.credits.full { background:#422006; }
        .dark .rt-badge.extra, .dark .rt-badge.walkin { background:#312e81; color:#c7d2fe; }
        .dark .rt-badge.carry { background:#422006; }
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
        .rt-sc-head:focus-visible { outline:2px solid var(--rt-accent); outline-offset:-2px; border-radius:12px; }
        [x-cloak] { display:none; }
        .rt-help-btn { font-size:.72rem; padding:.3rem .6rem; }
        .rt-help-overlay { position:fixed; inset:0; background:rgba(15,23,42,.5); display:flex; align-items:center; justify-content:center; z-index:100; padding:1.5rem; }
        .rt-help-card { background:var(--rt-bg); border:1px solid var(--rt-border); border-radius:14px; max-width:34rem; width:100%; max-height:80vh; overflow-y:auto; padding:1.25rem 1.5rem; box-shadow:0 20px 40px rgba(0,0,0,.25); position:relative; }
        .rt-help-close { position:absolute; top:.75rem; right:.75rem; border:0; background:transparent; color:var(--rt-muted); font-size:1.1rem; line-height:1; cursor:pointer; padding:.3rem; }
        .rt-help-title { font-size:1.05rem; font-weight:700; margin:0 0 .75rem; padding-right:1.5rem; }
        .rt-help-section { margin-top:1.1rem; }
        .rt-help-section h4 { font-size:.75rem; text-transform:uppercase; letter-spacing:.06em; color:var(--rt-muted); font-weight:700; margin:0 0 .5rem; }
        .rt-help-rules { margin:0; padding-left:1.1rem; display:flex; flex-direction:column; gap:.35rem; font-size:.83rem; }
        .rt-help-steps { margin:0; padding-left:1.1rem; display:flex; flex-direction:column; gap:.3rem; font-size:.83rem; }
        .rt-help-badges { display:flex; flex-direction:column; gap:.5rem; }
        .rt-help-badgerow { display:flex; align-items:center; gap:.6rem; font-size:.8rem; }
        .rt-help-badgerow .rt-badge, .rt-help-badgerow .rt-tag { flex-shrink:0; }
        .rt-help-foot { margin-top:1.1rem; font-size:.8rem; color:var(--rt-muted); border-top:1px dashed var(--rt-border); padding-top:.75rem; }
        .dark .rt-help-overlay { background:rgba(0,0,0,.65); }
        .dark .rt-help-card { box-shadow:0 20px 40px rgba(0,0,0,.5); }
    </style>

    <div class="rt" x-data="{ creditsHelp: false }" @keydown.escape.window="creditsHelp = false">
        @php($sessions = $this->sessionsOnDate)
        <div class="rt-head">
            <label class="rt-fieldgroup">
                <span class="rt-fieldlabel">Date</span>
                <div class="rt-datenav">
                    <input type="date" wire:model.live="date" @disabled($dirty) @if($dirty) title="Save or discard your changes first" @endif>
                    <span class="rt-weekday">{{ $date ? \Illuminate\Support\Carbon::parse($date)->format('D') : '' }}</span>
                    <button type="button" class="rt-btn ghost" wire:click="goToday" @disabled($dirty) @if($dirty) title="Save or discard your changes first" @endif>Today</button>
                </div>
            </label>
            <div class="rt-summary">
                <span>{{ count($sessions) }} session{{ count($sessions) === 1 ? '' : 's' }}@if(count($sessions)) · {{ collect($sessions)->where('recorded', true)->count() }} recorded @endif</span>
                <button type="button" class="rt-btn ghost rt-help-btn" @click="creditsHelp = true">? How credits work</button>
            </div>
        </div>

        <div class="rt-help-overlay" x-cloak x-show="creditsHelp" @click.self="creditsHelp = false" role="dialog" aria-modal="true" aria-label="How session credits work">
            <div class="rt-help-card">
                <button type="button" class="rt-help-close" @click="creditsHelp = false" aria-label="Close">✕</button>
                <h3 class="rt-help-title">How session credits work</h3>

                <div class="rt-help-section">
                    <h4>The five rules</h4>
                    <ol class="rt-help-rules">
                        <li>Each month's registration buys N sessions ("credits" — usually 4).</li>
                        <li>Attending your own weekly class uses this month's credit — present, late and absent all use one (the spot was held); excused does not.</li>
                        <li>Unused credits never disappear — unused sessions from previous months become "carried" credits.</li>
                        <li>Carried credits are spent by joining an extra session in the same program (any day, same class/program): free as a make-up, oldest credits first. Spendable in the same program only, and only up to the session's own month — a future month's prepaid credits never fund a make-up today. With no matching-program credits left, they pay the walk-in fee.</li>
                        <li>Regular monthly sessions never touch carried credits — this month's fee covers this month's classes.</li>
                    </ol>
                </div>

                <div class="rt-help-section">
                    <h4>Worked example — Adam</h4>
                    <ol class="rt-help-steps">
                        <li>June: pays for 4, attends 2 → 2 credits carried.</li>
                        <li>July: pays for 4 again — every Saturday uses a July credit, June leftovers untouched.</li>
                        <li>One Wednesday he joins another Group Training slot as an extra → free make-up, uses 1 June credit (a Goalkeeper session instead would have been a walk-in — his carried credits are Group Training credits).</li>
                        <li>After 4 Saturdays: <span class="rt-badge credits full">4/4 · paid up</span> — a 5th shows <span class="rt-badge credits over">+1 over</span>, allowed but renewal is due.</li>
                    </ol>
                </div>

                <div class="rt-help-section">
                    <h4>Badge cheat-sheet</h4>
                    <div class="rt-help-badges">
                        <div class="rt-help-badgerow"><span class="rt-badge credits">2/4</span> <span>in progress — 2 of 4 paid sessions used.</span></div>
                        <div class="rt-help-badgerow"><span class="rt-badge credits full">4/4 · paid up</span> <span>all paid sessions used — renewal due soon.</span></div>
                        <div class="rt-help-badgerow"><span class="rt-badge credits over">5/4 · +1 over</span> <span>over-delivered — never blocked, just flagged.</span></div>
                        <div class="rt-help-badgerow"><span class="rt-badge carry">+2 carried</span> <span>unused past sessions of this program, usable as free make-ups.</span></div>
                        <div class="rt-help-badgerow"><span class="rt-badge extra">make-up</span> <span>extra session paid by a carried credit.</span></div>
                        <div class="rt-help-badgerow"><span class="rt-badge walkin">walk-in · RM40</span> <span>extra session, no credits left — pays the fee.</span></div>
                    </div>
                </div>

                <div class="rt-help-foot">Credits belong to the program they were bought for — no credits for that program means the walk-in fee. Nothing is ever blocked; over-limit sessions are simply flagged for renewal.</div>
            </div>
        </div>

        <div class="rt-sessions">
            <div class="rt-listlabel">Sessions on {{ $date ? \Illuminate\Support\Carbon::parse($date)->format('l, j M') : 'this day' }}</div>

            @forelse($sessions as $s)
                @php($isOpen = $offeringId === $s['id'] && ! $creatingSession)
                <div class="rt-sc @if($isOpen) open @elseif($dirty) locked @endif" @if(! $isOpen && $dirty) title="Save or discard your changes first" @endif>
                    <div class="rt-sc-head" role="button" tabindex="0" aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                        wire:click="toggleSession({{ $s['id'] }})"
                        wire:keydown.enter="toggleSession({{ $s['id'] }})"
                        wire:keydown.space.prevent="toggleSession({{ $s['id'] }})">
                        <span class="rt-sc-chev">▸</span>
                        <span class="rt-sc-title">{{ $s['program'] }} <span class="rt-sc-time">· {{ $s['time'] }}</span></span>
                        <span class="rt-sc-spacer"></span>
                        <span class="rt-sc-meta">@if($s['coach']){{ $s['coach'] }} · @endif{{ $s['recorded'] ? $s['attended'].' attended' : $s['enrolled'].' enrolled' }}</span>
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

            <div class="rt-sc new @if($creatingSession) open @elseif($dirty) locked @endif" @if(! $creatingSession && $dirty) title="Save or discard your changes first" @endif>
                <div class="rt-sc-head" role="button" tabindex="0" aria-expanded="{{ $creatingSession ? 'true' : 'false' }}"
                    wire:click="toggleNewSession"
                    wire:keydown.enter="toggleNewSession"
                    wire:keydown.space.prevent="toggleNewSession">
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
