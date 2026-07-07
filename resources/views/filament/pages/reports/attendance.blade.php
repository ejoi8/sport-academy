<x-filament::page>
    @php($data = $this->getData())

    <div class="flex flex-wrap items-center gap-4">
        <label class="flex items-center gap-2">
            <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Month</span>
            <select wire:model.live="period"
                class="rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5">
                @foreach($this->periodOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>

        @if($this->isAdmin())
            <label class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Coach</span>
                <select wire:model.live="coachFilter"
                    class="rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5">
                    <option value="">All coaches</option>
                    @foreach($this->coachOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-gray-900">
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $data['sessions_delivered'] }}</div>
            <div class="mt-1 text-xs uppercase tracking-wide text-gray-500">Sessions delivered</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-gray-900">
            <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $data['attendance_rate'] }}%</div>
            <div class="mt-1 text-xs uppercase tracking-wide text-gray-500">Attendance rate</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-gray-900">
            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $data['no_show_rate'] }}%</div>
            <div class="mt-1 text-xs uppercase tracking-wide text-gray-500">No-show rate</div>
        </div>
    </div>

    <p class="text-sm text-gray-500">
        {{ $data['present'] }} present · {{ $data['late'] }} late · {{ $data['absent'] }} absent ·
        {{ $data['excused'] }} excused ({{ $data['total_marked'] }} marked)
    </p>

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5">
                <tr>
                    <th class="p-3">Program</th>
                    <th class="p-3 text-right">Sessions</th>
                    <th class="p-3 text-right">Attendances</th>
                    <th class="p-3 text-right">Attended</th>
                    <th class="p-3 text-right">Rate</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse($data['by_program'] as $program => $row)
                    <tr>
                        <td class="p-3 font-medium">{{ $program }}</td>
                        <td class="p-3 text-right tabular-nums">{{ $row['sessions'] }}</td>
                        <td class="p-3 text-right tabular-nums">{{ $row['attendances'] }}</td>
                        <td class="p-3 text-right tabular-nums">{{ $row['attended'] }}</td>
                        <td class="p-3 text-right tabular-nums">{{ $row['rate'] }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-4 text-center text-gray-500">No sessions delivered this month.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament::page>
