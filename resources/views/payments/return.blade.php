<x-layouts.public title="Payment status">
    <div class="space-y-6">
        <section class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-zinc-500">Payment status</p>
            <h1 class="mt-2 text-3xl font-semibold text-zinc-950">{{ $enrollment->booking_reference }}</h1>
            <p class="mt-2 text-sm text-zinc-600">
                {{ $enrollment->offering?->program?->name }} · {{ $enrollment->offering?->scheduleLabel() }}
            </p>
        </section>

        <section class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
            @if ($payment)
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-sm text-zinc-500">Gateway</p>
                        <p class="mt-2 text-2xl font-semibold text-zinc-950">{{ strtoupper($payment->gateway) }}</p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-sm font-medium {{ match($payment->status->value) { 'paid' => 'bg-emerald-100 text-emerald-800', 'pending' => 'bg-amber-100 text-amber-800', 'failed', 'cancelled', 'expired' => 'bg-red-100 text-red-700', default => 'bg-zinc-200 text-zinc-700' } }}">
                        {{ ucfirst($payment->status->value) }}
                    </span>
                </div>

                <dl class="mt-6 space-y-3 text-sm text-zinc-700">
                    <div class="flex items-center justify-between gap-4">
                        <dt>Amount</dt>
                        <dd class="font-medium text-zinc-950">RM{{ number_format($payment->amount_minor / 100, 2) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt>Transaction</dt>
                        <dd class="font-medium text-zinc-950">{{ $payment->transaction_id ?: '—' }}</dd>
                    </div>
                </dl>

                @if ($payment->status->isPaid())
                    <p class="mt-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                        Payment received. Your booking is being confirmed.
                    </p>
                @elseif ($payment->status->isPending())
                    <p class="mt-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        We are still waiting for the gateway confirmation. Refresh this page in a moment if needed.
                    </p>
                    <div class="mt-3">
                        <a href="{{ url()->current() }}" class="inline-flex rounded-md border border-amber-300 bg-white px-4 py-2 text-sm font-medium text-amber-900 hover:bg-amber-50">
                            Check payment status again
                        </a>
                    </div>
                @else
                    <p class="mt-6 rounded-md border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700">
                        This payment attempt did not complete. You can go back to My Family and try again.
                    </p>
                @endif
            @else
                <p class="text-sm text-zinc-600">No payment attempt has been started for this booking yet.</p>
            @endif

            <div class="mt-6">
                <a href="{{ route('family.index') }}" class="inline-flex rounded-md bg-zinc-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800">
                    Go to My Family
                </a>
            </div>
        </section>
    </div>
</x-layouts.public>
