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

            @if (session('error'))
                <div class="mx-auto max-w-6xl px-4 pt-4 sm:px-6">
                    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
                        {{ session('error') }}
                    </div>
                </div>
            @endif

            <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8">
                {{ $slot }}
            </main>

            <footer class="fa-grain relative mt-14 overflow-hidden bg-[linear-gradient(160deg,#0f2d7a,#091b4a)]">
                <x-pitch-lines opacity="0.08"/>
                <div class="relative mx-auto flex max-w-6xl flex-col gap-5 px-4 py-10 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div class="flex items-center gap-3">
                        <span class="grid h-9 w-9 place-items-center rounded-xl bg-white/10 text-xs font-extrabold text-white ring-1 ring-white/20">FA</span>
                        <div>
                            <p class="text-sm font-extrabold text-white">Football Academy</p>
                            <p class="text-xs text-blue-200/70">Real coaching, tracked progress.</p>
                        </div>
                    </div>
                    <nav class="flex flex-wrap items-center gap-x-6 gap-y-2 text-xs font-bold text-blue-100/80">
                        <a href="{{ route('home') }}" class="hover:text-white">Programs</a>
                        <a href="{{ route('home') }}#contact" class="hover:text-white">Contact us</a>
                        @auth
                            <a href="{{ route('family.index') }}" class="hover:text-white">My Family</a>
                        @else
                            <a href="{{ route('login') }}" class="hover:text-white">Log in</a>
                        @endauth
                        <span class="inline-flex items-center gap-1.5 text-blue-200/60"><svg class="h-3.5 w-3.5 stroke-emerald-400" viewBox="0 0 24 24" fill="none" stroke-width="2.4"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/></svg> Secure payments via FPX</span>
                    </nav>
                </div>
            </footer>
        </div>

        @livewireScripts
    </body>
</html>
