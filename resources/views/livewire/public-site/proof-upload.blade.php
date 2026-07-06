<div>
    @if ($uploaded)
        <p class="mt-3 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            Receipt uploaded — awaiting confirmation.
        </p>
    @else
        @if ($rejectionNote)
            <p class="mt-3 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                Your previous receipt could not be confirmed: {{ $rejectionNote }}
            </p>
        @endif
        <form wire:submit="submit" class="mt-3 space-y-3">
            <div>
                <label for="receipt-{{ $enrollment->id }}" class="block text-sm font-medium text-zinc-700">Upload receipt</label>
                <input
                    type="file"
                    id="receipt-{{ $enrollment->id }}"
                    wire:model="receipt"
                    accept=".jpg,.jpeg,.png,.pdf"
                    class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm"
                >
                <p class="mt-1 text-xs text-zinc-500">JPG, PNG, or PDF — max 4MB.</p>
                @error('receipt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                <div wire:loading wire:target="receipt" class="mt-1 text-xs text-zinc-500">Uploading…</div>
            </div>
            <div>
                <label for="note-{{ $enrollment->id }}" class="block text-sm font-medium text-zinc-700">Note (optional)</label>
                <input
                    type="text"
                    id="note-{{ $enrollment->id }}"
                    wire:model="note"
                    class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm"
                >
            </div>
            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="submit"
                class="inline-flex rounded-md bg-zinc-950 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-60"
            >
                Upload receipt
            </button>
        </form>
    @endif
</div>
