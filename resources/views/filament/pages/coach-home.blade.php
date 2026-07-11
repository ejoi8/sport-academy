<x-filament::page>
    @php($stats = $this->stats)
    @php($timeslots = $this->timeslots)
    @php($trend = $this->trend)

    <x-coach-shell active="home" :tabs="true">
        <div class="rt-bar">
            <div style="flex:1; min-width:0;">
                <div class="rt-crumb">{{ now()->format('l, j F') }}</div>
                <h1 style="font-size:1.15rem;">Hi, {{ $this->coachName }}</h1>
            </div>
            {{-- Focus mode hides the panel nav; this leaves the coach console for the full panel. --}}
            <a href="{{ \Filament\Facades\Filament::getUrl() }}" wire:navigate class="rt-iconbtn" title="Exit to dashboard" aria-label="Exit">
                <svg viewBox="0 0 24 24"><path d="M14 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
            </a>
        </div>

        <div class="rt-stats">
            <div class="rt-stat good"><div class="v">{{ $stats['sessions_week'] }}</div><div class="k">Sessions this week</div></div>
            <div class="rt-stat {{ $stats['to_assess'] > 0 ? 'warn' : '' }}"><div class="v">{{ $stats['to_assess'] }}</div><div class="k">To assess</div></div>
            <div class="rt-stat good"><div class="v">{{ $stats['attendance'] }}</div><div class="k">Attendance</div></div>
        </div>

        <div class="rt-section">
            <div class="rt-rosterhead"><span class="t">My classes · {{ now()->format('F') }}</span></div>
            <div class="rt-list">
                @forelse($timeslots as $t)
                    <a href="{{ $t['url'] }}" wire:navigate class="rt-scard">
                        <span class="rt-pav" aria-hidden="true">{{ mb_substr($t['program'], 0, 1) }}</span>
                        <span class="rt-scard-body">
                            <span class="rt-scard-title" style="display:block;">{{ $t['program'] }}</span>
                            <span class="rt-scard-meta">{{ $t['schedule'] }} · {{ $t['enrolled'] }}/{{ $t['capacity'] }} enrolled</span>
                        </span>
                        @if($t['today'])<span class="rt-badge extra">Today</span>@endif
                    </a>
                @empty
                    <div class="rt-callout">
                        <span class="ball" aria-hidden="true">⚽</span>
                        No classes assigned to you this month.
                        <div style="margin-top:.5rem"><a href="{{ \App\Filament\Pages\RunTraining::getUrl() }}" wire:navigate class="rt-linkbtn">Go to Run Training →</a></div>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- compact reporting summary — the score trend, with the full breakdown one tap away --}}
        <div class="rt-section">
            <div class="rt-rosterhead"><span class="t">Average score · last 6 months</span></div>
            <div class="rt-card pad">
                <div class="rt-trend">
                    @foreach($trend as $t)
                        <div class="bar">
                            <span class="val">{{ $t['avg'] > 0 ? number_format($t['avg'], 1) : '·' }}</span>
                            <div class="col"><div class="fill" style="height:{{ $t['avg'] > 0 ? round($t['avg'] / 5 * 100) : 2 }}%"></div></div>
                            <span class="lbl">{{ $t['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <a href="{{ \App\Filament\Pages\CoachReports::getUrl() }}" wire:navigate class="rt-scard">
            <span class="rt-pav" aria-hidden="true">
                <svg viewBox="0 0 24 24" style="width:1.15rem;height:1.15rem;stroke:currentColor;fill:none;stroke-width:2.2"><path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/></svg>
            </span>
            <span class="rt-scard-body">
                <span class="rt-scard-title" style="display:block;">See full report</span>
                <span class="rt-scard-meta">Attendance, programmes &amp; skill progress</span>
            </span>
            <span aria-hidden="true" style="color:var(--mut); font-size:1.1rem; flex:none;">›</span>
        </a>
    </x-coach-shell>
</x-filament::page>
