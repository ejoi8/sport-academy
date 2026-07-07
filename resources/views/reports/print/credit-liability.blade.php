@php($rm = fn ($sen) => 'RM '.number_format($sen / 100, 2))
<x-reports.print-layout title="Credit Liability" subtitle="Prepaid sessions not yet delivered">
    <div class="cards">
        <div class="card warn"><div class="n">{{ $rm($data['total_value_sen']) }}</div><div class="l">Owed (value)</div></div>
        <div class="card"><div class="n">{{ $data['total_remaining_credits'] }}</div><div class="l">Sessions owed</div></div>
        <div class="card"><div class="n">{{ $data['over_delivered_count'] }}</div><div class="l">Over-delivered</div></div>
    </div>

    <h2>By program</h2>
    <table>
        <thead>
            <tr>
                <th>Program</th>
                <th class="num">Sessions owed</th>
                <th class="num">Value</th>
                <th class="num">Enrolments</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['by_program'] as $program => $row)
                <tr>
                    <td>{{ $program }}</td>
                    <td class="num">{{ $row['remaining_credits'] }}</td>
                    <td class="num">{{ $rm($row['value_sen']) }}</td>
                    <td class="num">{{ $row['enrollments'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No outstanding credits.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td class="num">{{ $data['total_remaining_credits'] }}</td>
                <td class="num">{{ $rm($data['total_value_sen']) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <p class="muted" style="margin-top:1rem; font-size:.8rem;">
        Each remaining session is valued at that enrolment's price ÷ sessions. Expired credits are excluded.
    </p>
</x-reports.print-layout>
