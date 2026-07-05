<x-layouts.public title="Choose a new password">
    <div class="mx-auto max-w-md rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-semibold text-zinc-900">Choose a new password</h1>

        <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <div>
                <label class="block text-sm font-medium text-zinc-700">Email</label>
                <input type="email" name="email" value="{{ old('email', $request->email) }}" required class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-zinc-900">
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700">Password</label>
                <input type="password" name="password" required class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-zinc-900">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700">Confirm password</label>
                <input type="password" name="password_confirmation" required class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-zinc-900">
            </div>
            <button type="submit" class="w-full rounded-md bg-emerald-700 px-4 py-2 font-medium text-white hover:bg-emerald-800">Reset password</button>
        </form>
    </div>
</x-layouts.public>
