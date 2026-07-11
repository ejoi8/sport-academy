<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ? $title.' · '.config('app.name', 'Football Academy') : config('app.name', 'Football Academy') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        <style>[x-cloak]{ display:none !important; }</style>
    </head>
    <body class="min-h-screen bg-[#f6f8fb] text-slate-900 antialiased">
        @php($appName = config('app.name') ?: 'Academy')
        @php($appInitials = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::of($appName)->explode(' ')->filter()->take(2)->map(fn ($word) => \Illuminate\Support\Str::substr($word, 0, 1))->implode('')) ?: 'FA')
        <div class="min-h-screen">
            <header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/80 backdrop-blur" x-data="{ open: false }" @keydown.escape.window="open = false">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3.5 sm:px-6">
                    <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2.5">
                        <span class="grid h-9 w-9 flex-none place-items-center rounded-xl bg-[linear-gradient(150deg,#2563eb,#1d4ed8)] text-sm font-extrabold text-white shadow-[0_6px_16px_-6px_rgba(37,99,235,0.7)]">{{ $appInitials }}</span>
                        <span class="truncate text-base font-extrabold tracking-tight text-slate-900">{{ $appName }}</span>
                    </a>

                    {{-- Desktop nav --}}
                    <nav class="hidden items-center gap-1.5 text-sm font-semibold sm:flex">
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

                    {{-- Mobile hamburger --}}
                    <button type="button" class="grid h-9 w-9 flex-none place-items-center rounded-lg text-slate-700 hover:bg-slate-100 sm:hidden" @click="open = ! open" :aria-expanded="open" aria-label="Toggle menu">
                        <svg x-show="! open" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                        <svg x-show="open" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>

                {{-- Mobile menu --}}
                <div x-show="open" x-cloak x-transition.origin.top class="border-t border-slate-200/70 bg-white sm:hidden" @click="open = false">
                    <nav class="mx-auto flex max-w-6xl flex-col gap-1 px-4 py-2 text-sm font-semibold sm:px-6">
                        <a href="{{ route('home') }}" class="rounded-lg px-3 py-2.5 text-slate-700 hover:bg-slate-100">Programs</a>
                        @auth
                            <a href="{{ route('family.index') }}" class="rounded-lg px-3 py-2.5 text-slate-700 hover:bg-slate-100">My Family</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full rounded-lg px-3 py-2.5 text-left text-slate-700 hover:bg-slate-100">Log out</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="rounded-lg px-3 py-2.5 text-slate-700 hover:bg-slate-100">Log in</a>
                            <a href="{{ route('register') }}" class="fa-btn-primary mt-1 justify-center">Create account</a>
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
                        <span class="grid h-9 w-9 place-items-center rounded-xl bg-white/10 text-xs font-extrabold text-white ring-1 ring-white/20">{{ $appInitials }}</span>
                        <div>
                            <p class="text-sm font-extrabold text-white">{{ $appName }}</p>
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
