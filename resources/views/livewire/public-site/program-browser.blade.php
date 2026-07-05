<div class="space-y-8">
    <section class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr] lg:items-end">
        <div class="space-y-3">
            <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium uppercase tracking-wide text-emerald-800">Open for booking</span>
            <h1 class="text-3xl font-semibold text-zinc-950 sm:text-4xl">Programs for this month and next</h1>
            <p class="max-w-2xl text-sm leading-6 text-zinc-600 sm:text-base">Choose the class that fits your child, review the session-credit rules up front, and send a booking through in a few minutes.</p>
        </div>
        <div class="grid gap-3 rounded-lg border border-zinc-200 bg-white p-4 shadow-sm sm:grid-cols-3">
            <div>
                <p class="text-xs uppercase tracking-wide text-zinc-500">Programs</p>
                <p class="mt-2 text-2xl font-semibold text-zinc-950">{{ $programs->count() }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-zinc-500">Current month</p>
                <p class="mt-2 text-lg font-medium text-zinc-900">{{ now()->format('F Y') }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-zinc-500">Next month</p>
                <p class="mt-2 text-lg font-medium text-zinc-900">{{ now()->addMonth()->format('F Y') }}</p>
            </div>
        </div>
    </section>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($programs as $program)
            @php($featured = $program->offerings->first())
            <article class="flex h-full flex-col rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-zinc-950">{{ $program->name }}</h2>
                        <p class="mt-2 text-sm leading-6 text-zinc-600">{{ $program->description ?: 'Structured monthly training with tracked attendance and skill scoring.' }}</p>
                    </div>
                    <div class="h-12 w-12 shrink-0 rounded-md bg-[linear-gradient(135deg,#0f766e_0%,#14532d_100%)]"></div>
                </div>

                @if ($featured)
                    <dl class="mt-5 grid gap-3 text-sm text-zinc-700">
                        <div class="flex items-center justify-between border-t border-zinc-100 pt-3">
                            <dt>Price</dt>
                            <dd class="font-medium text-zinc-950">RM{{ number_format($featured->price_sen / 100, 2) }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt>Schedule</dt>
                            <dd class="font-medium text-zinc-950">{{ $featured->scheduleLabel() }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt>Seats left</dt>
                            <dd class="font-medium {{ $featured->isFull() ? 'text-red-700' : 'text-emerald-700' }}">
                                {{ $featured->isFull() ? 'Full' : $featured->seatsLeft().' seats left' }}
                            </dd>
                        </div>
                    </dl>
                @endif

                <div class="mt-5 flex flex-wrap gap-2 text-xs text-zinc-500">
                    @foreach ($program->offerings->take(2) as $offering)
                        <span class="rounded-full bg-zinc-100 px-2.5 py-1">{{ $offering->monthLabel() }}</span>
                    @endforeach
                </div>

                <div class="mt-6">
                    <a href="{{ route('programs.show', $program) }}" class="inline-flex w-full items-center justify-center rounded-md bg-zinc-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800">View classes</a>
                </div>
            </article>
        @endforeach
    </section>
</div>
