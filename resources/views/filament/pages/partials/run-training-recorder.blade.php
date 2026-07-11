{{-- Recorder screen body: (new-session form) + coach strip + roster rows + walk-ins + save bar.
     Per-player editing lives in the bottom sheet (see run-training.blade.php). --}}
@php($enrolled = collect($roster)->filter(fn ($r) => $r['type'] === 'enrolled'))
@php($extras = collect($roster)->reject(fn ($r) => $r['type'] === 'enrolled'))
@php($filter = trim($rosterFilter))
@php($visibleEnrolled = $filter === '' ? $enrolled : $enrolled->filter(fn ($r) => stripos($r['name'], $filter) !== false || stripos((string) ($r['ic'] ?? ''), $filter) !== false))

<div class="rt-panel">
    @if($creatingSession)
        <div class="rt-newsession">
            <div>
                <span class="rt-fieldlabel">Program</span>
                <select wire:model.live="adHocProgramId" style="margin-top:.3rem">
                    <option value="">— choose program —</option>
                    @foreach($this->programOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </select>
                @error('adHocProgramId') <div class="rt-warn" style="margin-top:.4rem">{{ $message }}</div> @enderror
            </div>
            <div class="row2">
                <div>
                    <span class="rt-fieldlabel">Start</span>
                    <input type="time" wire:model.live="adHocTime" style="margin-top:.3rem">
                </div>
                <div>
                    <span class="rt-fieldlabel">End <span style="text-transform:none;font-weight:600;color:var(--mut)">(optional)</span></span>
                    <input type="time" wire:model="adHocEndTime" style="margin-top:.3rem">
                </div>
            </div>
            @error('adHocTime') <div class="rt-warn">{{ $message }}</div> @enderror
            @if($this->overlappingTimeslots)
                <div class="rt-warn">⚠ A session already runs at {{ substr($adHocTime, 0, 5) }} on this date — {{ implode(', ', $this->overlappingTimeslots) }}. Only create a separate session if that's intended (e.g. a second team).</div>
            @endif
        </div>
    @endif

    <div class="rt-coachstrip" data-tour="coach">
        <span class="lbl">Coach for all</span>
        <select wire:model="bulkCoachId">
            <option value="">— unassigned —</option>
            @foreach($coachOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
        </select>
        <button type="button" class="rt-textbtn" wire:click="assignAll" @disabled(empty($coachOptions))>Apply</button>
        <button type="button" class="rt-linkbtn" wire:click="startAddCoach">+ New coach</button>
    </div>

    {{-- First-use tip; dismissible, remembered per device. --}}
    <div x-data="{ show: ! window.localStorage?.getItem('rt-recorder-hint') }" x-show="show" x-cloak class="rt-hint">
        <span>💡 Tap a player to score them · tap the status pill to change attendance.</span>
        <button type="button" @click="show = false; window.localStorage?.setItem('rt-recorder-hint', '1')" aria-label="Dismiss tip">✕</button>
    </div>

    <div>
        <div class="rt-rosterhead">
            <span class="t">Roster · {{ $enrolled->count() }} enrolled</span>
            @if($enrolled->count() > 10)
                <span class="rt-muted">
                    <input type="text" wire:model.live.debounce.250ms="rosterFilter" placeholder="Filter…" style="display:inline-block;width:8rem;padding:.35rem .55rem;font-size:.78rem">
                </span>
            @endif
        </div>
        <div class="rt-players" style="margin-top:.5rem" data-tour="roster">
            @forelse($visibleEnrolled as $key => $row)
                @include('filament.pages.partials.run-training-item', ['key' => $key, 'row' => $row, 'removable' => false])
            @empty
                <div class="rt-callout" style="padding:1.25rem 1rem">{{ $filter !== '' ? 'No players match "'.$filter.'"' : ($creatingSession ? 'New session — add who attended below.' : 'No players enrolled for this session yet.') }}</div>
            @endforelse
        </div>
    </div>

    <div>
        <div class="rt-rosterhead">
            <span class="t">Walk-ins &amp; make-ups</span>
            @if($extras->isNotEmpty())<span class="rt-muted">{{ $extras->count() }} added</span>@endif
        </div>
        <div class="rt-players" style="margin-top:.5rem">
            @foreach($extras as $key => $row)
                @include('filament.pages.partials.run-training-item', ['key' => $key, 'row' => $row, 'removable' => true])
            @endforeach
            <button type="button" class="rt-addbtn" data-tour="add" wire:click="startAdd">＋ Add walk-in or make-up</button>
        </div>
    </div>
</div>

<div class="rt-actionbar" data-tour="save">
    @if($savedSessionExists)
        <button type="button" class="rt-delete" wire:click="deleteSession" wire:confirm="Delete this saved session and all its attendance + scores? This cannot be undone.">Delete</button>
    @endif
    @if($dirty)
        <button type="button" class="rt-discard" wire:click="discard" wire:confirm="Discard your unsaved changes?">Discard</button>
    @endif
    <button type="button" class="rt-save"
        @if($creatingSession && $this->overlappingTimeslots && count($roster))
            wire:confirm="A session already runs at that time on this date ({{ implode(', ', $this->overlappingTimeslots) }}). Create a second, separate session anyway?"
        @endif
        wire:loading.attr="disabled" wire:target="save" wire:click="save">
        <span wire:loading.remove wire:target="save">Save session</span>
        <span wire:loading wire:target="save">Saving…</span>
    </button>
</div>
