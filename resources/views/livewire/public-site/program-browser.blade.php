<div class="space-y-8">
    <section class="relative overflow-hidden rounded-3xl border border-slate-200/80 bg-white p-7 shadow-sm sm:p-10">
        <div class="pointer-events-none absolute -right-24 -top-28 h-80 w-80 rounded-full bg-[radial-gradient(circle,rgba(37,99,235,0.14),transparent_62%)]"></div>
        <div class="relative grid gap-8 lg:grid-cols-[1.2fr_0.8fr] lg:items-center">
            <div class="space-y-4">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-blue-700">Open for booking</span>
                <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">Programs for this month and next</h1>
                <p class="max-w-2xl text-sm leading-6 text-slate-500 sm:text-base">Choose the class that fits your child, review the session-credit rules up front, and send a booking through in a few minutes.</p>
                <div class="flex flex-wrap gap-3 pt-1 text-sm font-semibold text-slate-500">
                    <span class="inline-flex items-center gap-2"><svg class="h-4 w-4 stroke-emerald-600" viewBox="0 0 24 24" fill="none" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg> Secure online payment</span>
                    <span class="inline-flex items-center gap-2"><svg class="h-4 w-4 stroke-emerald-600" viewBox="0 0 24 24" fill="none" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg> Credits never expire</span>
                </div>
            </div>
            <div class="grid gap-3 rounded-2xl border border-slate-200 bg-[#f6f8fb] p-4 sm:grid-cols-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Programs</p>
                    <p class="mt-2 text-2xl font-extrabold text-slate-900">{{ $programs->count() }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Current month</p>
                    <p class="mt-2 text-lg font-bold text-slate-900">{{ now()->format('F Y') }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Next month</p>
                    <p class="mt-2 text-lg font-bold text-slate-900">{{ now()->addMonth()->format('F Y') }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($programs as $program)
            @php($featured = $program->offerings->first())
            <article class="flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-end justify-between gap-3 bg-[linear-gradient(150deg,#2563eb,#1d4ed8)] px-5 pb-4 pt-6">
                    <h2 class="text-xl font-extrabold tracking-tight text-white drop-shadow">{{ $program->name }}</h2>
                    @if ($featured)
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $featured->isFull() ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }}">
                            {{ $featured->isFull() ? 'Full' : $featured->seatsLeft().' seats left' }}
                        </span>
                    @endif
                </div>

                <div class="flex flex-1 flex-col p-5">
                    <p class="text-sm leading-6 text-slate-500">{{ $program->description ?: 'Structured monthly training with tracked attendance and skill scoring.' }}</p>

                    @if ($featured)
                        <dl class="mt-5 grid gap-3 text-sm text-slate-600">
                            <div class="flex items-center justify-between border-t border-slate-100 pt-3">
                                <dt>Price</dt>
                                <dd class="text-base font-extrabold text-slate-900">RM{{ number_format($featured->price_sen / 100, 2) }}</dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt>Schedule</dt>
                                <dd class="font-bold text-slate-900">{{ $featured->scheduleLabel() }}</dd>
                            </div>
                        </dl>
                    @endif

                    <div class="mt-5 flex flex-wrap gap-2">
                        @foreach ($program->offerings->take(2) as $offering)
                            <span class="fa-pill">{{ $offering->monthLabel() }}</span>
                        @endforeach
                    </div>

                    <div class="mt-6 pt-1">
                        <a href="{{ route('programs.show', $program) }}" class="fa-btn-primary w-full">View classes</a>
                    </div>
                </div>
            </article>
        @endforeach
    </section>
</div>
