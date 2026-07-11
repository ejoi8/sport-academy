<x-filament::page>
    @php($stats = $this->stats)
    @php($timeslots = $this->timeslots)

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
    </x-coach-shell>
</x-filament::page>
