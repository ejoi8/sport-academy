<x-filament::page>
    @php($data = $this->getData())
    @php($rm = fn ($sen) => 'RM '.number_format($sen / 100, 2))

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-gray-900">
            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $rm($data['total_value_sen']) }}</div>
            <div class="mt-1 text-xs uppercase tracking-wide text-gray-500">Owed (value)</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-gray-900">
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $data['total_remaining_credits'] }}</div>
            <div class="mt-1 text-xs uppercase tracking-wide text-gray-500">Sessions owed</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-gray-900">
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $data['over_delivered_count'] }}</div>
            <div class="mt-1 text-xs uppercase tracking-wide text-gray-500">Over-delivered</div>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5">
                <tr>
                    <th class="p-3">Program</th>
                    <th class="p-3 text-right">Sessions owed</th>
                    <th class="p-3 text-right">Value</th>
                    <th class="p-3 text-right">Enrolments</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse($data['by_program'] as $program => $row)
                    <tr>
                        <td class="p-3 font-medium">{{ $program }}</td>
                        <td class="p-3 text-right tabular-nums">{{ $row['remaining_credits'] }}</td>
                        <td class="p-3 text-right tabular-nums">{{ $rm($row['value_sen']) }}</td>
                        <td class="p-3 text-right tabular-nums">{{ $row['enrollments'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-4 text-center text-gray-500">No outstanding credits.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-500">
        Each remaining session is valued at that enrolment's price ÷ sessions. Expired credits are excluded.
    </p>
</x-filament::page>
