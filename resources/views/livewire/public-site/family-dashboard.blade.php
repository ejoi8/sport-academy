<div class="space-y-8">
    <section class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
        <h1 class="text-3xl font-semibold text-zinc-950">My Family</h1>
        <p class="mt-2 text-sm text-zinc-500">Children, bookings, session usage, and anything still awaiting payment confirmation.</p>
    </section>

    <section class="space-y-4">
        @forelse ($user?->students ?? [] as $student)
            <article class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-zinc-950">{{ $student->name }}</h2>
                        <p class="mt-1 text-sm text-zinc-500">Lifetime: {{ $student->creditSummary()['attended'] }}/{{ $student->creditSummary()['purchased'] }} used · +{{ $student->carriedCreditsCount() }} carried</p>
                    </div>
                    <a href="{{ route('students.report', $student) }}" target="_blank"
                        class="inline-flex items-center gap-1 self-start rounded-md border border-zinc-300 px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 sm:self-end">
                        Progress report
                    </a>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse ($student->enrollments as $enrollment)
                        <div class="rounded-md border border-zinc-200 bg-zinc-50 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="font-medium text-zinc-950">{{ $enrollment->offering?->program?->name ?? 'Program' }}</p>
                                    <p class="mt-1 text-sm text-zinc-500">{{ $enrollment->offering?->scheduleLabel() ?? 'Timeslot' }} · {{ $enrollment->offering?->monthLabel() ?? '' }}</p>
                                    @if ($enrollment->booking_reference)
                                        <p class="mt-1 text-sm text-zinc-500">Reference: {{ $enrollment->booking_reference }}</p>
                                    @endif
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ match($enrollment->status->value) { 'pending' => 'bg-amber-100 text-amber-800', 'active' => 'bg-emerald-100 text-emerald-800', 'overdue' => 'bg-red-100 text-red-700', default => 'bg-zinc-200 text-zinc-700' } }}">
                                    {{ $enrollment->status->getLabel() }}
                                </span>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-3 text-sm text-zinc-700">
                                <span class="rounded-full bg-white px-3 py-1">{{ $enrollment->used_credits }} / {{ $enrollment->sessions_included }} used</span>
                                @if ($enrollment->creditsRemaining() > 0)
                                    <span class="rounded-full bg-white px-3 py-1">+{{ $enrollment->creditsRemaining() }} carried</span>
                                @endif
                                <span class="rounded-full bg-white px-3 py-1">RM{{ number_format($enrollment->price_sen / 100, 2) }}</span>
                                @if ($enrollment->latestPayment)
                                    <span class="rounded-full bg-white px-3 py-1">Payment: {{ ucfirst($enrollment->latestPayment->status->value) }}</span>
                                @endif
                            </div>

                            @if ($enrollment->status->value === 'pending')
                                <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                    {{ \App\Support\PaymentInstructions::summary() }}
                                </div>
                                @if ($gatewayEnabled && $enrollment->source === 'online')
                                    <form method="POST" action="{{ route('payments.checkout', $enrollment) }}" class="mt-3 space-y-3">
                                        @csrf
                                        <div>
                                            <label for="gateway-{{ $enrollment->id }}" class="block text-sm font-medium text-zinc-700">Payment provider</label>
                                            <select id="gateway-{{ $enrollment->id }}" name="gateway" class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 sm:max-w-sm">
                                                @foreach ($gatewayOptions as $gateway => $label)
                                                    <option value="{{ $gateway }}" @selected($gateway === $defaultGateway)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="submit" class="inline-flex rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">
                                            Pay now
                                        </button>
                                    </form>

                                    <details class="mt-3 group">
                                        <summary class="cursor-pointer text-sm font-medium text-zinc-600 hover:text-zinc-900">
                                            Paid by bank transfer? Upload your receipt
                                        </summary>
                                        <div class="mt-1">
                                            @livewire('public-site.proof-upload', ['enrollment' => $enrollment], key('proof-upload-'.$enrollment->id))
                                        </div>
                                    </details>
                                @elseif ($enrollment->source === 'online')
                                    <div class="mt-3">
                                        <p class="text-sm font-medium text-zinc-700">Paid by bank transfer? Upload your receipt</p>
                                        @livewire('public-site.proof-upload', ['enrollment' => $enrollment], key('proof-upload-'.$enrollment->id))
                                    </div>
                                @endif
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-zinc-500">No enrolments yet.</p>
                    @endforelse
                </div>
            </article>
        @empty
            <div class="rounded-lg border border-dashed border-zinc-300 bg-white p-10 text-center text-sm text-zinc-500">
                No children on this account yet. Browse programs and book the first class.
            </div>
        @endforelse
    </section>
</div>
