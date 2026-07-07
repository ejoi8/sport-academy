<x-filament::page>
    @php($data = $this->getData())
    @php($rm = fn ($sen) => 'RM '.number_format($sen / 100, 2))

    <div class="flex flex-wrap items-center gap-3">
        <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Month</span>
        <select wire:model.live="period"
            class="rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5">
            @foreach($this->periodOptions() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach([
            ['Billed', $data['billed_sen'], 'text-gray-900 dark:text-white'],
            ['Collected', $data['collected_sen'], 'text-green-600 dark:text-green-400'],
            ['Outstanding', $data['outstanding_sen'], 'text-amber-600 dark:text-amber-400'],
        ] as [$label, $sen, $color])
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-gray-900">
                <div class="text-2xl font-bold {{ $color }}">{{ $rm($sen) }}</div>
                <div class="mt-1 text-xs uppercase tracking-wide text-gray-500">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    <p class="text-sm text-gray-500">
        {{ $data['enrollment_count'] }} enrolments · {{ $data['new_count'] }} new · {{ $data['renewing_count'] }} renewing
    </p>

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5">
                <tr>
                    <th class="p-3">Program</th>
                    <th class="p-3 text-right">Billed</th>
                    <th class="p-3 text-right">Collected</th>
                    <th class="p-3 text-right">Outstanding</th>
                    <th class="p-3 text-right">A / P / O</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse($data['by_program'] as $program => $row)
                    <tr>
                        <td class="p-3 font-medium">{{ $program }}</td>
                        <td class="p-3 text-right tabular-nums">{{ $rm($row['billed_sen']) }}</td>
                        <td class="p-3 text-right tabular-nums">{{ $rm($row['collected_sen']) }}</td>
                        <td class="p-3 text-right tabular-nums">{{ $rm($row['outstanding_sen']) }}</td>
                        <td class="p-3 text-right tabular-nums">{{ $row['active'] }} / {{ $row['pending'] }} / {{ $row['overdue'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-4 text-center text-gray-500">No enrolments billed for this month.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament::page>
