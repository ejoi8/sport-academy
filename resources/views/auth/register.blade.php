<x-layouts.public title="Create account">
    <div class="mx-auto max-w-md rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-semibold text-zinc-900">Create account</h1>
        <p class="mt-2 text-sm text-zinc-500">This account is used for bookings, payment follow-up, and your family page.</p>

        <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-zinc-700">Your name</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-zinc-900">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-zinc-900">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700">Phone</label>
                <input type="text" name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-zinc-900">
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
            <button type="submit" class="w-full rounded-md bg-emerald-700 px-4 py-2 font-medium text-white hover:bg-emerald-800">Create account</button>
        </form>
    </div>
</x-layouts.public>
