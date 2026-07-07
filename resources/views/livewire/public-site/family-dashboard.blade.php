<div class="space-y-8">
    <section class="fa-card p-6 sm:p-7">
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">My Family</h1>
        <p class="mt-2 text-sm text-slate-500">Children, bookings, session usage, and anything still awaiting payment confirmation.</p>
    </section>

    <section class="space-y-4">
        @forelse ($user?->students ?? [] as $student)
            <article class="fa-card p-5 sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-xl font-extrabold text-slate-900">{{ $student->name }}</h2>
                        <p class="mt-1 text-sm text-slate-500">Lifetime: {{ $student->creditSummary()['attended'] }}/{{ $student->creditSummary()['purchased'] }} used · +{{ $student->carriedCreditsCount() }} carried</p>
                    </div>
                    <a href="{{ route('students.report', $student) }}" target="_blank"
                        class="fa-btn-ghost self-start px-3 py-1.5 text-xs sm:self-end">
                        Progress report
                    </a>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse ($student->enrollments as $enrollment)
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="font-bold text-slate-900">{{ $enrollment->offering?->program?->name ?? 'Program' }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $enrollment->offering?->scheduleLabel() ?? 'Timeslot' }} · {{ $enrollment->offering?->monthLabel() ?? '' }}</p>
                                    @if ($enrollment->booking_reference)
                                        <p class="mt-1 text-xs font-medium text-slate-400">Reference: {{ $enrollment->booking_reference }}</p>
                                    @endif
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ match($enrollment->status->value) { 'pending' => 'bg-amber-50 text-amber-700', 'active' => 'bg-emerald-50 text-emerald-700', 'overdue' => 'bg-red-50 text-red-700', default => 'bg-slate-100 text-slate-600' } }}">
                                    {{ $enrollment->status->getLabel() }}
                                </span>
                            </div>

                            @php($total = max(1, (int) $enrollment->sessions_included))
                            @php($pct = min(100, (int) round(((int) $enrollment->used_credits / $total) * 100)))
                            <div class="mt-4">
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-[linear-gradient(90deg,#2563eb,#1d4ed8)]" style="width: {{ $pct }}%"></div>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs font-semibold text-slate-500">
                                    <span>{{ $enrollment->used_credits }} / {{ $enrollment->sessions_included }} sessions used</span>
                                    @if ($enrollment->creditsRemaining() > 0)
                                        <span class="text-emerald-700">+{{ $enrollment->creditsRemaining() }} remaining</span>
                                    @endif
                                    <span class="text-slate-400">RM{{ number_format($enrollment->price_sen / 100, 2) }}</span>
                                    @if ($enrollment->latestPayment)
                                        <span class="text-slate-400">Payment: {{ ucfirst($enrollment->latestPayment->status->value) }}</span>
                                    @endif
                                </div>
                            </div>

                            @if ($enrollment->status->value === 'pending')
                                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                    {{ \App\Support\PaymentInstructions::summary() }}
                                </div>
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
                                        <button type="submit" class="fa-btn-primary">Pay now</button>
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
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No enrolments yet.</p>
                    @endforelse
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
                No children on this account yet. Browse programs and book the first class.
            </div>
        @endforelse
    </section>
</div>
