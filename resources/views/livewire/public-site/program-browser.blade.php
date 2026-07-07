@php($days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'])

<div class="space-y-12">
    {{-- ============ Hero: copy left, photo-over-pitch right ============ --}}
    <section class="relative overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-sm">
        <div class="grid lg:grid-cols-[1.05fr_0.95fr]">
            <div class="relative space-y-5 p-7 sm:p-10">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-blue-700">Open for booking</span>
                <h1 class="text-3xl font-extrabold leading-tight tracking-tight text-slate-900 sm:text-[2.7rem] sm:leading-[1.08]">
                    Real coaching for young players.<br>
                    <span class="bg-[linear-gradient(150deg,#2563eb,#1d4ed8)] bg-clip-text text-transparent">Booked in minutes.</span>
                </h1>
                <p class="max-w-xl text-sm leading-6 text-slate-500 sm:text-base">Weekly training and 1-on-1 sessions with tracked attendance and skill scores. Pick a class below, reserve your child's place, and pay online.</p>
                <div class="flex flex-wrap items-center gap-4">
                    <a href="#programs" class="fa-btn-primary px-6 py-3 text-base">Find a class
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14m0 0l-6-6m6 6l6-6"/></svg>
                    </a>
                    @guest
                        <a href="{{ route('login') }}" class="text-sm font-bold text-slate-500 hover:text-blue-700">Already a member? Log in</a>
                    @else
                        <a href="{{ route('family.index') }}" class="text-sm font-bold text-slate-500 hover:text-blue-700">Go to My Family →</a>
                    @endguest
                </div>
                <div class="flex flex-wrap gap-x-5 gap-y-2 pt-1 text-xs font-bold text-slate-400">
                    <span class="inline-flex items-center gap-1.5"><svg class="h-4 w-4 stroke-emerald-600" viewBox="0 0 24 24" fill="none" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg> Secure FPX payment</span>
                    <span class="inline-flex items-center gap-1.5"><svg class="h-4 w-4 stroke-emerald-600" viewBox="0 0 24 24" fill="none" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg> Credits never expire</span>
                    <span class="inline-flex items-center gap-1.5"><svg class="h-4 w-4 stroke-emerald-600" viewBox="0 0 24 24" fill="none" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg> Progress reports included</span>
                </div>
            </div>

            <div class="fa-grain relative min-h-[260px] overflow-hidden bg-[linear-gradient(160deg,#1d4ed8,#0f2d7a)] lg:min-h-full">
                <x-program-art seed="0"/>
                <div class="absolute bottom-4 left-4 right-4 flex items-center gap-3 rounded-2xl bg-white/90 p-3 shadow-lg backdrop-blur sm:left-6 sm:bottom-6 sm:right-auto">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-[linear-gradient(150deg,#2563eb,#1d4ed8)] text-lg" aria-hidden="true">⚽</span>
                    <div class="pr-2">
                        <p class="text-xs font-extrabold text-slate-900">Every session recorded</p>
                        <p class="text-[11px] font-semibold text-slate-500">Attendance · skill scores 1–5 · credits</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ How it works ============ --}}
    <section class="grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['1', '⚽', 'Pick a class', 'Choose a day and time that fits — seats update live.'],
            ['2', '💳', 'Book & pay online', 'Reserve the spot in a 3-step form. FPX or bank transfer.'],
            ['3', '📈', 'Track every session', 'Attendance, skill scores and credits — all in My Family.'],
        ] as [$n, $icon, $title, $body])
            <div class="fa-card flex items-start gap-4 p-5">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-[radial-gradient(120%_120%_at_50%_0%,#eff4fe,#e6edfb)] text-2xl" aria-hidden="true">{{ $icon }}</span>
                <div>
                    <p class="text-sm font-extrabold text-slate-900"><span class="mr-1.5 text-blue-600">{{ $n }}.</span>{{ $title }}</p>
                    <p class="mt-1 text-xs leading-5 text-slate-500">{{ $body }}</p>
                </div>
            </div>
        @endforeach
    </section>

    {{-- ============ Programs with bookable slots inline ============ --}}
    <section id="programs" class="scroll-mt-24 space-y-6">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-blue-700">This month &amp; next</p>
            <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">Programs for this month and next</h2>
            <p class="mt-1 text-sm text-slate-500">Every class shows its live seat count — tap Book on the slot you want.</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            @foreach ($programs as $program)
                @php($theme = $program->theme())
                <article class="flex h-full flex-col overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm transition hover:shadow-[0_28px_50px_-28px_rgba(15,23,42,0.4)]">
                    {{-- illustrated tile: theme gradient + pitch/ball artwork, arrangement varies per program --}}
                    <div class="fa-grain relative h-48 sm:h-56" style="background:linear-gradient(155deg,{{ $theme['from'] }},{{ $theme['to'] }})">
                        <x-program-art :seed="$program->id"/>
                        <div class="absolute bottom-4 left-5 right-5 z-10 flex items-end justify-between gap-3">
                            <h3 class="text-2xl font-extrabold tracking-tight text-white drop-shadow">{{ $program->name }}</h3>
                            <span class="rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-extrabold backdrop-blur" style="color:{{ $theme['ink'] }}">{{ $program->offerings->count() }} {{ \Illuminate\Support\Str::plural('slot', $program->offerings->count()) }}</span>
                        </div>
                    </div>

                    <div class="flex flex-1 flex-col gap-3 p-4">
                        <p class="line-clamp-2 px-1 text-xs leading-5 text-slate-500">{{ $program->description ?: 'Structured monthly training with tracked attendance and skill scoring.' }}</p>

                        @foreach ($program->offerings as $offering)
                            @php($ratio = $offering->capacity > 0 ? $offering->heldSeatsCount() / $offering->capacity : 1)
                            <div class="rounded-2xl border p-3 {{ $offering->isFull() ? 'border-slate-200 bg-slate-50' : 'border-slate-200 bg-white' }}">
                                <div class="flex items-center gap-3">
                                    <div class="fa-daychip">
                                        @if ($offering->schedule_type === \App\Enums\ScheduleType::OneOff && $offering->specific_date)
                                            <span class="d" style="background:{{ $theme['from'] }}">{{ $offering->specific_date->format('j M') }}</span>
                                        @else
                                            <span class="d" style="background:{{ $theme['from'] }}">{{ $days[$offering->weekday] ?? '—' }}</span>
                                        @endif
                                        <span class="t">{{ substr((string) $offering->start_time, 0, 5) }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-extrabold text-slate-900">{{ $offering->monthLabel() }}</p>
                                        <p class="mt-0.5 flex items-center gap-1.5 text-xs font-semibold text-slate-400">
                                            @if ($offering->defaultCoach)
                                                <span class="grid h-4.5 w-4.5 place-items-center rounded-full text-[9px] font-extrabold text-white" style="background:linear-gradient(150deg,{{ $theme['from'] }},{{ $theme['to'] }})">{{ mb_substr($offering->defaultCoach->name, 0, 1) }}</span>
                                                <span class="truncate">Coach {{ \Illuminate\Support\Str::before($offering->defaultCoach->name, ' ') }}</span>
                                                <span aria-hidden="true">·</span>
                                            @endif
                                            <span>{{ $offering->session_count }} sessions</span>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="whitespace-nowrap text-base font-extrabold tabular-nums text-slate-900"><span class="align-top text-[10px] font-bold text-slate-400">RM</span>{{ number_format($offering->price_sen / 100, 0) }}</p>
                                        @if ($offering->session_count > 1)
                                            <p class="text-[10px] font-semibold text-slate-400">≈ RM{{ number_format($offering->price_sen / 100 / $offering->session_count, 0) }} / session</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-3 flex items-center gap-3">
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full {{ $ratio >= 1 ? 'bg-red-400' : ($ratio >= 0.75 ? 'bg-amber-400' : 'bg-emerald-400') }}" style="width: {{ min(100, (int) round($ratio * 100)) }}%"></div>
                                    </div>
                                    @if ($offering->isFull())
                                        <span class="rounded-xl bg-slate-200 px-3.5 py-2 text-xs font-bold text-slate-500">Full</span>
                                    @else
                                        <span class="whitespace-nowrap text-xs font-bold {{ $ratio >= 0.75 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $offering->seatsLeft() }} seats left</span>
                                        <a href="{{ route('bookings.create', $offering) }}" class="fa-btn-primary px-4 py-2 text-xs">Book</a>
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        <a href="{{ route('programs.show', $program) }}" class="mt-auto inline-flex items-center gap-1 px-1 pt-1 text-xs font-bold text-slate-400 transition hover:text-blue-700">
                            More about this class
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12h14m0 0l-6-6m6 6l-6 6"/></svg>
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    {{-- ============ Dark band: honest numbers from the academy's own records ============ --}}
    @if ($stats)
        <section class="fa-grain relative overflow-hidden rounded-[2rem] bg-[linear-gradient(160deg,#0f2d7a,#091b4a)] shadow-[0_30px_60px_-30px_rgba(9,27,74,0.7)]">
            <x-pitch-lines opacity="0.12"/>
            <div class="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-[radial-gradient(circle,rgba(96,165,250,0.35),transparent_65%)]"></div>
            <div class="relative grid gap-8 p-8 sm:p-12 lg:grid-cols-[1fr_auto] lg:items-center">
                <div class="max-w-md">
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-blue-300">Why train with us</p>
                    <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-white sm:text-3xl">Every session counts.<br>Literally.</h2>
                    <p class="mt-3 text-sm leading-6 text-blue-100/80">Nothing here is a marketing number — it's the academy's own training records: every attendance marked and every skill scored, session by session.</p>
                </div>
                <div class="grid grid-cols-3 gap-6 sm:gap-10">
                    @foreach ([
                        [number_format($stats['students']), 'Active players'],
                        [number_format($stats['sessions']), 'Sessions delivered'],
                        [number_format($stats['scores']), 'Skill scores recorded'],
                    ] as [$n, $label])
                        <div>
                            <p class="text-3xl font-extrabold tabular-nums tracking-tight text-white sm:text-4xl">{{ $n }}</p>
                            <p class="mt-1 text-[11px] font-bold uppercase tracking-wide text-blue-300">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ============ Contact ============ --}}
    <section id="contact" class="scroll-mt-24">
        <div class="fa-card overflow-hidden">
            <div class="grid lg:grid-cols-[1fr_1.1fr]">
                <div class="flex flex-col justify-center gap-5 border-b border-slate-100 p-7 sm:p-9 lg:border-b-0 lg:border-r">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-blue-700">Get in touch</p>
                        <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">Contact us</h2>
                        <p class="mt-2 max-w-md text-sm leading-6 text-slate-500">Questions about age groups, skill levels or schedules? Send us a message and we'll help you choose the right class.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="https://wa.me/{{ $contact['whatsapp'] }}" target="_blank" rel="noopener" class="fa-btn-primary">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
                            Message us on WhatsApp
                        </a>
                        <a href="tel:{{ preg_replace('/[^+0-9]/', '', $contact['phone']) }}" class="fa-btn-ghost">Call {{ $contact['phone'] }}</a>
                    </div>
                    <p class="text-xs font-semibold text-slate-400">We usually reply within a few hours on training days.</p>
                </div>

                <div class="grid content-center gap-1 p-5 sm:grid-cols-2 sm:p-7">
                    @foreach ([
                        ['Phone', $contact['phone'], 'M3 5a2 2 0 012-2h2.2a1 1 0 01.95.68l1.2 3.6a1 1 0 01-.27 1.06L7.6 9.8a14.5 14.5 0 006.6 6.6l1.46-1.43a1 1 0 011.06-.27l3.6 1.2a1 1 0 01.68.95V19a2 2 0 01-2 2h-.5C10.4 21 3 13.6 3 4.5V5z'],
                        ['Email', $contact['email'], 'M4 6h16a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V7a1 1 0 011-1zm0 1.5l8 5.5 8-5.5'],
                        ['Location', $contact['address'], 'M12 21s-7-5.75-7-11a7 7 0 1114 0c0 5.25-7 11-7 11zm0-8.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z'],
                        ['Training hours', $contact['hours'], 'M12 21a9 9 0 110-18 9 9 0 010 18zm0-14v5l3 2'],
                    ] as [$label, $value, $path])
                        <div class="flex items-start gap-3.5 rounded-2xl p-3.5 transition hover:bg-slate-50">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-50">
                                <svg class="h-5 w-5 stroke-blue-600" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $path }}"/></svg>
                            </span>
                            <div class="min-w-0">
                                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ $label }}</p>
                                <p class="mt-0.5 break-words text-sm font-bold text-slate-900">{{ $value }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
</div>
