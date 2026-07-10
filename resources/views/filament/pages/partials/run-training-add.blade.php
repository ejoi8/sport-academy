{{-- Bottom sheet for adding a walk-in or make-up: search existing students, or create a new
     walk-in. Make-up vs walk-in classification comes from the component. --}}
<div class="rt-backdrop" wire:click="cancelAdd"></div>
<div class="rt-sheet" role="dialog" aria-modal="true" aria-label="Add participant">
    <div class="rt-handle"></div>
    <div class="rt-sheet-head">
        <div class="nm">Add participant</div>
        <button type="button" class="rt-sheet-close" wire:click="cancelAdd" aria-label="Close">✕</button>
    </div>

    <div class="rt-sheet-sec">
        <span class="h">Find an existing student</span>
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search name or IC">
        <div class="rt-results">
            @forelse($this->results as $r)
                <div class="rt-result">
                    <span class="meta">
                        <strong>{{ $r['name'] }}</strong>
                        <span class="rt-muted">IC {{ $r['ic'] ?? '—' }} · age {{ $r['age'] ?? '—' }}</span>
                    </span>
                    @if($r['type'] === 'make_up')
                        <span class="rt-tag">make-up · no fee · {{ $r['credits_left'] ?? 0 }} left</span>
                        @if(($r['payment_status'] ?? null) && $r['payment_status'] !== 'active')<span class="rt-badge pay-{{ $r['payment_status'] }}">{{ $r['payment_status'] }}</span>@endif
                        <button type="button" class="rt-textbtn" wire:click="addExisting({{ $r['id'] }})">Add</button>
                        <button type="button" class="rt-linkbtn muted" wire:click="addExisting({{ $r['id'] }}, true)" title="Charge the walk-in fee instead of using their credit">Walk-in</button>
                    @else
                        <span class="rt-tag" style="background:var(--vis);color:var(--vi)">walk-in · RM{{ number_format(($r['fee_sen'] ?? 0) / 100, 2) }}</span>
                        <button type="button" class="rt-textbtn" wire:click="addExisting({{ $r['id'] }})">Add</button>
                    @endif
                </div>
            @empty
                <div class="rt-muted" style="padding:.3rem .1rem">{{ mb_strlen(trim($search)) < 2 ? 'Type a name or IC to search…' : 'No matches.' }}</div>
            @endforelse
        </div>
    </div>

    <div class="rt-sheet-sec">
        <span class="h">Or add a new walk-in · fee RM{{ number_format($walkInFeeSen / 100, 2) }}</span>
        @if($suggestion)
            <div class="rt-warn">⚠ "{{ $newName }}" resembles <strong>{{ $suggestion['name'] }}</strong> — <button type="button" class="rt-linkbtn" wire:click="addExisting({{ $suggestion['id'] }})">use existing</button></div>
        @endif
        <input type="text" wire:model.live.debounce.400ms="newName" placeholder="New walk-in name">
        <div class="rt-row2">
            <input type="tel" wire:model="newPhone" placeholder="Phone (optional)">
            <input type="text" wire:model="newIc" placeholder="IC (optional)">
        </div>
        @error('newName') <div class="rt-warn">{{ $message }}</div> @enderror
        <button type="button" class="rt-sheet-done" wire:click="addNewWalkIn">Add walk-in</button>
    </div>
</div>
