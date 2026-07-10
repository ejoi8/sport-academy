<x-filament::page>
    @php($skills = $this->skills)
    @php($suggestion = $this->suggestion)
    @php($coachOptions = $this->coachOptions)
    @php($sessions = $this->sessionsOnDate)
    @php($sel = \Illuminate\Support\Carbon::parse($date ?: today()->toDateString()))
    @php($onRecorder = $offeringId || $creatingSession)
    @php($openSession = $offeringId ? collect($sessions)->firstWhere('id', $offeringId) : null)
    @php($cycle = ['present' => 'late', 'late' => 'absent', 'absent' => 'excused', 'excused' => 'present'])
    @php($statusWord = ['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'excused' => 'Excused'])

    <style>
        .rt{ --b:#e8edf3; --bs:#e2e8f0; --bg:#fff; --soft:#f6f8fb; --mut:#94a3b8; --sub:#64748b; --ink:#0f172a;
             --ac:#2563eb; --ac2:#1d4ed8; --acs:#eff4fe; --ok:#15803d; --oks:#ecfdf3; --wa:#b45309; --was:#fef6e7;
             --da:#b91c1c; --das:#fef2f2; --vi:#6d28d9; --vis:#f5f1fe;
             color:var(--ink); max-width:34rem; margin:0 auto; display:flex; flex-direction:column; gap:.75rem; padding-bottom:1.5rem; }
        /* Fill the visible page height on the recorder screen so the sticky action bar has a
           bottom to pin to even when the roster is short — glued-to-bottom, but still aligned to
           the column. The ~9rem offset ≈ Filament topbar + page padding; tweak if it over/undershoots. */
        .rt-recording{ min-height:calc(100dvh - 9rem); }
        .dark .rt{ --b:#262d38; --bs:#333c49; --bg:#161b22; --soft:#0f141b; --mut:#6b7688; --sub:#9aa4b5; --ink:#e6e9ef;
             --ac:#5b8bff; --ac2:#3b6cf0; --acs:#182036; --ok:#3fbf98; --oks:#10281f; --wa:#e2ab5b; --was:#2a2114;
             --da:#f08a8a; --das:#301616; --vi:#b394f5; --vis:#241a38; }
        .rt input,.rt select,.rt textarea{ border:1.5px solid var(--bs); border-radius:.75rem; padding:.5rem .7rem; background:var(--bg); color:inherit; font-size:.9rem; width:100%; }
        .rt input:focus,.rt select:focus,.rt textarea:focus{ outline:none; border-color:var(--ac); box-shadow:0 0 0 3px color-mix(in srgb,var(--ac) 18%,transparent); }
        .rt select:disabled,.rt input:disabled{ opacity:.55; }

        /* ---------- app bar ---------- */
        .rt-bar{ position:sticky; top:0; z-index:20; display:flex; align-items:center; gap:.6rem; padding:.7rem .25rem; background:linear-gradient(to bottom,var(--soft) 78%,transparent); }
        .rt-bar h1{ font-size:1.15rem; font-weight:800; letter-spacing:-.02em; margin:0; flex:1; min-width:0; }
        .rt-bar .rt-crumb{ font-size:.72rem; font-weight:700; color:var(--mut); }
        .rt-iconbtn{ display:grid; place-items:center; width:2.5rem; height:2.5rem; border-radius:.8rem; border:1px solid var(--b); background:var(--bg); color:var(--sub); cursor:pointer; box-shadow:0 1px 3px rgba(15,23,42,.05); flex:none; }
        .rt-iconbtn:disabled{ opacity:.4; cursor:not-allowed; }
        .rt-iconbtn svg{ width:1.15rem; height:1.15rem; stroke:currentColor; fill:none; stroke-width:2.2; }
        .rt-textbtn{ border:0; background:var(--acs); color:var(--ac2); font-weight:800; font-size:.78rem; padding:.5rem .8rem; border-radius:.7rem; cursor:pointer; flex:none; }
        .rt-cal{ position:relative; overflow:hidden; }
        .rt-cal input{ position:absolute; inset:0; opacity:0; cursor:pointer; padding:0; border:0; }

        /* ---------- week strip ---------- */
        .rt-week{ display:flex; align-items:center; gap:.35rem; }
        .rt-days{ display:flex; gap:.4rem; flex:1; overflow-x:auto; scrollbar-width:none; padding:.15rem 0; }
        .rt-days::-webkit-scrollbar{ display:none; }
        .rt-day{ flex:1 0 auto; min-width:2.9rem; display:flex; flex-direction:column; align-items:center; gap:.15rem; padding:.55rem .3rem; border-radius:.9rem; border:1px solid var(--b); background:var(--bg); cursor:pointer; }
        .rt-day .wd{ font-size:.58rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:var(--mut); }
        .rt-day .dn{ font-size:1.05rem; font-weight:800; font-variant-numeric:tabular-nums; }
        .rt-day .dot{ width:.3rem; height:.3rem; border-radius:50%; background:var(--ac); }
        .rt-day.on{ border-color:transparent; background-image:linear-gradient(150deg,var(--ac),var(--ac2)); box-shadow:0 8px 18px -8px color-mix(in srgb,var(--ac) 60%,transparent); }
        .rt-day.on .wd,.rt-day.on .dn{ color:#fff; }
        .rt-day.on .dot{ background:#fff; }

        /* ---------- session cards (list) ---------- */
        .rt-list{ display:flex; flex-direction:column; gap:.6rem; }
        .rt-listlabel{ font-size:.66rem; text-transform:uppercase; letter-spacing:.1em; color:var(--mut); font-weight:800; padding:.25rem .25rem 0; }
        .rt-scard{ display:flex; align-items:center; gap:.85rem; width:100%; text-align:left; padding:.8rem; border:1px solid var(--b); border-radius:1.1rem; background:var(--bg); cursor:pointer; box-shadow:0 1px 3px rgba(15,23,42,.04),0 10px 26px -22px rgba(15,23,42,.3); }
        .rt-scard:active{ background:var(--soft); }
        .rt-scard.locked{ opacity:.5; pointer-events:none; }
        .rt-scard.new{ border-style:dashed; border-color:var(--bs); background:transparent; box-shadow:none; }
        .rt-daychip{ display:grid; width:3.5rem; flex:none; border-radius:.85rem; overflow:hidden; border:1px solid var(--b); text-align:center; }
        .rt-daychip .d{ background-image:linear-gradient(150deg,var(--ac),var(--ac2)); color:#fff; font-size:.56rem; font-weight:800; text-transform:uppercase; letter-spacing:.1em; padding:.16rem 0; }
        .rt-daychip .t{ font-size:.82rem; font-weight:800; padding:.28rem 0; font-variant-numeric:tabular-nums; background:var(--bg); }
        .rt-scard-body{ flex:1; min-width:0; }
        .rt-scard-title{ font-weight:800; letter-spacing:-.01em; font-size:.95rem; }
        .rt-scard-meta{ font-size:.76rem; color:var(--sub); margin-top:.15rem; display:flex; align-items:center; gap:.4rem; font-weight:600; }
        .rt-plus{ display:grid; place-items:center; width:3.5rem; height:3.1rem; flex:none; border-radius:.85rem; border:1.5px dashed var(--bs); color:var(--ac); font-size:1.5rem; font-weight:800; }
        .rt-avatar{ display:inline-grid; place-items:center; width:1.4rem; height:1.4rem; border-radius:999px; background-image:linear-gradient(150deg,var(--ac),var(--ac2)); color:#fff; font-size:.62rem; font-weight:800; flex:none; }
        .rt-status{ display:inline-flex; align-items:center; gap:.35rem; font-size:.68rem; font-weight:800; padding:.24rem .6rem; border-radius:999px; white-space:nowrap; flex:none; }
        .rt-status .led{ width:.42rem; height:.42rem; border-radius:50%; }
        .rt-status.saved{ background:var(--oks); color:var(--ok); } .rt-status.saved .led{ background:var(--ok); }
        .rt-status.pending{ background:var(--was); color:var(--wa); } .rt-status.pending .led{ background:var(--wa); }
        .rt-callout{ border:1.5px dashed var(--bs); border-radius:1.1rem; padding:2rem 1rem; text-align:center; color:var(--mut); font-size:.85rem; background:var(--bg); }
        .rt-callout .ball{ display:block; font-size:1.8rem; margin-bottom:.4rem; }

        /* ---------- recorder screen ---------- */
        .rt-panel{ display:flex; flex-direction:column; gap:.85rem; }
        .rt-fieldlabel{ font-size:.62rem; text-transform:uppercase; letter-spacing:.1em; color:var(--mut); font-weight:800; }
        .rt-newsession{ display:grid; grid-template-columns:1fr; gap:.55rem; padding:.9rem; border:1px solid var(--b); border-radius:1rem; background:var(--bg); }
        .rt-newsession .row2{ display:grid; grid-template-columns:1fr 1fr; gap:.55rem; }
        .rt-coachstrip{ display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; font-size:.8rem; color:var(--sub); background:var(--bg); border:1px solid var(--b); border-radius:1rem; padding:.7rem .85rem; }
        .rt-coachstrip .lbl{ font-weight:800; color:inherit; }
        .rt-coachstrip select{ width:auto; flex:1; min-width:7rem; }
        .rt-linkbtn{ border:0; background:transparent; color:var(--ac2); font-weight:800; font-size:.8rem; cursor:pointer; padding:0; }
        .rt-linkbtn.muted{ color:var(--mut); }
        .rt-warn{ font-size:.78rem; color:var(--wa); background:var(--was); border:1px solid color-mix(in srgb,var(--wa) 30%,transparent); border-radius:.7rem; padding:.5rem .65rem; font-weight:600; }

        .rt-rosterhead{ display:flex; align-items:center; justify-content:space-between; padding:0 .25rem; }
        .rt-rosterhead .t{ font-size:.66rem; text-transform:uppercase; letter-spacing:.1em; color:var(--mut); font-weight:800; }
        .rt-players{ display:flex; flex-direction:column; gap:.5rem; }
        .rt-prow{ display:flex; align-items:center; gap:.7rem; padding:.65rem .75rem; border:1px solid var(--b); border-radius:1rem; background:var(--bg); }
        .rt-prow.done{ border-left:4px solid var(--ok); }
        .rt-prow.overlimit{ border-left:4px solid var(--da); }
        .rt-prow.excused{ opacity:.62; }
        .rt-prow-main{ flex:1; min-width:0; display:flex; align-items:center; gap:.65rem; cursor:pointer; background:none; border:0; text-align:left; color:inherit; padding:0; }
        .rt-pav{ display:grid; place-items:center; width:2.25rem; height:2.25rem; border-radius:.7rem; background:var(--soft); border:1px solid var(--b); font-weight:800; font-size:.85rem; color:var(--sub); flex:none; }
        .rt-pname{ font-weight:700; font-size:.9rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .rt-psub{ font-size:.7rem; color:var(--mut); margin-top:.1rem; display:flex; gap:.3rem; flex-wrap:wrap; align-items:center; }
        .rt-scored{ font-size:.66rem; font-weight:800; color:var(--ac2); background:var(--acs); padding:.08rem .4rem; border-radius:999px; }
        .rt-scored.full{ color:var(--ok); background:var(--oks); }
        .rt-cyc{ display:inline-flex; align-items:center; gap:.35rem; border:1.5px solid; border-radius:.7rem; padding:.4rem .7rem; font-size:.76rem; font-weight:800; cursor:pointer; background:var(--bg); flex:none; }
        .rt-cyc .d{ width:.45rem; height:.45rem; border-radius:50%; }
        .rt-cyc.present{ border-color:color-mix(in srgb,var(--ok) 45%,transparent); color:var(--ok); background:var(--oks); } .rt-cyc.present .d{ background:var(--ok); }
        .rt-cyc.late{ border-color:color-mix(in srgb,var(--wa) 45%,transparent); color:var(--wa); background:var(--was); } .rt-cyc.late .d{ background:var(--wa); }
        .rt-cyc.absent{ border-color:color-mix(in srgb,var(--da) 45%,transparent); color:var(--da); background:var(--das); } .rt-cyc.absent .d{ background:var(--da); }
        .rt-cyc.excused{ border-color:var(--bs); color:var(--mut); background:var(--soft); } .rt-cyc.excused .d{ background:var(--mut); }
        .rt-remove{ border:0; background:transparent; color:var(--mut); cursor:pointer; font-size:1rem; padding:.25rem; flex:none; }

        .rt-badge{ font-size:.66rem; padding:.15rem .5rem; border-radius:999px; font-weight:800; white-space:nowrap; }
        .rt-badge.pay-active{ background:var(--oks); color:var(--ok); } .rt-badge.pay-pending{ background:var(--was); color:var(--wa); } .rt-badge.pay-overdue{ background:var(--das); color:var(--da); }
        .rt-badge.credits{ background:var(--soft); color:var(--sub); border:1px solid var(--b); } .rt-badge.credits.over{ background:var(--das); color:var(--da); } .rt-badge.credits.full{ background:var(--was); color:var(--wa); }
        .rt-badge.extra{ background:var(--acs); color:var(--ac2); } .rt-badge.walkin{ background:var(--vis); color:var(--vi); } .rt-badge.carry{ background:var(--was); color:var(--wa); }

        /* ---------- add / floating ---------- */
        .rt-addbtn{ display:flex; align-items:center; justify-content:center; gap:.5rem; width:100%; padding:.8rem; border:1.5px dashed var(--bs); border-radius:1rem; background:var(--bg); color:var(--ac2); font-weight:800; font-size:.85rem; cursor:pointer; }

        /* ---------- bottom sheet ---------- */
        .rt-backdrop{ position:fixed; inset:0; z-index:60; background:rgba(15,23,42,.5); }
        .rt-sheet{ position:fixed; left:50%; transform:translateX(-50%); bottom:0; z-index:61; width:100%; max-width:34rem; max-height:88vh; overflow-y:auto; background:var(--bg); border-radius:1.4rem 1.4rem 0 0; box-shadow:0 -18px 40px -12px rgba(15,23,42,.35); padding:.5rem 1.1rem 1.4rem; display:flex; flex-direction:column; gap:1rem; }
        .rt-handle{ width:2.5rem; height:.28rem; border-radius:999px; background:var(--bs); margin:.35rem auto .2rem; }
        .rt-sheet-head{ display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem; }
        .rt-sheet-head .nm{ font-size:1.15rem; font-weight:800; letter-spacing:-.02em; }
        .rt-sheet-head .badges{ display:flex; flex-wrap:wrap; gap:.35rem; margin-top:.35rem; }
        .rt-sheet-close{ border:0; background:var(--soft); border-radius:.7rem; color:var(--sub); font-size:1rem; padding:.4rem .55rem; cursor:pointer; flex:none; }
        .rt-sheet-sec{ display:flex; flex-direction:column; gap:.55rem; }
        .rt-sheet-sec > .h{ font-size:.66rem; text-transform:uppercase; letter-spacing:.1em; color:var(--mut); font-weight:800; }
        .rt-att{ display:grid; grid-template-columns:repeat(4,1fr); gap:.45rem; }
        .rt-att button{ border:1.5px solid var(--bs); border-radius:.8rem; padding:.65rem .3rem; background:var(--bg); cursor:pointer; font-size:.78rem; font-weight:700; color:var(--sub); }
        .rt-att button.sel{ color:#fff; border-color:transparent; }
        .rt-att button.sel.present{ background:var(--ok); } .rt-att button.sel.late{ background:var(--wa); } .rt-att button.sel.absent{ background:var(--da); } .rt-att button.sel.excused{ background:var(--mut); }
        .rt-skill{ display:flex; align-items:center; justify-content:space-between; gap:.6rem; padding:.15rem 0; }
        .rt-skill .n{ font-size:.86rem; font-weight:600; }
        .rt-pills{ display:inline-flex; gap:.3rem; }
        .rt-pill{ width:2.4rem; height:2.4rem; border:1.5px solid var(--bs); border-radius:.7rem; background:var(--bg); cursor:pointer; font-size:.9rem; font-weight:700; color:var(--sub); }
        .rt-pill.sel{ border-color:transparent; background-image:linear-gradient(150deg,var(--ac),var(--ac2)); color:#fff; font-weight:800; box-shadow:inset 0 1px 0 rgba(255,255,255,.22); }
        .rt-pill:disabled{ opacity:.35; cursor:not-allowed; }
        .rt-sheet-done{ background-image:linear-gradient(150deg,var(--ac),var(--ac2)); color:#fff; border:0; border-radius:.9rem; padding:.8rem; font-weight:800; font-size:.95rem; cursor:pointer; box-shadow:inset 0 1px 0 rgba(255,255,255,.22); }

        .rt-result{ display:flex; align-items:center; gap:.6rem; padding:.6rem .7rem; border:1px solid var(--b); border-radius:.8rem; background:var(--bg); font-size:.82rem; flex-wrap:wrap; }
        .rt-result .meta{ flex:1; min-width:0; }
        .rt-tag{ font-size:.66rem; padding:.15rem .5rem; border-radius:999px; background:var(--acs); color:var(--ac2); font-weight:800; }
        .rt-results{ display:flex; flex-direction:column; gap:.4rem; }
        .rt-row2{ display:grid; grid-template-columns:1fr 1fr; gap:.55rem; }
        .rt-muted{ color:var(--mut); font-size:.8rem; }

        /* ---------- sticky action bar ---------- */
        /* Sticky (not viewport-fixed) so it tracks the centered .rt column instead of the whole
           window — otherwise Filament's sidebar offsets it left of the content on desktop. */
        .rt-actionbar{ position:sticky; bottom:0; z-index:40; display:flex; align-items:center; gap:.6rem; padding:.9rem 1rem calc(.9rem + env(safe-area-inset-bottom)); background:linear-gradient(to top,var(--bg) 72%,transparent); }
        .rt-save{ flex:1; background-image:linear-gradient(150deg,var(--ac),var(--ac2)); color:#fff; border:0; border-radius:.95rem; padding:.85rem; font-weight:800; font-size:1rem; cursor:pointer; box-shadow:inset 0 1px 0 rgba(255,255,255,.22),0 12px 24px -10px color-mix(in srgb,var(--ac) 65%,transparent); }
        .rt-save:disabled{ opacity:.55; cursor:not-allowed; box-shadow:none; }
        .rt-discard,.rt-delete{ border:1px solid var(--bs); background:var(--bg); color:var(--sub); border-radius:.95rem; padding:.85rem 1rem; font-weight:800; cursor:pointer; font-size:.85rem; flex:none; }
        .rt-delete{ border-color:color-mix(in srgb,var(--da) 35%,transparent); color:var(--da); }

        /* ---------- help modal ---------- */
        [x-cloak]{ display:none; }
        .rt-help-overlay{ position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:flex-end; justify-content:center; z-index:100; }
        .rt-help-card{ background:var(--bg); border-radius:1.4rem 1.4rem 0 0; width:100%; max-width:34rem; max-height:85vh; overflow-y:auto; padding:1.3rem 1.4rem 1.6rem; position:relative; }
        .rt-help-close{ position:absolute; top:.9rem; right:.9rem; border:0; background:var(--soft); border-radius:.6rem; color:var(--sub); font-size:1rem; padding:.35rem .5rem; cursor:pointer; }
        .rt-help-title{ font-size:1.15rem; font-weight:800; margin:0 0 .75rem; padding-right:2rem; }
        .rt-help-section{ margin-top:1.1rem; } .rt-help-section h4{ font-size:.68rem; text-transform:uppercase; letter-spacing:.09em; color:var(--mut); font-weight:800; margin:0 0 .5rem; }
        .rt-help-rules,.rt-help-steps{ margin:0; padding-left:1.1rem; display:flex; flex-direction:column; gap:.35rem; font-size:.83rem; color:var(--sub); }
        .rt-help-badges{ display:flex; flex-direction:column; gap:.5rem; } .rt-help-badgerow{ display:flex; align-items:center; gap:.6rem; font-size:.8rem; color:var(--sub); } .rt-help-badgerow .rt-badge{ flex:none; }
        .rt-help-foot{ margin-top:1.1rem; font-size:.8rem; color:var(--mut); border-top:1px dashed var(--bs); padding-top:.75rem; }
    </style>

    <div class="rt {{ $onRecorder ? 'rt-recording' : '' }}" x-data="{ help: false }" @keydown.escape.window="help = false">

        {{-- ============================ RECORDER SCREEN ============================ --}}
        @if($onRecorder)
            <div class="rt-bar">
                <button type="button" class="rt-iconbtn" wire:click="{{ $creatingSession ? 'cancelNewSession' : 'toggleSession('.$offeringId.')' }}"
                    @disabled($dirty) title="{{ $dirty ? 'Save or discard first' : 'Back to sessions' }}" aria-label="Back">
                    <svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg>
                </button>
                <div style="flex:1; min-width:0;">
                    <div class="rt-crumb">{{ $sel->format('D, j M') }}</div>
                    <h1 style="font-size:1.05rem;">{{ $creatingSession ? 'New session' : ($openSession['program'] ?? 'Session').' · '.($openSession['time'] ?? '') }}</h1>
                </div>
                @if($dirty)
                    <span class="rt-status pending"><span class="led"></span>Unsaved</span>
                @elseif($savedSessionExists)
                    <span class="rt-status saved"><span class="led"></span>Saved</span>
                @else
                    <span class="rt-status pending"><span class="led"></span>Not recorded</span>
                @endif
            </div>

            @include('filament.pages.partials.run-training-recorder')

        {{-- ============================ DAY / LIST SCREEN ============================ --}}
        @else
            <div class="rt-bar">
                <h1>Run Training</h1>
                <label class="rt-iconbtn rt-cal" title="Jump to date">
                    <input type="date" wire:model.live="date">
                    <svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="16" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/></svg>
                </label>
                <button type="button" class="rt-textbtn" wire:click="goToday">Today</button>
                <button type="button" class="rt-iconbtn" @click="help = true" aria-label="How credits work"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 114 2c-.9.6-1.5 1.2-1.5 2.2M12 17h.01"/></svg></button>
            </div>

            <div class="rt-week">
                <button type="button" class="rt-iconbtn" wire:click="$set('date', '{{ $sel->copy()->subDays(7)->toDateString() }}')" aria-label="Previous week"><svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg></button>
                <div class="rt-days">
                    @for($i = 0; $i < 7; $i++)
                        @php($d = $sel->copy()->startOfWeek(\Carbon\CarbonInterface::MONDAY)->addDays($i))
                        <button type="button" class="rt-day {{ $d->toDateString() === $date ? 'on' : '' }}" wire:click="$set('date', '{{ $d->toDateString() }}')">
                            <span class="wd">{{ $d->format('D') }}</span>
                            <span class="dn">{{ $d->format('j') }}</span>
                            @if($d->isToday())<span class="dot"></span>@else<span class="dot" style="background:transparent"></span>@endif
                        </button>
                    @endfor
                </div>
                <button type="button" class="rt-iconbtn" wire:click="$set('date', '{{ $sel->copy()->addDays(7)->toDateString() }}')" aria-label="Next week"><svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg></button>
            </div>

            <div class="rt-list">
                <div class="rt-listlabel">{{ $sel->format('l, j F') }}</div>

                @forelse($sessions as $s)
                    <button type="button" class="rt-scard @if($dirty) locked @endif" wire:click="toggleSession({{ $s['id'] }})">
                        <span class="rt-daychip" aria-hidden="true"><span class="d">{{ $sel->format('D') }}</span><span class="t">{{ substr($s['time'], 0, 5) }}</span></span>
                        <span class="rt-scard-body">
                            <span class="rt-scard-title" style="display:block;">{{ $s['program'] }}</span>
                            <span class="rt-scard-meta">
                                @if($s['coach'])<span class="rt-avatar">{{ mb_substr($s['coach'], 0, 1) }}</span>{{ $s['coach'] }} · @endif
                                {{ $s['recorded'] ? $s['attended'].' attended' : $s['enrolled'].' enrolled' }}
                            </span>
                        </span>
                        @if($s['recorded'])<span class="rt-status saved"><span class="led"></span>Saved</span>
                        @else<span class="rt-status pending"><span class="led"></span>Not recorded</span>@endif
                    </button>
                @empty
                    <div class="rt-callout"><span class="ball" aria-hidden="true">⚽</span>No sessions scheduled on this day.</div>
                @endforelse

                <button type="button" class="rt-scard new @if($dirty) locked @endif" wire:click="toggleNewSession">
                    <span class="rt-plus" aria-hidden="true">＋</span>
                    <span class="rt-scard-body">
                        <span class="rt-scard-title" style="display:block;">Create new session</span>
                        <span class="rt-scard-meta">Off-schedule or one-off clinic</span>
                    </span>
                </button>
            </div>
        @endif

        {{-- ============================ EDIT SHEET (per player) ============================ --}}
        @if($onRecorder && $expandedKey && isset($roster[$expandedKey]))
            @php($row = $roster[$expandedKey])
            @php($absent = in_array($row['status'], ['absent', 'excused'], true))
            <div class="rt-backdrop" wire:click="$set('expandedKey', null)"></div>
            <div class="rt-sheet" role="dialog" aria-modal="true" aria-label="Record {{ $row['name'] }}">
                <div class="rt-handle"></div>
                <div class="rt-sheet-head">
                    <div>
                        <div class="nm">{{ $row['name'] }}</div>
                        <div class="badges">
                            @if($row['type'] === 'enrolled')
                                @if($row['payment_status'])<span class="rt-badge pay-{{ $row['payment_status'] }}">{{ $row['payment_status'] === 'active' ? 'paid' : $row['payment_status'] }}</span>@endif
                                @if(! is_null($row['credits_total']))
                                    @php($u = (int) $row['credits_used'])@php($t = (int) $row['credits_total'])
                                    <span class="rt-badge credits {{ $u > $t ? 'over' : ($u === $t && $t > 0 ? 'full' : '') }}">{{ $u }}/{{ $t }}{{ $u > $t ? ' · +'.($u - $t).' over' : ($u === $t && $t > 0 ? ' · paid up' : '') }}</span>
                                @endif
                                @if(($row['carry_over'] ?? 0) > 0)<span class="rt-badge carry">+{{ $row['carry_over'] }} carried</span>@endif
                            @elseif($row['type'] === 'make_up')
                                <span class="rt-badge extra">make-up · no fee</span>
                            @else
                                <span class="rt-badge walkin">walk-in · RM{{ number_format(($row['fee_sen'] ?? 0) / 100, 2) }}</span>
                            @endif
                        </div>
                    </div>
                    <button type="button" class="rt-sheet-close" wire:click="$set('expandedKey', null)" aria-label="Close">✕</button>
                </div>

                <div class="rt-sheet-sec">
                    <span class="h">Attendance</span>
                    <div class="rt-att">
                        @foreach(['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused'] as $val => $label)
                            <button type="button" class="{{ $row['status'] === $val ? 'sel '.$val : '' }}" wire:click="setStatus('{{ $expandedKey }}', '{{ $val }}')">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                @if($skills->isNotEmpty())
                    <div class="rt-sheet-sec" @if($absent) style="opacity:.5" title="Scores aren't recorded for an absent player" @endif>
                        <span class="h">Skill scores {{ $absent ? '· not recorded while absent' : '(1–5)' }}</span>
                        @foreach($skills as $skill)
                            <div class="rt-skill">
                                <span class="n">{{ $skill->name }}</span>
                                <span class="rt-pills">
                                    @for($n = 1; $n <= 5; $n++)
                                        <button type="button" class="rt-pill {{ ($row['scores'][$skill->id] ?? null) === $n ? 'sel' : '' }}" wire:click="setScore('{{ $expandedKey }}', {{ $skill->id }}, {{ $n }})" @disabled($absent)>{{ $n }}</button>
                                    @endfor
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="rt-sheet-sec">
                    <span class="h">Coach</span>
                    <select wire:model="roster.{{ $expandedKey }}.coach_id">
                        <option value="">— unassigned —</option>
                        @foreach($coachOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                </div>

                <div class="rt-sheet-sec">
                    <span class="h">Note</span>
                    <textarea wire:model.live.debounce.500ms="roster.{{ $expandedKey }}.note" rows="2" placeholder="Optional note for this player"></textarea>
                </div>

                <button type="button" class="rt-sheet-done" wire:click="$set('expandedKey', null)">Done</button>
            </div>
        @endif

        {{-- ============================ ADD SHEET ============================ --}}
        @if($onRecorder && $adding)
            @include('filament.pages.partials.run-training-add', ['suggestion' => $suggestion])
        @endif

        {{-- ============================ ADD-COACH SHEET ============================ --}}
        @if($onRecorder && $addingCoach)
            @include('filament.pages.partials.run-training-coach')
        @endif

        {{-- ============================ HELP MODAL ============================ --}}
        <div class="rt-help-overlay" x-cloak x-show="help" @click.self="help = false" role="dialog" aria-modal="true" aria-label="How session credits work">
            <div class="rt-help-card">
                <button type="button" class="rt-help-close" @click="help = false" aria-label="Close">✕</button>
                <h3 class="rt-help-title">How session credits work</h3>
                <div class="rt-help-section">
                    <h4>The five rules</h4>
                    <ol class="rt-help-rules">
                        <li>Each month's registration buys N sessions ("credits" — usually 4).</li>
                        <li>Attending your own weekly class uses this month's credit — present, late and absent all use one (the spot was held); excused does not.</li>
                        <li>Unused credits never disappear — unused sessions from previous months become "carried" credits.</li>
                        <li>Carried credits are spent by joining an extra session in the same program: free as a make-up, oldest first, same program only and up to the session's own month. With none left, they pay the walk-in fee.</li>
                        <li>Regular monthly sessions never touch carried credits — this month's fee covers this month's classes.</li>
                    </ol>
                </div>
                <div class="rt-help-section">
                    <h4>Badge cheat-sheet</h4>
                    <div class="rt-help-badges">
                        <div class="rt-help-badgerow"><span class="rt-badge credits">2/4</span> <span>in progress — 2 of 4 paid sessions used.</span></div>
                        <div class="rt-help-badgerow"><span class="rt-badge credits full">4/4 · paid up</span> <span>all paid sessions used — renewal due soon.</span></div>
                        <div class="rt-help-badgerow"><span class="rt-badge credits over">5/4 · +1 over</span> <span>over-delivered — never blocked, just flagged.</span></div>
                        <div class="rt-help-badgerow"><span class="rt-badge carry">+2 carried</span> <span>unused past sessions, usable as free make-ups.</span></div>
                        <div class="rt-help-badgerow"><span class="rt-badge extra">make-up</span> <span>extra session paid by a carried credit.</span></div>
                        <div class="rt-help-badgerow"><span class="rt-badge walkin">walk-in · RM40</span> <span>extra session, no credits left — pays the fee.</span></div>
                    </div>
                </div>
                <div class="rt-help-foot">Credits belong to the program they were bought for. Nothing is ever blocked; over-limit sessions are simply flagged for renewal.</div>
            </div>
        </div>
    </div>
</x-filament::page>
