{{-- Bottom sheet for adding or editing a student — same sheet pattern as Run Training's add flows.
     `editingId` on the component decides create vs update; the title reflects it. --}}
<div class="rt-backdrop" wire:click="cancelForm"></div>
<div class="rt-sheet" role="dialog" aria-modal="true" aria-label="{{ $editingId ? 'Edit student' : 'Add student' }}">
    <div class="rt-handle"></div>
    <div class="rt-sheet-head">
        <div class="nm">{{ $editingId ? 'Edit student' : 'Add student' }}</div>
        <button type="button" class="rt-sheet-close" wire:click="cancelForm" aria-label="Close">✕</button>
    </div>

    <div class="rt-sheet-sec">
        <span class="h">Name</span>
        <input type="text" wire:model="fName" placeholder="Full name">
        @error('fName') <div class="rt-warn">{{ $message }}</div> @enderror
    </div>

    <div class="rt-sheet-sec">
        <span class="h">Status</span>
        <div class="rt-att" style="grid-template-columns:1fr 1fr">
            <button type="button" class="{{ $fActive ? 'sel present' : '' }}" wire:click="$set('fActive', true)">Active</button>
            <button type="button" class="{{ ! $fActive ? 'sel absent' : '' }}" wire:click="$set('fActive', false)">Inactive</button>
        </div>
    </div>

    <div class="rt-sheet-sec">
        <span class="h">Identity</span>
        <div class="rt-row2">
            <input type="text" wire:model="fIc" placeholder="IC number (optional)">
            <input type="date" wire:model="fDob" title="Date of birth">
        </div>
        <select wire:model="fGender">
            <option value="">— gender (optional) —</option>
            @foreach($genderOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
        </select>
        @error('fIc') <div class="rt-warn">{{ $message }}</div> @enderror
        @error('fDob') <div class="rt-warn">{{ $message }}</div> @enderror
    </div>

    <div class="rt-sheet-sec">
        <span class="h">Guardian</span>
        <input type="text" wire:model="fGuardianName" placeholder="Guardian name (optional)">
        <input type="tel" wire:model="fGuardianPhone" placeholder="Guardian phone (optional)">
        @error('fGuardianName') <div class="rt-warn">{{ $message }}</div> @enderror
        @error('fGuardianPhone') <div class="rt-warn">{{ $message }}</div> @enderror
    </div>

    <div class="rt-sheet-sec">
        <span class="h">Notes <span style="text-transform:none;font-weight:600;color:var(--mut)">(optional)</span></span>
        <textarea wire:model="fNotes" rows="2" placeholder="Anything the coaches should know"></textarea>
        @error('fNotes') <div class="rt-warn">{{ $message }}</div> @enderror
    </div>

    <button type="button" class="rt-sheet-done" wire:click="saveStudent">{{ $editingId ? 'Save changes' : 'Add student' }}</button>
</div>
