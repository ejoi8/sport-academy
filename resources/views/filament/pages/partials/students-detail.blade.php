{{-- Student profile: contact, credit/attendance stats, enrolments, skill averages and recent
     session history. Read-only reuse of the Student model's own summaries; edits happen in the
     form sheet, active/delete in the sticky action bar. Built from the shared .rt-section / .rt-card
     blocks so its rhythm matches Run Training exactly. --}}
@php($credit = $profile['credit'])
@php($att = $profile['attendance'])
@php($statusBadge = ['present' => 'pay-active', 'late' => 'carry', 'absent' => 'pay-overdue', 'excused' => 'off'])
@php($payBadge = ['active' => 'pay-active', 'pending' => 'pay-pending', 'overdue' => 'pay-overdue'])
@php($deletable = $student->deletionBlockedReason() === null)

<div class="rt-panel">
    {{-- credit / attendance at a glance --}}
    <div class="rt-stats">
        <div class="rt-stat good"><div class="v">{{ $profile['attended'] }}</div><div class="k">Attended</div></div>
        <div class="rt-stat {{ $credit['owed'] > 0 ? 'warn' : '' }}"><div class="v">{{ $credit['owed'] }}</div><div class="k">Credits owed</div></div>
        <div class="rt-stat {{ $profile['carried'] > 0 ? 'good' : '' }}"><div class="v">{{ $profile['carried'] }}</div><div class="k">Carried</div></div>
    </div>

    {{-- contact --}}
    <div class="rt-section">
        <div class="rt-rosterhead"><span class="t">Details</span></div>
        <div class="rt-card">
            <div class="rt-defrow"><span class="k">Age</span><span class="v">{{ $student->age !== null ? $student->age.' yrs' : '—' }}</span></div>
            <div class="rt-defrow"><span class="k">Date of birth</span><span class="v">{{ $student->dob?->format('j M Y') ?? '—' }}</span></div>
            <div class="rt-defrow"><span class="k">Gender</span><span class="v">{{ $student->gender?->getLabel() ?? '—' }}</span></div>
            <div class="rt-defrow"><span class="k">IC number</span><span class="v">{{ $student->ic_number ?? '—' }}</span></div>
            <div class="rt-defrow"><span class="k">Guardian</span><span class="v">{{ $student->guardian_name ?? '—' }}</span></div>
            <div class="rt-defrow"><span class="k">Guardian phone</span><span class="v">{{ $student->guardian_phone ?? '—' }}</span></div>
        </div>
    </div>

    {{-- enrolments --}}
    <div class="rt-section">
        <div class="rt-rosterhead"><span class="t">Enrolments · {{ $profile['enrollments']->count() }}</span></div>
        <div class="rt-players">
            @forelse($profile['enrollments'] as $enrollment)
                @php($st = $enrollment->status->value)
                <div class="rt-prow">
                    <button type="button" class="rt-prow-main" wire:click="openEnrollment({{ $enrollment->id }})" title="View session report">
                        <span style="flex:1; min-width:0;">
                            <span class="rt-pname">{{ $enrollment->offering?->program?->name ?? 'Programme' }}</span>
                            <span class="rt-psub">{{ $enrollment->offering?->period ?? '—' }} · {{ $enrollment->creditsUsed() }}/{{ $enrollment->sessions_included }} sessions</span>
                        </span>
                    </button>
                    <span class="rt-badge {{ $payBadge[$st] ?? 'off' }}">{{ $st === 'active' ? 'paid' : $st }}</span>
                    <span aria-hidden="true" style="color:var(--mut); font-size:1.1rem; flex:none;">›</span>
                </div>
            @empty
                <div class="rt-callout" style="padding:1.1rem 1rem">Not enrolled in any programme.</div>
            @endforelse
        </div>
    </div>

    {{-- attendance breakdown --}}
    <div class="rt-section">
        <div class="rt-rosterhead"><span class="t">Attendance</span></div>
        <div class="rt-card">
            <div class="rt-defrow"><span class="k">Present</span><span class="v">{{ $att['present'] ?? 0 }}</span></div>
            <div class="rt-defrow"><span class="k">Late</span><span class="v">{{ $att['late'] ?? 0 }}</span></div>
            <div class="rt-defrow"><span class="k">Absent</span><span class="v">{{ $att['absent'] ?? 0 }}</span></div>
            <div class="rt-defrow"><span class="k">Excused</span><span class="v">{{ $att['excused'] ?? 0 }}</span></div>
        </div>
    </div>

    {{-- skill averages --}}
    <div class="rt-section">
        <div class="rt-rosterhead"><span class="t">Skill averages</span></div>
        <div class="rt-card pad">
            @if($profile['assessment']->isNotEmpty())
                <div class="rt-meter">
                    @foreach($profile['assessment'] as $row)
                        @php($avg = (float) $row['average'])
                        @php($lvl = $avg >= 3.5 ? 'hi' : ($avg >= 2.5 ? 'mid' : 'lo'))
                        <div class="m">
                            <div class="head">
                                <span class="nm">{{ $row['skill'] }}</span>
                                <span class="val">{{ number_format($avg, 1) }} <span class="rt-muted">/5 · latest {{ $row['latest'] ?? '—' }}</span></span>
                            </div>
                            <div class="track"><div class="fill {{ $lvl }}" style="width:{{ max(4, round($avg / 5 * 100)) }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rt-muted" style="padding:.4rem .1rem">No skill scores recorded yet.</div>
            @endif
        </div>
    </div>

    {{-- recent sessions --}}
    <div class="rt-section">
        <div class="rt-rosterhead"><span class="t">Recent sessions</span></div>
        <div class="rt-players">
            @forelse($profile['history']->take(8) as $h)
                <div class="rt-histrow">
                    <div class="top">
                        <span class="dt">{{ $h['date']?->format('j M Y') ?? '—' }}</span>
                        <span class="rt-badge {{ $statusBadge[$h['status']] ?? 'off' }}">{{ ucfirst($h['status']) }}</span>
                    </div>
                    <span class="sl">{{ $h['timeslot'] }}@if($h['coach']) · {{ $h['coach'] }}@endif</span>
                    @if(! empty($h['scores']))
                        <span class="sl">{{ collect($h['scores'])->map(fn ($s) => $s['skill'].' '.$s['score'])->implode(' · ') }}</span>
                    @endif
                    @if($h['note'])<span class="sl" style="color:var(--sub)">“{{ $h['note'] }}”</span>@endif
                </div>
            @empty
                <div class="rt-callout" style="padding:1.1rem 1rem">No sessions recorded yet.</div>
            @endforelse
        </div>
        @if($profile['history']->count() > 8)
            <div class="rt-muted" style="text-align:center">Showing the latest 8 of {{ $profile['history']->count() }} sessions.</div>
        @endif
    </div>

    {{-- notes --}}
    @if($student->notes)
        <div class="rt-section">
            <div class="rt-rosterhead"><span class="t">Notes</span></div>
            <div class="rt-card" style="color:var(--sub)">{{ $student->notes }}</div>
        </div>
    @endif
</div>

<div class="rt-actionbar">
    @if($deletable)
        <button type="button" class="rt-delete" wire:click="deleteStudent" wire:confirm="Delete {{ $student->name }} permanently? This cannot be undone.">Delete</button>
    @endif
    <button type="button" class="rt-discard" wire:click="toggleActive">{{ $student->is_active ? 'Mark inactive' : 'Mark active' }}</button>
    <button type="button" class="rt-save" wire:click="startEdit">Edit student</button>
</div>
