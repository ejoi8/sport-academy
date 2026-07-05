{{-- The recording surface inside an expanded card: summary + (new-session form) + coach strip + roster + save. --}}
<div class="rt-summary">
    <span>{{ $this->summary() }}</span>
    @if($dirty)
        <span class="rt-dirty">● Unsaved changes</span>
    @elseif($savedSessionExists)
        <span class="rt-saved">● Saved</span>
    @else
        <span class="rt-muted">● Not recorded yet</span>
    @endif
</div>

@if($creatingSession)
    <div class="rt-newsession">
        <select wire:model.live="adHocProgramId" title="Program">
            <option value="">— program —</option>
            @foreach($this->programOptions as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
        <input type="time" wire:model.live="adHocTime" title="Start time">
        <span class="rt-muted">to</span>
        <input type="time" wire:model="adHocEndTime" title="End time (optional)">
        <span class="rt-muted">Add who attended below, then Save.</span>
        @error('adHocProgramId') <div class="rt-warn">{{ $message }}</div> @enderror
        @error('adHocTime') <div class="rt-warn">{{ $message }}</div> @enderror
        @if($this->overlappingTimeslots)
            <div class="rt-warn" style="flex-basis:100%;">⚠ A session already runs at {{ substr($adHocTime, 0, 5) }} on this date — {{ implode(', ', $this->overlappingTimeslots) }}. Only create a separate session if that's intended (e.g. a second team).</div>
        @endif
    </div>
@endif

<div class="rt-coachstrip">
    <span class="rt-cs-label">Coach for all</span>
    <select wire:model="bulkCoachId">
        <option value="">— unassigned —</option>
        @foreach($coachOptions as $id => $name)
            <option value="{{ $id }}">{{ $name }}</option>
        @endforeach
    </select>
    <button type="button" class="rt-linkbtn" wire:click="assignAll" @disabled(empty($coachOptions))>Apply</button>
    <span class="rt-cs-sep"></span>
    @if($addingCoach)
        <input type="text" wire:model="newCoachName" placeholder="Coach name">
        <input type="email" wire:model="newCoachEmail" placeholder="Email (optional)">
        <button type="button" class="rt-linkbtn" wire:click="saveCoach">Save</button>
        <button type="button" class="rt-linkbtn muted" wire:click="cancelAddCoach">Cancel</button>
        @error('newCoachName') <div class="rt-warn">{{ $message }}</div> @enderror
        @error('newCoachEmail') <div class="rt-warn">{{ $message }}</div> @enderror
    @else
        <button type="button" class="rt-linkbtn" wire:click="startAddCoach">+ Add coach</button>
    @endif
</div>

@php($enrolled = collect($roster)->filter(fn ($r) => $r['type'] === 'enrolled'))
@php($extras = collect($roster)->reject(fn ($r) => $r['type'] === 'enrolled'))
@php($filter = trim($rosterFilter))
@php($visibleEnrolled = $filter === '' ? $enrolled : $enrolled->filter(fn ($r) => stripos($r['name'], $filter) !== false || stripos((string) ($r['ic'] ?? ''), $filter) !== false))

@if($enrolled->count() > 10)
    <div class="rt-field">
        <input type="text" wire:model.live.debounce.250ms="rosterFilter" placeholder="Filter players — name or IC">
        @if($filter !== '')
            <span class="rt-muted">showing {{ $visibleEnrolled->count() }} of {{ $enrolled->count() }}</span>
            <button type="button" class="rt-linkbtn muted" wire:click="$set('rosterFilter', '')">Clear</button>
        @endif
    </div>
@endif

<div class="rt-list">
    @forelse($visibleEnrolled as $key => $row)
        @include('filament.pages.partials.run-training-item', ['key' => $key, 'row' => $row, 'skills' => $skills, 'coachOptions' => $coachOptions, 'removable' => false])
    @empty
        <div class="rt-muted">{{ $filter !== '' ? 'No players match "'.$filter.'"' : ($creatingSession ? 'New session — add who attended below.' : 'No players enrolled for this session yet.') }}</div>
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
                        <span class="rt-tag">{{ $r['type'] === 'make_up' ? 'Make-up · no fee · uses '.($r['program'] ?? 'carried credit').' · '.($r['credits_left'] ?? 0).' left' : 'Walk-in · RM'.number_format(($r['fee_sen'] ?? 0) / 100, 2) }}</span>
                        @if($r['type'] === 'make_up' && ($r['payment_status'] ?? null) && $r['payment_status'] !== 'active')
                            <span class="rt-badge pay-{{ $r['payment_status'] }}">{{ $r['payment_status'] }}</span>
                        @endif
                        <button type="button" class="rt-btn" wire:click="addExisting({{ $r['id'] }})">Add</button>
                        @if($r['type'] === 'make_up')
                            <button type="button" class="rt-btn ghost" wire:click="addExisting({{ $r['id'] }}, true)" title="Charge the walk-in fee instead of using their credit">Walk-in instead</button>
                        @endif
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
    <span class="rt-muted" wire:loading wire:target="save">Saving…</span>
    <button type="button" class="rt-save"
        @if($creatingSession && $this->overlappingTimeslots && count($roster))
            wire:confirm="A session already runs at that time on this date ({{ implode(', ', $this->overlappingTimeslots) }}). Create a second, separate session anyway?"
        @endif
        wire:loading.attr="disabled" wire:target="save"
        wire:click="save">Save session</button>
</div>
