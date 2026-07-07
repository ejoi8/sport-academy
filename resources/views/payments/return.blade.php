<x-layouts.public title="Payment status">
    <div class="mx-auto max-w-xl space-y-5">
        <section class="fa-card p-7 text-center sm:p-8">
            @if ($payment && $payment->status->isPaid())
                <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-50">
                    <svg class="h-8 w-8 stroke-emerald-600" viewBox="0 0 24 24" fill="none" stroke-width="2.6"><path d="M20 6L9 17l-5-5"/></svg>
                </div>
                <h1 class="mt-4 text-2xl font-extrabold tracking-tight text-slate-900">Payment received 🎉</h1>
                <p class="mt-1 text-sm text-slate-500">Payment received. Your booking is being confirmed.</p>
            @elseif ($payment && $payment->status->isPending())
                <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-amber-50 text-3xl">⏳</div>
                <h1 class="mt-4 text-2xl font-extrabold tracking-tight text-slate-900">Almost there…</h1>
                <p class="mt-1 text-sm text-slate-500">We are still waiting for the gateway confirmation. Refresh this page in a moment if needed.</p>
                <a href="{{ url()->current() }}" class="fa-btn-soft mt-4">Check payment status again</a>
            @elseif ($payment)
                <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-red-50 text-3xl">✕</div>
                <h1 class="mt-4 text-2xl font-extrabold tracking-tight text-slate-900">Payment not completed</h1>
                <p class="mt-1 text-sm text-slate-500">This payment attempt did not complete. You can go back to My Family and try again.</p>
            @else
                <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-slate-100 text-3xl">💳</div>
                <h1 class="mt-4 text-2xl font-extrabold tracking-tight text-slate-900">No payment yet</h1>
                <p class="mt-1 text-sm text-slate-500">No payment attempt has been started for this booking yet.</p>
            @endif

            <div class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4">
                <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Booking reference</p>
                <p class="mt-1 text-xl font-extrabold tracking-wide text-slate-900">{{ $enrollment->booking_reference }}</p>
                <p class="mt-1 text-xs font-semibold text-slate-400">{{ $enrollment->offering?->program?->name }} · {{ $enrollment->offering?->scheduleLabel() }}</p>
            </div>

            @if ($payment)
                <dl class="mt-5 space-y-2.5 text-left text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-500">Amount</dt>
                        <dd class="font-extrabold text-slate-900">RM{{ number_format($payment->amount_minor / 100, 2) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-500">Gateway</dt>
                        <dd class="font-bold text-slate-900">{{ strtoupper($payment->gateway) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-500">Transaction</dt>
                        <dd class="font-bold text-slate-900">{{ $payment->transaction_id ?: '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-500">Status</dt>
                        <dd>
                            <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ match($payment->status->value) { 'paid' => 'bg-emerald-50 text-emerald-700', 'pending' => 'bg-amber-50 text-amber-700', 'failed', 'cancelled', 'expired' => 'bg-red-50 text-red-700', default => 'bg-slate-100 text-slate-600' } }}">
                                {{ ucfirst($payment->status->value) }}
                            </span>
                        </dd>
                    </div>
                </dl>
            @endif

            <a href="{{ route('family.index') }}" class="fa-btn-primary mt-6 w-full py-3">Go to My Family</a>
        </section>
    </div>
</x-layouts.public>
