<x-reports.print-layout title="Program Progress" subtitle="Average assessment scores by skill">
    @forelse($data['by_program'] as $program => $row)
        <h2>{{ $program }} · overall {{ $row['overall_average'] }} / 5 <span class="muted">({{ $row['total_scores'] }} scores)</span></h2>
        <table>
            <thead>
                <tr>
                    <th>Skill</th>
                    <th class="num">Times scored</th>
                    <th class="num">Average</th>
                </tr>
            </thead>
            <tbody>
                @foreach($row['skills'] as $skill)
                    <tr>
                        <td>{{ $skill['skill'] }}</td>
                        <td class="num">{{ $skill['count'] }}</td>
                        <td class="num">{{ $skill['average'] }} / 5</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p class="muted">No assessment scores recorded yet.</p>
    @endforelse
</x-reports.print-layout>
