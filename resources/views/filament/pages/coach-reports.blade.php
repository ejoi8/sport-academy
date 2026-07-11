<x-filament::page>
    @php($attendance = $this->attendance)
    @php($progress = $this->progress)
    @php($trend = $this->trend)
    @php($overall = $this->overallAverage)
    @php($maxTrend = 5)

    {{-- Reached from Home's "See full report" — a drill-down, so no bottom tabs; Back returns Home. --}}
    <x-coach-shell active="home" :tabs="false">
        <div class="rt-bar">
            <a href="{{ \App\Filament\Pages\CoachHome::getUrl() }}" wire:navigate class="rt-iconbtn" title="Back to home" aria-label="Back">
                <svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg>
            </a>
            <div style="flex:1; min-width:0;">
                <div class="rt-crumb">{{ $this->periodLabel() }}</div>
                <h1 style="font-size:1.05rem;">Full report</h1>
            </div>
        </div>

        {{-- reporting window picker — drives every section below --}}
        <div class="rt-card pad" style="display:flex; flex-direction:column; gap:.5rem;">
            <span class="rt-fieldlabel">Report period</span>
            <select wire:model.live="range">
                @foreach($this->rangeOptions() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
            @if($range === 'custom')
                <div class="rt-row2">
                    <input type="date" wire:model.live="from" aria-label="From">
                    <input type="date" wire:model.live="to" aria-label="To">
                </div>
            @endif
        </div>

        {{-- the window at a glance --}}
        <div class="rt-stats">
            <div class="rt-stat"><div class="v">{{ $attendance['sessions_delivered'] }}</div><div class="k">Sessions</div></div>
            <div class="rt-stat good"><div class="v">{{ $attendance['total_marked'] > 0 ? $attendance['attendance_rate'].'%' : '—' }}</div><div class="k">Attendance</div></div>
            <div class="rt-stat"><div class="v">{{ $overall !== null ? number_format($overall, 1) : '—' }}</div><div class="k">Avg score</div></div>
        </div>

        {{-- score trend across the window (monthly or yearly buckets) --}}
        <div class="rt-section">
            <div class="rt-rosterhead"><span class="t">Score trend · {{ $this->periodLabel() }}</span></div>
            <div class="rt-card pad">
                <div class="rt-trend">
                    @foreach($trend as $t)
                        <div class="bar">
                            <span class="val">{{ $t['avg'] > 0 ? number_format($t['avg'], 1) : '·' }}</span>
                            <div class="col"><div class="fill" style="height:{{ $t['avg'] > 0 ? round($t['avg'] / $maxTrend * 100) : 2 }}%"></div></div>
                            <span class="lbl">{{ $t['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- attendance breakdown this month --}}
        <div class="rt-section">
            <div class="rt-rosterhead"><span class="t">Attendance · {{ $this->periodLabel() }}</span></div>
            <div class="rt-card">
                <div class="rt-defrow"><span class="k">Present</span><span class="v">{{ $attendance['present'] }}</span></div>
                <div class="rt-defrow"><span class="k">Late</span><span class="v">{{ $attendance['late'] }}</span></div>
                <div class="rt-defrow"><span class="k">Absent</span><span class="v">{{ $attendance['absent'] }}</span></div>
                <div class="rt-defrow"><span class="k">Excused</span><span class="v">{{ $attendance['excused'] }}</span></div>
            </div>
        </div>

        {{-- this month by programme --}}
        <div class="rt-section">
            <div class="rt-rosterhead"><span class="t">By programme · {{ $this->periodLabel() }}</span></div>
            <div class="rt-players">
                @forelse($attendance['by_program'] as $name => $row)
                    <div class="rt-prow">
                        <span style="flex:1; min-width:0;">
                            <span class="rt-pname">{{ $name }}</span>
                            <span class="rt-psub">{{ $row['sessions'] }} sessions · {{ $row['attendances'] }} marked</span>
                        </span>
                        <span class="rt-badge {{ $row['rate'] >= 80 ? 'pay-active' : ($row['rate'] >= 60 ? 'carry' : 'pay-overdue') }}">{{ $row['attendances'] > 0 ? $row['rate'].'%' : '—' }}</span>
                    </div>
                @empty
                    <div class="rt-callout" style="padding:1.1rem 1rem">No sessions delivered in this period.</div>
                @endforelse
            </div>
        </div>

        {{-- skill progress for the window, per programme --}}
        <div class="rt-section">
            <div class="rt-rosterhead"><span class="t">Skill progress · {{ $this->periodLabel() }}</span></div>
            @forelse($progress as $name => $data)
                <div class="rt-card">
                    <div class="rt-defrow" style="border-bottom:1px solid var(--b)">
                        <span class="k" style="color:var(--ink); font-weight:800">{{ $name }}</span>
                        <span class="v">{{ number_format($data['overall_average'], 1) }} <span class="rt-muted">avg · {{ $data['total_scores'] }} scores</span></span>
                    </div>
                    @foreach($data['skills'] as $skill)
                        <div class="rt-defrow"><span class="k">{{ $skill['skill'] }}</span><span class="v">{{ number_format($skill['average'], 1) }} <span class="rt-muted">· {{ $skill['count'] }}×</span></span></div>
                    @endforeach
                </div>
            @empty
                <div class="rt-callout">No assessments recorded yet — scores you log in Run Training show up here.</div>
            @endforelse
        </div>
    </x-coach-shell>
</x-filament::page>
