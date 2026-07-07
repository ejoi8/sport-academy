<div class="space-y-8">
    <section class="fa-card p-6 sm:p-7">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <a href="{{ route('home') }}" class="text-sm font-semibold text-slate-500 hover:text-blue-700">← Programs</a>
                <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">{{ $program->name }}</h1>
                <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">{{ $program->description ?: 'Monthly academy training with attendance tracking and progress scoring.' }}</p>
            </div>
            <div class="rounded-2xl border border-blue-100 bg-blue-50 px-5 py-4">
                <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Walk-in fee</p>
                <p class="mt-2 text-2xl font-extrabold text-blue-900">RM{{ number_format($program->walk_in_fee_sen / 100, 2) }}</p>
            </div>
        </div>
    </section>

    <section class="space-y-5">
        @foreach ($program->offerings->groupBy('period') as $period => $offerings)
            <div class="space-y-3">
                <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">{{ \Illuminate\Support\Carbon::parse($period.'-01')->format('F Y') }}</h2>
                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach ($offerings as $offering)
                        <article class="fa-card p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-lg font-extrabold text-slate-900">{{ $offering->scheduleLabel() }}</h3>
                                    <p class="mt-1 text-sm text-slate-500">{{ $offering->session_count }} session credits · {{ $offering->monthLabel() }}</p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $offering->isFull() ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }}">
                                    {{ $offering->isFull() ? 'Full' : $offering->seatsLeft().' seats left' }}
                                </span>
                            </div>

                            <dl class="mt-4 grid gap-2 text-sm text-slate-600">
                                <div class="flex items-center justify-between border-t border-slate-100 pt-3">
                                    <dt>Monthly fee</dt>
                                    <dd class="font-extrabold text-slate-900">RM{{ number_format($offering->price_sen / 100, 2) }}</dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt>Head coach</dt>
                                    <dd class="font-bold text-slate-900">{{ $offering->defaultCoach?->name ?? 'Assigned by academy' }}</dd>
                                </div>
                            </dl>

                            <div class="mt-5">
                                @if ($offering->isFull())
                                    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">Class full — contact us.</div>
                                @else
                                    <a href="{{ route('bookings.create', $offering) }}" class="fa-btn-primary w-full">Book this class</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @endforeach
    </section>
</div>
