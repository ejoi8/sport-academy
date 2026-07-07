@php($rm = fn ($sen) => 'RM '.number_format($sen / 100, 2))
<x-reports.print-layout
    title="Revenue &amp; Outstanding"
    :subtitle="\Illuminate\Support\Carbon::parse($data['period'].'-01')->format('F Y')"
>
    <div class="cards">
        <div class="card"><div class="n">{{ $rm($data['billed_sen']) }}</div><div class="l">Billed</div></div>
        <div class="card good"><div class="n">{{ $rm($data['collected_sen']) }}</div><div class="l">Collected</div></div>
        <div class="card warn"><div class="n">{{ $rm($data['outstanding_sen']) }}</div><div class="l">Outstanding</div></div>
    </div>

    <p class="muted" style="margin-top:1rem; font-size:.85rem;">
        {{ $data['enrollment_count'] }} enrolments · {{ $data['new_count'] }} new · {{ $data['renewing_count'] }} renewing
    </p>

    <h2>By program</h2>
    <table>
        <thead>
            <tr>
                <th>Program</th>
                <th class="num">Billed</th>
                <th class="num">Collected</th>
                <th class="num">Outstanding</th>
                <th class="num">Active / Pending / Overdue</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['by_program'] as $program => $row)
                <tr>
                    <td>{{ $program }}</td>
                    <td class="num">{{ $rm($row['billed_sen']) }}</td>
                    <td class="num">{{ $rm($row['collected_sen']) }}</td>
                    <td class="num">{{ $rm($row['outstanding_sen']) }}</td>
                    <td class="num">{{ $row['active'] }} / {{ $row['pending'] }} / {{ $row['overdue'] }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No enrolments billed for this month.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td class="num">{{ $rm($data['billed_sen']) }}</td>
                <td class="num">{{ $rm($data['collected_sen']) }}</td>
                <td class="num">{{ $rm($data['outstanding_sen']) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</x-reports.print-layout>
