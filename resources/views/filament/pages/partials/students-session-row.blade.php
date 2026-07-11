{{-- One session in a history list. Collapsed: date · session-average bar · attendance status.
     Tap the chevron to expand into per-skill level bars. Shared by the profile "Recent sessions"
     and the enrolment "Sessions". Expects $s = ['date'(Carbon|null), 'status', 'coach', 'note',
     'scores' => [['skill','score'], …], and optionally 'timeslot']. --}}
@php($statusBadge = ['present' => 'pay-active', 'late' => 'carry', 'absent' => 'pay-overdue', 'excused' => 'off'])
@php($scores = collect($s['scores'] ?? []))
@php($avg = $scores->isNotEmpty() ? round((float) $scores->avg('score'), 1) : null)
@php($avgLvl = $avg === null ? '' : ($avg >= 3.5 ? 'hi' : ($avg >= 2.5 ? 'mid' : 'lo')))
@php($meta = collect([$s['timeslot'] ?? null, ! empty($s['coach']) ? 'Coach '.$s['coach'] : null])->filter()->implode(' · '))

<div class="rt-histrow" @if($avg !== null) x-data="{ open: false }" @endif>
    <div class="top">
        <span class="dt">{{ $s['date']?->format('j M Y') ?? '—' }}</span>
        <span class="hh-right">
            @if($avg !== null)
                <span class="rt-avg" title="Session average">
                    <span class="bar"><span class="fill {{ $avgLvl }}" style="width:{{ round($avg / 5 * 100) }}%"></span></span>{{ number_format($avg, 1) }}
                </span>
            @endif
            <span class="rt-badge {{ $statusBadge[$s['status']] ?? 'off' }}">{{ ucfirst($s['status']) }}</span>
            @if($avg !== null)
                <button type="button" class="rt-chev" @click="open = ! open" :class="{ 'on': open }" :aria-expanded="open" aria-label="Show skill scores">
                    <svg viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                </button>
            @endif
        </span>
    </div>

    @if($meta)<span class="sl">{{ $meta }}</span>@endif

    @if($avg !== null)
        <div class="rt-hist-det" x-show="open" x-collapse x-cloak>
            <div class="rt-meter">
                @foreach($scores as $sc)
                    @php($lvl = $sc['score'] >= 4 ? 'hi' : ($sc['score'] >= 3 ? 'mid' : 'lo'))
                    <div class="m">
                        <div class="head">
                            <span class="nm">{{ $sc['skill'] }}</span>
                            <span class="val">{{ $sc['score'] }} <span class="rt-muted">/5</span></span>
                        </div>
                        <div class="track"><div class="fill {{ $lvl }}" style="width:{{ round($sc['score'] / 5 * 100) }}%"></div></div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if(! empty($s['note']))<span class="sl" style="color:var(--sub)">“{{ $s['note'] }}”</span>@endif
</div>
