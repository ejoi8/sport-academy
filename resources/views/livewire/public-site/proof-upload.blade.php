<div>
    @if ($uploaded)
        <p class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">
            Receipt uploaded — awaiting confirmation.
        </p>
    @else
        @if ($rejectionNote)
            <p class="mt-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
                Your previous receipt could not be confirmed: {{ $rejectionNote }}
            </p>
        @endif
        <form wire:submit="submit" class="mt-3 space-y-3">
            <div>
                <label for="receipt-{{ $enrollment->id }}" class="fa-label">Upload receipt</label>
                <input
                    type="file"
                    id="receipt-{{ $enrollment->id }}"
                    wire:model="receipt"
                    accept=".jpg,.jpeg,.png,.pdf"
                    class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-blue-700"
                >
                <p class="mt-1 text-xs text-slate-400">JPG, PNG, or PDF — max 4MB.</p>
                @error('receipt') <p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p> @enderror
                <div wire:loading wire:target="receipt" class="mt-1 text-xs text-slate-400">Uploading…</div>
            </div>
            <div>
                <label for="note-{{ $enrollment->id }}" class="fa-label">Note (optional)</label>
                <input
                    type="text"
                    id="note-{{ $enrollment->id }}"
                    wire:model="note"
                    class="fa-input"
                >
            </div>
            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="submit"
                class="fa-btn-ghost disabled:opacity-60"
            >
                Upload receipt
            </button>
        </form>
    @endif
</div>
