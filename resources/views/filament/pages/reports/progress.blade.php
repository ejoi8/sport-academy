<x-filament::page>
    @php($data = $this->getData())

    @forelse($data['by_program'] as $program => $row)
        <div class="space-y-3">
            <div class="flex items-baseline justify-between">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $program }}</h3>
                <span class="text-sm text-gray-500">
                    Overall <span class="font-semibold text-green-600 dark:text-green-400">{{ $row['overall_average'] }}</span> / 5
                    · {{ $row['total_scores'] }} scores
                </span>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5">
                        <tr>
                            <th class="p-3">Skill</th>
                            <th class="p-3 text-right">Times scored</th>
                            <th class="p-3 text-right">Average</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach($row['skills'] as $skill)
                            <tr>
                                <td class="p-3 font-medium">{{ $skill['skill'] }}</td>
                                <td class="p-3 text-right tabular-nums">{{ $skill['count'] }}</td>
                                <td class="p-3 text-right tabular-nums">{{ $skill['average'] }} / 5</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500">No assessment scores recorded yet.</p>
    @endforelse
</x-filament::page>
