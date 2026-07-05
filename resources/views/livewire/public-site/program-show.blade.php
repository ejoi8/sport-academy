<div class="space-y-8">
    <section class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <a href="{{ route('home') }}" class="text-sm text-zinc-500 hover:text-zinc-800">Programs</a>
                <h1 class="mt-3 text-3xl font-semibold text-zinc-950">{{ $program->name }}</h1>
                <p class="mt-3 text-sm leading-6 text-zinc-600 sm:text-base">{{ $program->description ?: 'Monthly academy training with attendance tracking and progress scoring.' }}</p>
            </div>
            <div class="rounded-lg bg-[linear-gradient(135deg,#052e16_0%,#0f766e_100%)] px-5 py-4 text-white">
                <p class="text-xs uppercase tracking-wide text-emerald-100">Walk-in fee</p>
                <p class="mt-2 text-2xl font-semibold">RM{{ number_format($program->walk_in_fee_sen / 100, 2) }}</p>
            </div>
        </div>
    </section>

    <section class="space-y-4">
        @foreach ($program->offerings->groupBy('period') as $period => $offerings)
            <div class="space-y-3">
                <h2 class="text-lg font-semibold text-zinc-900">{{ \Illuminate\Support\Carbon::parse($period.'-01')->format('F Y') }}</h2>
                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach ($offerings as $offering)
                        <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-zinc-950">{{ $offering->scheduleLabel() }}</h3>
                                    <p class="mt-1 text-sm text-zinc-500">{{ $offering->session_count }} session credits · {{ $offering->monthLabel() }}</p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $offering->isFull() ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $offering->isFull() ? 'Full' : $offering->seatsLeft().' seats left' }}
                                </span>
                            </div>

                            <dl class="mt-4 grid gap-2 text-sm text-zinc-700">
                                <div class="flex items-center justify-between">
                                    <dt>Monthly fee</dt>
                                    <dd class="font-medium text-zinc-950">RM{{ number_format($offering->price_sen / 100, 2) }}</dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt>Head coach</dt>
                                    <dd class="font-medium text-zinc-950">{{ $offering->defaultCoach?->name ?? 'Assigned by academy' }}</dd>
                                </div>
                            </dl>

                            <div class="mt-5">
                                @if ($offering->isFull())
                                    <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">Class full — contact us.</div>
                                @else
                                    <a href="{{ route('bookings.create', $offering) }}" class="inline-flex w-full items-center justify-center rounded-md bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-800">Book this class</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @endforeach
    </section>
</div>
