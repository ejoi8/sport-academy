<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ? $title.' · '.config('app.name', 'Football Academy') : config('app.name', 'Football Academy') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-[#f6f8fb] text-slate-900 antialiased">
        <div class="min-h-screen">
            <header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/80 backdrop-blur">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6">
                    <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                        <span class="grid h-9 w-9 place-items-center rounded-xl bg-[linear-gradient(150deg,#2563eb,#1d4ed8)] text-sm font-extrabold text-white shadow-[0_6px_16px_-6px_rgba(37,99,235,0.7)]">FA</span>
                        <span class="text-base font-extrabold tracking-tight text-slate-900">Football Academy</span>
                    </a>
                    <nav class="flex items-center gap-1.5 text-sm font-semibold">
                        <a href="{{ route('home') }}" class="rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100">Programs</a>
                        @auth
                            <a href="{{ route('family.index') }}" class="rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100">My Family</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100">Log out</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100">Log in</a>
                            <a href="{{ route('register') }}" class="fa-btn-primary">Create account</a>
                        @endauth
                    </nav>
                </div>
            </header>

            @if (session('status'))
                <div class="mx-auto max-w-6xl px-4 pt-4 sm:px-6">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
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
