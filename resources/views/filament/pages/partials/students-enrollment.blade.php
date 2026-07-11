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
        <div class="rt-card">
            @forelse($report['skills'] as $row)
                <div class="rt-defrow">
                    <span class="k">{{ $row['skill'] }}</span>
                    <span class="v">{{ number_format($row['average'], 1) }} <span class="rt-muted">· {{ $row['count'] }}×</span></span>
                </div>
            @empty
                <div class="rt-muted" style="padding:.4rem .1rem">No skill scores recorded under this enrolment yet.</div>
            @endforelse
        </div>
    </div>

    {{-- session by session --}}
    <div class="rt-section">
        <div class="rt-rosterhead"><span class="t">Sessions · {{ $report['total_sessions'] }}</span></div>
        <div class="rt-players">
            @forelse($report['sessions'] as $s)
                <div class="rt-histrow">
                    <div class="top">
                        <span class="dt">{{ $s['date']?->format('j M Y') ?? '—' }}</span>
                        <span class="rt-badge {{ $statusBadge[$s['status']] ?? 'off' }}">{{ ucfirst($s['status']) }}</span>
                    </div>
                    @if($s['coach'])<span class="sl">Coach {{ $s['coach'] }}</span>@endif
                    @if(! empty($s['scores']))
                        <span class="sl">{{ collect($s['scores'])->map(fn ($x) => $x['skill'].' '.$x['score'])->implode(' · ') }}</span>
                    @endif
                    @if($s['note'])<span class="sl" style="color:var(--sub)">“{{ $s['note'] }}”</span>@endif
                </div>
            @empty
                <div class="rt-callout" style="padding:1.1rem 1rem">No sessions recorded under this enrolment yet.</div>
            @endforelse
        </div>
    </div>
</div>
