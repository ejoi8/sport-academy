{{-- Bottom sheet for minting a new coach login — mirrors the add-participant sheet so every
     "add" flow on the page looks and behaves the same. A blank email auto-generates one. --}}
<div class="rt-backdrop" wire:click="cancelAddCoach"></div>
<div class="rt-sheet" role="dialog" aria-modal="true" aria-label="Add coach">
    <div class="rt-handle"></div>
    <div class="rt-sheet-head">
        <div class="nm">Add coach</div>
        <button type="button" class="rt-sheet-close" wire:click="cancelAddCoach" aria-label="Close">✕</button>
    </div>

    <div class="rt-sheet-sec">
        <span class="h">Name</span>
        <input type="text" wire:model="newCoachName" placeholder="Coach name">
        @error('newCoachName') <div class="rt-warn">{{ $message }}</div> @enderror
    </div>

    <div class="rt-sheet-sec">
        <span class="h">Email <span style="text-transform:none;font-weight:600;color:var(--mut)">(optional)</span></span>
        <input type="email" wire:model="newCoachEmail" placeholder="coach@example.com">
        <span class="rt-muted">Leave blank to auto-generate a placeholder login.</span>
        @error('newCoachEmail') <div class="rt-warn">{{ $message }}</div> @enderror
    </div>

    <button type="button" class="rt-sheet-done" wire:click="saveCoach">Save coach</button>
</div>
