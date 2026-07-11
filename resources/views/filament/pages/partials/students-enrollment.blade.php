{{-- Per-enrolment, session-by-session report: a credit/attendance headline, the skill averages
     earned under this enrolment, and every session it delivered (newest first) with scores +
     notes. Read-only; the app bar's Back returns to the student profile. --}}
@php($statusBadge = ['present' => 'pay-active', 'late' => 'carry', 'absent' => 'pay-overdue', 'excused' => 'off'])
@php($st = $enrollment->status->value)
@php($payBadge = ['active' => 'pay-active', 'pending' => 'pay-pending', 'overdue' => 'pay-overdue'])

<div class="rt-panel">
    <div class="rt-stats">
        <div class="rt-stat good"><div class="v">{{ $report['attended'] }}</div><div class="k">Attended</div></div>
        <div class="rt-stat {{ $report['credits_used'] > $report['credits_total'] ? 'bad' : '' }}"><div class="v">{{ $report['credits_used'] }}/{{ $report['credits_total'] }}</div><div class="k">Credits used</div></div>
        <div class="rt-stat"><div class="v">{{ count($report['skills']) }}</div><div class="k">Skills scored</div></div>
    </div>

    {{-- enrolment status line --}}
    <div class="rt-card" style="display:flex; align-items:center; justify-content:space-between; gap:.6rem;">
        <span class="rt-muted">Status</span>
        <span class="rt-badge {{ $payBadge[$st] ?? 'off' }}">{{ $st === 'active' ? 'paid' : $st }}</span>
    </div>

    {{-- skill averages earned under this enrolment --}}
    <div class="rt-section">
        <div class="rt-rosterhead"><span class="t">Skill averages</span></div>
        <div class="rt-card pad">
            @if(! empty($report['skills']))
                <div class="rt-meter">
                    @foreach($report['skills'] as $row)
                        @php($avg = (float) $row['average'])
                        @php($lvl = $avg >= 3.5 ? 'hi' : ($avg >= 2.5 ? 'mid' : 'lo'))
                        <div class="m">
                            <div class="head">
                                <span class="nm">{{ $row['skill'] }}</span>
                                <span class="val">{{ number_format($avg, 1) }} <span class="rt-muted">/5 · {{ $row['count'] }}×</span></span>
                            </div>
                            <div class="track"><div class="fill {{ $lvl }}" style="width:{{ max(4, round($avg / 5 * 100)) }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rt-muted" style="padding:.4rem .1rem">No skill scores recorded under this enrolment yet.</div>
            @endif
        </div>
    </div>

    {{-- session by session --}}
    <div class="rt-section">
        <div class="rt-rosterhead"><span class="t">Sessions · {{ $report['total_sessions'] }}</span></div>
        <div class="rt-players">
            @forelse($report['sessions'] as $s)
                @include('filament.pages.partials.students-session-row', ['s' => $s])
            @empty
                <div class="rt-callout" style="padding:1.1rem 1rem">No sessions recorded under this enrolment yet.</div>
            @endforelse
        </div>
    </div>
</div>
