<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ? $title.' · '.config('app.name', 'Football Academy') : config('app.name', 'Football Academy') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-[linear-gradient(180deg,#f4f7f1_0%,#eef3ea_42%,#f8faf7_100%)] text-zinc-900">
        <div class="min-h-screen">
            <header class="border-b border-zinc-200/70 bg-white/90 backdrop-blur">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                    <div>
                        <a href="{{ route('home') }}" class="text-base font-semibold tracking-normal text-zinc-900">Football Academy</a>
                        <p class="text-sm text-zinc-500">Browse classes, book a slot, and track your family bookings.</p>
                    </div>
                    <nav class="flex items-center gap-2 text-sm">
                        <a href="{{ route('home') }}" class="rounded-md px-3 py-2 text-zinc-700 hover:bg-zinc-100">Programs</a>
                        @auth
                            <a href="{{ route('family.index') }}" class="rounded-md px-3 py-2 text-zinc-700 hover:bg-zinc-100">My Family</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="rounded-md px-3 py-2 text-zinc-700 hover:bg-zinc-100">Log out</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="rounded-md px-3 py-2 text-zinc-700 hover:bg-zinc-100">Log in</a>
                            <a href="{{ route('register') }}" class="rounded-md bg-emerald-700 px-3 py-2 font-medium text-white hover:bg-emerald-800">Create account</a>
                        @endauth
                    </nav>
                </div>
            </header>

            @if (session('status'))
                <div class="mx-auto max-w-6xl px-4 pt-4 sm:px-6">
                    <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {{ session('status') }}
                    </div>
                </div>
            @endif

            <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8">
                {{ $slot }}
            </main>
        </div>

        @livewireScripts
    </body>
</html>
