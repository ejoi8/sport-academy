<x-reports.print-layout
    title="Attendance &amp; Delivery"
    :subtitle="\Illuminate\Support\Carbon::parse($data['period'].'-01')->format('F Y')"
>
    <div class="cards">
        <div class="card"><div class="n">{{ $data['sessions_delivered'] }}</div><div class="l">Sessions delivered</div></div>
        <div class="card good"><div class="n">{{ $data['attendance_rate'] }}%</div><div class="l">Attendance rate</div></div>
        <div class="card warn"><div class="n">{{ $data['no_show_rate'] }}%</div><div class="l">No-show rate</div></div>
    </div>

    <p class="muted" style="margin-top:1rem; font-size:.85rem;">
        {{ $data['present'] }} present · {{ $data['late'] }} late · {{ $data['absent'] }} absent · {{ $data['excused'] }} excused
        ({{ $data['total_marked'] }} marked)
    </p>

    <h2>By program</h2>
    <table>
        <thead>
            <tr>
                <th>Program</th>
                <th class="num">Sessions</th>
                <th class="num">Attendances</th>
                <th class="num">Attended</th>
                <th class="num">Rate</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['by_program'] as $program => $row)
                <tr>
                    <td>{{ $program }}</td>
                    <td class="num">{{ $row['sessions'] }}</td>
                    <td class="num">{{ $row['attendances'] }}</td>
                    <td class="num">{{ $row['attended'] }}</td>
                    <td class="num">{{ $row['rate'] }}%</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No sessions delivered this month.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td class="num">{{ $data['sessions_delivered'] }}</td>
                <td class="num">{{ $data['total_marked'] }}</td>
                <td class="num">{{ $data['attended'] }}</td>
                <td class="num">{{ $data['attendance_rate'] }}%</td>
            </tr>
        </tfoot>
    </table>

    <p class="muted" style="margin-top:1rem; font-size:.8rem;">
        Attended = present + late. Rate is over all marked attendances.
    </p>
</x-reports.print-layout>
