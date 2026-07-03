<x-filament::page>
    @php($skills = $this->skills)
    @php($suggestion = $this->suggestion)
    @php($coachOptions = $this->coachOptions)

    <style>
        .rt { --rt-border:#e5e7eb; --rt-bg:#fff; --rt-soft:#f9fafb; --rt-muted:#6b7280; --rt-accent:#16a34a; --rt-accent-soft:#dcfce7; --rt-danger:#dc2626; --rt-warn:#b45309; display:flex; flex-direction:column; gap:1rem; padding-bottom:5rem; }
        .dark .rt { --rt-border:#374151; --rt-bg:#1f2937; --rt-soft:#111827; --rt-muted:#9ca3af; --rt-accent:#22c55e; --rt-accent-soft:#064e3b; }
        .rt input, .rt select, .rt textarea { border:1px solid var(--rt-border); border-radius:.5rem; padding:.4rem .6rem; background:var(--rt-bg); color:inherit; font-size:.85rem; }
        .rt select:disabled, .rt input:disabled { opacity:.55; cursor:not-allowed; }
        .rt-iconbtn:disabled { opacity:.4; cursor:not-allowed; }
        .rt-head { display:flex; flex-wrap:wrap; gap:.75rem; align-items:center; justify-content:space-between; }
        .rt-controls { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; }
        .rt-datenav { display:inline-flex; align-items:center; gap:.25rem; }
        .rt-weekday { font-size:.75rem; color:var(--rt-muted); min-width:1.6rem; text-align:center; }
        .rt-iconbtn { border:1px solid var(--rt-border); border-radius:.5rem; width:2rem; height:2rem; display:inline-flex; align-items:center; justify-content:center; background:var(--rt-bg); cursor:pointer; color:inherit; }
        .rt-summary { font-size:.8rem; color:var(--rt-muted); display:flex; gap:.5rem; align-items:center; }
        .rt-dirty { color:var(--rt-warn); font-weight:600; }
        .rt-saved { color:var(--rt-accent); font-weight:600; }
        .rt-coachbar { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; padding:.6rem .75rem; border:1px solid var(--rt-border); border-radius:.6rem; background:var(--rt-soft); font-size:.82rem; }
        .rt-coachbar .sep { flex:1; }
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
        .rt-empty { display:flex; flex-direction:column; align-items:center; gap:1rem; padding:2.5rem 1rem; }
        .rt-savebar { position:sticky; bottom:0; display:flex; justify-content:flex-end; gap:.75rem; padding:.75rem 0; background:linear-gradient(to top, var(--rt-bg) 55%, transparent); }
        .rt-save { background:var(--rt-accent); color:#fff; border:0; border-radius:.6rem; padding:.65rem 1.6rem; font-weight:700; cursor:pointer; box-shadow:0 6px 16px rgba(0,0,0,.18); }
        .rt-save:disabled { opacity:.5; cursor:not-allowed; box-shadow:none; }
        .rt-delete { border:1px solid var(--rt-danger); color:var(--rt-danger); background:transparent; border-radius:.6rem; padding:.65rem 1.2rem; font-weight:600; cursor:pointer; }
    </style>

    <div class="rt">
        <div class="rt-head">
            <div class="rt-controls">
                <select wire:model.live="period" title="Month" @disabled($dirty)>
                    @foreach($this->periodOptions as $p => $label)
                        <option value="{{ $p }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select wire:model.live="offeringId" title="Timeslot" @disabled($dirty)>
                    <option value="">— choose timeslot —</option>
                    @foreach($this->offeringOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
                <div class="rt-datenav">
                    <button type="button" class="rt-iconbtn" wire:click="shiftDay(-1)" @disabled($dirty)>‹</button>
                    <input type="date" wire:model.live="date" @disabled($dirty)>
                    <span class="rt-weekday">{{ $date ? \Illuminate\Support\Carbon::parse($date)->format('D') : '' }}</span>
                    <button type="button" class="rt-iconbtn" wire:click="shiftDay(1)" @disabled($dirty)>›</button>
                    <button type="button" class="rt-btn ghost" wire:click="goToday" @disabled($dirty)>Today</button>
                </div>
            </div>
            <div class="rt-summary">
                <span>{{ $this->summary() }}</span>
                @if($dirty)
                    <span class="rt-dirty">● Unsaved changes</span>
                @elseif($savedSessionExists)
                    <span class="rt-saved">● Saved</span>
                @endif
            </div>
        </div>

        @if(! $offeringId)
            <div class="rt-callout">Choose a timeslot above to load its roster.</div>
        @elseif(! $started)
            <div class="rt-callout rt-empty">
                <div>No session recorded for <strong>{{ $date ? \Illuminate\Support\Carbon::parse($date)->format('l, j M Y') : 'this date' }}</strong> yet.</div>
                <button type="button" class="rt-save" wire:click="startSession">Start session</button>
            </div>
        @else
            <div class="rt-coachbar">
                <span>Assign all to</span>
                <select wire:model="bulkCoachId">
                    <option value="">— unassigned —</option>
                    @foreach($coachOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <button type="button" class="rt-btn" wire:click="assignAll" @disabled(empty($coachOptions))>Apply to all</button>
                @if(empty($coachOptions))
                    <span class="rt-muted">No coaches yet — add one with “+ Add coach”.</span>
                @else
                    <span class="rt-muted">Each player defaults to the timeslot's head coach.</span>
                @endif
                <span class="sep"></span>
                @if($addingCoach)
                    <input type="text" wire:model="newCoachName" placeholder="Coach name">
                    <input type="email" wire:model="newCoachEmail" placeholder="Email (optional)">
                    <button type="button" class="rt-btn" wire:click="saveCoach">Save coach</button>
                    <button type="button" class="rt-btn ghost" wire:click="cancelAddCoach">Cancel</button>
                    @error('newCoachName') <div class="rt-warn">{{ $message }}</div> @enderror
                    @error('newCoachEmail') <div class="rt-warn">{{ $message }}</div> @enderror
                @else
                    <button type="button" class="rt-btn" wire:click="startAddCoach">+ Add coach</button>
                @endif
            </div>

            @php($enrolled = collect($roster)->filter(fn ($r) => $r['type'] === 'enrolled'))
            @php($extras = collect($roster)->reject(fn ($r) => $r['type'] === 'enrolled'))

            <div class="rt-list">
                @forelse($enrolled as $key => $row)
                    @include('filament.pages.partials.run-training-item', ['key' => $key, 'row' => $row, 'skills' => $skills, 'coachOptions' => $coachOptions, 'removable' => false])
                @empty
                    <div class="rt-muted">No players enrolled for this timeslot yet.</div>
                @endforelse
            </div>

            <div class="rt-section">
                <div class="rt-section-head">
                    <span class="rt-section-title">Walk-ins / Make-ups</span>
                    <button type="button" class="rt-btn" wire:click="startAdd">+ Add participant</button>
                </div>

                @if($adding)
                    <div class="rt-add">
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search existing student — name or IC">

                        <div class="rt-results">
                            @forelse($this->results as $r)
                                <div class="rt-result">
                                    <span class="meta">
                                        <strong>{{ $r['name'] }}</strong>
                                        <span class="rt-muted">· IC {{ $r['ic'] ?? '—' }} · age {{ $r['age'] ?? '—' }} · {{ $r['program'] ?? 'not enrolled' }}</span>
                                    </span>
                                    <span class="rt-tag">{{ $r['type'] === 'make_up' ? 'Make-up · no fee' : 'Walk-in · RM'.number_format(($r['fee_sen'] ?? 0) / 100, 2) }}</span>
                                    <button type="button" class="rt-btn" wire:click="addExisting({{ $r['id'] }})">Add</button>
                                </div>
                            @empty
                                <div class="rt-muted">{{ mb_strlen(trim($search)) < 2 ? 'Type a name or IC to search…' : 'No matches.' }}</div>
                            @endforelse
                        </div>

                        <div class="rt-new">
                            @if($suggestion)
                                <div class="rt-warn">
                                    ⚠ “{{ $newName }}” resembles <strong>{{ $suggestion['name'] }}</strong> —
                                    <button type="button" class="rt-btn" wire:click="addExisting({{ $suggestion['id'] }})">use existing</button>
                                </div>
                            @endif
                            <input type="text" wire:model.live.debounce.400ms="newName" placeholder="New walk-in name">
                            <input type="tel" wire:model="newPhone" placeholder="Phone">
                            <input type="text" wire:model="newIc" placeholder="IC (optional)">
                            <span class="rt-muted">Fee RM{{ number_format($walkInFeeSen / 100, 2) }}</span>
                            <button type="button" class="rt-btn" wire:click="addNewWalkIn">Add walk-in</button>
                            <button type="button" class="rt-btn ghost" wire:click="cancelAdd">Cancel</button>
                        </div>

                        @error('newName') <div class="rt-warn">{{ $message }}</div> @enderror
                    </div>
                @endif

                <div class="rt-added">
                    @foreach($extras as $key => $row)
                        @include('filament.pages.partials.run-training-item', ['key' => $key, 'row' => $row, 'skills' => $skills, 'coachOptions' => $coachOptions, 'removable' => true])
                    @endforeach
                </div>
            </div>

            <div class="rt-savebar">
                @if($savedSessionExists)
                    <button type="button" class="rt-delete" wire:click="deleteSession"
                        wire:confirm="Delete this saved session and all its attendance + scores? This cannot be undone.">Delete session</button>
                @endif
                @if($dirty)
                    <button type="button" class="rt-btn ghost" wire:click="discard"
                        wire:confirm="Discard your unsaved changes?">Discard</button>
                @endif
                <button type="button" class="rt-save" wire:click="save">Save session</button>
            </div>
        @endif
    </div>
</x-filament::page>
