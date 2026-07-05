<x-layouts.public title="Log in">
    <div class="mx-auto max-w-md rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-semibold text-zinc-900">Log in</h1>
        <p class="mt-2 text-sm text-zinc-500">Use your parent account to view bookings and children.</p>

        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-zinc-700">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-zinc-900">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700">Password</label>
                <input type="password" name="password" required class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-zinc-900">
            </div>
            <label class="flex items-center gap-2 text-sm text-zinc-600">
                <input type="checkbox" name="remember" value="1">
                Remember me
            </label>
            <button type="submit" class="w-full rounded-md bg-emerald-700 px-4 py-2 font-medium text-white hover:bg-emerald-800">Log in</button>
        </form>

        <div class="mt-4 flex items-center justify-between text-sm">
            <a href="{{ route('password.request') }}" class="text-emerald-700 hover:text-emerald-800">Forgot password?</a>
            <a href="{{ route('register') }}" class="text-zinc-600 hover:text-zinc-900">Create account</a>
        </div>
    </div>
</x-layouts.public>
