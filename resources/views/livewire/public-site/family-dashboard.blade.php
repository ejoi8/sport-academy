<div class="space-y-6">
    @php($students = $user?->students ?? collect())
    @php($pendingCount = $students->flatMap->enrollments->where('status.value', 'pending')->count())

    <section class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">My Family</h1>
            <p class="mt-1 text-sm text-slate-500">Bookings, session credits, and progress — all in one place.</p>
        </div>
        <a href="{{ route('home') }}" class="fa-btn-soft self-start px-4 py-2 text-xs sm:self-end">＋ Book another class</a>
    </section>

    {{-- Action first: anything awaiting payment is the one thing a parent must not miss. --}}
    @if ($pendingCount > 0)
        <section class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
            <span class="text-xl" aria-hidden="true">⏳</span>
            <p class="flex-1 text-sm font-semibold text-amber-900">
                {{ $pendingCount === 1 ? '1 booking is' : $pendingCount.' bookings are' }} awaiting payment — the spot is held, but not confirmed yet.
            </p>
        </section>
    @endif

    <section class="space-y-5">
        @forelse ($students as $student)
            <article class="fa-card overflow-hidden">
                <div class="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3.5">
                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-[radial-gradient(120%_120%_at_50%_0%,#eff4fe,#e6edfb)] text-base font-extrabold text-blue-700">{{ mb_substr($student->name, 0, 1) }}</span>
                        <div>
                            <h2 class="text-lg font-extrabold tracking-tight text-slate-900">{{ $student->name }}</h2>
                            <p class="text-xs font-semibold text-slate-400">Lifetime: {{ $student->creditSummary()['attended'] }}/{{ $student->creditSummary()['purchased'] }} used · +{{ $student->carriedCreditsCount() }} carried</p>
                        </div>
                    </div>
                    <a href="{{ route('students.report', $student) }}" target="_blank" class="fa-btn-ghost self-start px-3.5 py-2 text-xs sm:self-auto">
                        📈 Progress report
                    </a>
                </div>

                <div class="space-y-3 p-5">
                    @forelse ($student->enrollments as $enrollment)
                        @php($isPending = $enrollment->status->value === 'pending')
                        @php($next = $enrollment->status->value === 'active' ? $enrollment->offering?->nearestOccurrence() : null)
                        <div id="enrollment-{{ $enrollment->id }}" class="rounded-2xl border p-4 {{ $isPending ? 'border-amber-200 bg-amber-50/40' : 'border-slate-200 bg-white' }}">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 text-2xl" aria-hidden="true">{{ $enrollment->offering?->program?->emoji() ?? '⚽' }}</span>
                                    <div>
                                        <p class="font-extrabold text-slate-900">{{ $enrollment->offering?->program?->name ?? 'Program' }}</p>
                                        <p class="mt-0.5 text-sm text-slate-500">{{ $enrollment->offering?->scheduleLabel() ?? 'Timeslot' }} · {{ $enrollment->offering?->monthLabel() ?? '' }}</p>
                                        @if ($next && $next->gte(today()))
                                            <p class="mt-1 text-xs font-bold text-blue-700">Next session: {{ $next->format('D, j M') }}</p>
                                        @endif
                                        @if ($enrollment->booking_reference)
                                            <p class="mt-1 text-[11px] font-semibold text-slate-400">Ref: {{ $enrollment->booking_reference }}</p>
                                        @endif
                                    </div>
                                </div>
                                <span class="self-start rounded-full px-2.5 py-1 text-xs font-bold {{ match($enrollment->status->value) { 'pending' => 'bg-amber-100 text-amber-800', 'active' => 'bg-emerald-50 text-emerald-700', 'overdue' => 'bg-red-50 text-red-700', default => 'bg-slate-100 text-slate-600' } }}">
                                    {{ $enrollment->status->getLabel() }}
                                </span>
                            </div>

                            @php($total = max(1, (int) $enrollment->sessions_included))
                            @php($pct = min(100, (int) round(((int) $enrollment->used_credits / $total) * 100)))
                            <div class="mt-4">
                                <div class="flex items-center justify-between text-xs font-bold">
                                    <span class="text-slate-500">{{ $enrollment->used_credits }} / {{ $enrollment->sessions_included }} sessions used</span>
                                    @if ($enrollment->creditsRemaining() > 0)
                                        <span class="text-emerald-600">{{ $enrollment->creditsRemaining() }} left</span>
                                    @endif
                                </div>
                                <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-[linear-gradient(90deg,#2563eb,#1d4ed8)]" style="width: {{ $pct }}%"></div>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs font-semibold text-slate-400">
                                    <span>RM{{ number_format($enrollment->price_sen / 100, 2) }}</span>
                                    @if ($enrollment->latestPayment)
                                        <span>Payment: {{ ucfirst($enrollment->latestPayment->status->value) }}</span>
                                    @endif
                                </div>
                            </div>

                            @if ($isPending)
                                <div class="mt-4 border-t border-amber-200/60 pt-4">
                                    <p class="text-sm font-medium text-amber-900">{{ \App\Support\PaymentInstructions::summary() }}</p>

                                    @if ($gatewayEnabled && $enrollment->source === 'online')
                                        <form method="POST" action="{{ route('payments.checkout', $enrollment) }}" class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
                                            @csrf
                                            <div class="sm:max-w-xs sm:flex-1">
                                                <label for="gateway-{{ $enrollment->id }}" class="fa-label">Payment provider</label>
                                                <select id="gateway-{{ $enrollment->id }}" name="gateway" class="fa-input">
                                                    @foreach ($gatewayOptions as $gateway => $label)
                                                        <option value="{{ $gateway }}" @selected($gateway === $defaultGateway)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <button type="submit" class="fa-btn-primary">Pay now · RM{{ number_format($enrollment->price_sen / 100, 2) }}</button>
                                        </form>

                                        <details class="group mt-3">
                                            <summary class="cursor-pointer text-sm font-semibold text-slate-500 hover:text-slate-900">
                                                Paid by bank transfer? Upload your receipt
                                            </summary>
                                            <div class="mt-1">
                                                @livewire('public-site.proof-upload', ['enrollment' => $enrollment], key('proof-upload-'.$enrollment->id))
                                            </div>
                                        </details>
                                    @elseif ($enrollment->source === 'online')
                                        <div class="mt-3">
                                            <p class="text-sm font-semibold text-slate-700">Paid by bank transfer? Upload your receipt</p>
                                            @livewire('public-site.proof-upload', ['enrollment' => $enrollment], key('proof-upload-'.$enrollment->id))
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No enrolments yet.</p>
                    @endforelse
                </div>
            </article>
        @empty
            <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center">
                <p class="text-4xl" aria-hidden="true">⚽</p>
                <p class="mt-3 font-extrabold text-slate-900">No children on this account yet</p>
                <p class="mt-1 text-sm text-slate-500">Browse programs and book the first class — it takes about two minutes.</p>
                <a href="{{ route('home') }}" class="fa-btn-primary mt-5">Find a class</a>
            </div>
        @endforelse
    </section>
</div>
