<x-filament::page>
    @php($skills = $this->skills)
    @php($suggestion = $this->suggestion)
    @php($coachOptions = $this->coachOptions)
    @php($sessions = $this->sessionsOnDate)
    @php($sel = \Illuminate\Support\Carbon::parse($date ?: today()->toDateString()))
    @php($onRecorder = $offeringId || $creatingSession)
    @php($openSession = $offeringId ? collect($sessions)->firstWhere('id', $offeringId) : null)
    @php($cycle = ['present' => 'late', 'late' => 'absent', 'absent' => 'excused', 'excused' => 'present'])
    @php($statusWord = ['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'excused' => 'Excused'])


    <x-coach-shell active="training" :tabs="! $onRecorder">

        {{-- ============================ RECORDER SCREEN ============================ --}}
        @if($onRecorder)
            <div class="rt-bar">
                <button type="button" class="rt-iconbtn" wire:click="{{ $creatingSession ? 'cancelNewSession' : 'toggleSession('.$offeringId.')' }}"
                    @disabled($dirty) title="{{ $dirty ? 'Save or discard first' : 'Back to sessions' }}" aria-label="Back">
                    <svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg>
                </button>
                <div style="flex:1; min-width:0;">
                    <div class="rt-crumb">{{ $sel->format('D, j M') }}</div>
                    <h1 style="font-size:1.05rem;">{{ $creatingSession ? 'New session' : ($openSession['program'] ?? 'Session').' · '.($openSession['time'] ?? '') }}</h1>
                </div>
                @if($dirty)
                    <span class="rt-status pending"><span class="led"></span>Unsaved</span>
                @elseif($savedSessionExists)
                    <span class="rt-status saved"><span class="led"></span>Saved</span>
                @else
                    <span class="rt-status pending"><span class="led"></span>Not recorded</span>
                @endif
            </div>

            @include('filament.pages.partials.run-training-recorder')

        {{-- ============================ DAY / LIST SCREEN ============================ --}}
        @else
            <div class="rt-bar">
                <h1>Run Training</h1>
                <label class="rt-iconbtn rt-cal" title="Jump to date">
                    <input type="date" wire:model.live="date">
                    <svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="16" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/></svg>
                </label>
                <button type="button" class="rt-textbtn" wire:click="goToday">Today</button>
                <button type="button" class="rt-iconbtn" data-tour="help" @click="help = true" aria-label="How credits work"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 114 2c-.9.6-1.5 1.2-1.5 2.2M12 17h.01"/></svg></button>
                {{-- Focus mode hides the panel nav, so this is the way back out. Points at the panel
                     home (which restores the chrome); swap for a logout route for a kiosk device. --}}
                <a href="{{ \Filament\Facades\Filament::getUrl() }}" wire:navigate class="rt-iconbtn" title="Exit to dashboard" aria-label="Exit">
                    <svg viewBox="0 0 24 24"><path d="M14 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
                </a>
            </div>

            <div class="rt-week" data-tour="date">
                <button type="button" class="rt-iconbtn" wire:click="$set('date', '{{ $sel->copy()->subDays(7)->toDateString() }}')" aria-label="Previous week"><svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg></button>
                <div class="rt-days">
                    @for($i = 0; $i < 7; $i++)
                        @php($d = $sel->copy()->startOfWeek(\Carbon\CarbonInterface::MONDAY)->addDays($i))
                        <button type="button" class="rt-day {{ $d->toDateString() === $date ? 'on' : '' }}" wire:click="$set('date', '{{ $d->toDateString() }}')">
                            <span class="wd">{{ $d->format('D') }}</span>
                            <span class="dn">{{ $d->format('j') }}</span>
                            @if($d->isToday())<span class="dot"></span>@else<span class="dot" style="background:transparent"></span>@endif
                        </button>
                    @endfor
                </div>
                <button type="button" class="rt-iconbtn" wire:click="$set('date', '{{ $sel->copy()->addDays(7)->toDateString() }}')" aria-label="Next week"><svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg></button>
            </div>

            <div class="rt-list" data-tour="sessions">
                <div class="rt-listlabel">{{ $sel->format('l, j F') }}</div>

                @forelse($sessions as $s)
                    <button type="button" class="rt-scard @if($dirty) locked @endif" wire:click="toggleSession({{ $s['id'] }})">
                        <span class="rt-daychip" aria-hidden="true"><span class="d">{{ $sel->format('D') }}</span><span class="t">{{ substr($s['time'], 0, 5) }}</span></span>
                        <span class="rt-scard-body">
                            <span class="rt-scard-title" style="display:block;">{{ $s['program'] }}</span>
                            <span class="rt-scard-meta">
                                @if($s['coach'])<span class="rt-avatar">{{ mb_substr($s['coach'], 0, 1) }}</span>{{ $s['coach'] }} · @endif
                                {{ $s['recorded'] ? $s['attended'].' attended' : $s['enrolled'].' enrolled' }}
                            </span>
                        </span>
                        @if($s['recorded'])<span class="rt-status saved"><span class="led"></span>Saved</span>
                        @else<span class="rt-status pending"><span class="led"></span>Not recorded</span>@endif
                    </button>
                @empty
                    <div class="rt-callout"><span class="ball" aria-hidden="true">⚽</span>No sessions scheduled on this day.</div>
                @endforelse

                <button type="button" class="rt-scard new @if($dirty) locked @endif" data-tour="new-session" wire:click="toggleNewSession">
                    <span class="rt-plus" aria-hidden="true">＋</span>
                    <span class="rt-scard-body">
                        <span class="rt-scard-title" style="display:block;">Create new session</span>
                        <span class="rt-scard-meta">Off-schedule or one-off clinic</span>
                    </span>
                </button>
            </div>
        @endif

        {{-- ============================ EDIT SHEET (per player) ============================ --}}
        @if($onRecorder && $expandedKey && isset($roster[$expandedKey]))
            @php($row = $roster[$expandedKey])
            @php($absent = in_array($row['status'], ['absent', 'excused'], true))
            <div class="rt-backdrop" wire:click="$set('expandedKey', null)"></div>
            <div class="rt-sheet" role="dialog" aria-modal="true" aria-label="Record {{ $row['name'] }}">
                <div class="rt-handle"></div>
                <div class="rt-sheet-head">
                    <div>
                        <div class="nm">{{ $row['name'] }}</div>
                        <div class="badges">
                            @if($row['type'] === 'enrolled')
                                @if($row['payment_status'])<span class="rt-badge pay-{{ $row['payment_status'] }}">{{ $row['payment_status'] === 'active' ? 'paid' : $row['payment_status'] }}</span>@endif
                                @if(! is_null($row['credits_total']))
                                    @php($u = (int) $row['credits_used'])@php($t = (int) $row['credits_total'])
                                    <span class="rt-badge credits {{ $u > $t ? 'over' : ($u === $t && $t > 0 ? 'full' : '') }}">{{ $u }}/{{ $t }}{{ $u > $t ? ' · +'.($u - $t).' over' : ($u === $t && $t > 0 ? ' · paid up' : '') }}</span>
                                @endif
                                @if(($row['carry_over'] ?? 0) > 0)<span class="rt-badge carry">+{{ $row['carry_over'] }} carried</span>@endif
                            @elseif($row['type'] === 'make_up')
                                <span class="rt-badge extra">make-up · no fee</span>
                            @else
                                <span class="rt-badge walkin">walk-in · RM{{ number_format(($row['fee_sen'] ?? 0) / 100, 2) }}</span>
                            @endif
                        </div>
                    </div>
                    <button type="button" class="rt-sheet-close" wire:click="$set('expandedKey', null)" aria-label="Close">✕</button>
                </div>

                <div class="rt-sheet-sec">
                    <span class="h">Attendance</span>
                    <div class="rt-att">
                        @foreach(['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused'] as $val => $label)
                            <button type="button" class="{{ $row['status'] === $val ? 'sel '.$val : '' }}" wire:click="setStatus('{{ $expandedKey }}', '{{ $val }}')">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                @if($skills->isNotEmpty())
                    <div class="rt-sheet-sec" @if($absent) style="opacity:.5" title="Scores aren't recorded for an absent player" @endif>
                        <span class="h">Skill scores {{ $absent ? '· not recorded while absent' : '(1–5)' }}</span>
                        @foreach($skills as $skill)
                            <div class="rt-skill">
                                <span class="n">{{ $skill->name }}</span>
                                <span class="rt-pills">
                                    @for($n = 1; $n <= 5; $n++)
                                        <button type="button" class="rt-pill {{ ($row['scores'][$skill->id] ?? null) === $n ? 'sel' : '' }}" wire:click="setScore('{{ $expandedKey }}', {{ $skill->id }}, {{ $n }})" @disabled($absent)>{{ $n }}</button>
                                    @endfor
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="rt-sheet-sec">
                    <span class="h">Coach</span>
                    <select wire:model="roster.{{ $expandedKey }}.coach_id">
                        <option value="">— unassigned —</option>
                        @foreach($coachOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                </div>

                <div class="rt-sheet-sec">
                    <span class="h">Note</span>
                    <textarea wire:model.live.debounce.500ms="roster.{{ $expandedKey }}.note" rows="2" placeholder="Optional note for this player"></textarea>
                </div>

                <button type="button" class="rt-sheet-done" wire:click="$set('expandedKey', null)">Done</button>
            </div>
        @endif

        {{-- ============================ ADD SHEET ============================ --}}
        @if($onRecorder && $adding)
            @include('filament.pages.partials.run-training-add', ['suggestion' => $suggestion])
        @endif

        {{-- ============================ ADD-COACH SHEET ============================ --}}
        @if($onRecorder && $addingCoach)
            @include('filament.pages.partials.run-training-coach')
        @endif

        {{-- ============================ ONBOARDING (guided spotlight tour) ============================ --}}
        {{-- Steps adapt to the screen: the list gets the day/session steps, the recorder gets the
             mark/score/save steps. Targets are the real elements (data-tour="…"). --}}
        @php($tourSteps = $onRecorder ? [
            ['target' => 'coach', 'title' => 'Coach for all', 'text' => 'Pick who’s coaching and Apply it across the roster — or set a coach per player inside their card.'],
            ['target' => 'roster', 'title' => 'Mark & score', 'text' => 'Tap the status pill to set attendance (Present → Late → Absent → Excused). Tap a player’s row to give skill scores (1–5) and a note.'],
            ['target' => 'add', 'title' => 'Walk-ins & make-ups', 'text' => 'Add drop-ins and make-ups here — credits, fees and badges are worked out for you.'],
            ['target' => 'save', 'title' => 'Save the session', 'text' => 'Hit Save when you’re done. Over-limit players are flagged, never blocked — and you can re-open a saved session to edit it.'],
        ] : [
            ['target' => 'date', 'title' => 'Pick the day', 'text' => 'Tap a day on the strip, or the calendar to jump to any date. Today is already selected.'],
            ['target' => 'sessions', 'title' => 'Your sessions', 'text' => 'Every class that day shows here. Tap a card to open its roster — go ahead, tap one and the tour follows you in.'],
            ['target' => 'new-session', 'title' => 'Off-schedule session', 'text' => 'Running a one-off clinic or an extra class? Create it here.'],
            ['target' => 'help', 'title' => 'Credits & badges', 'text' => 'Make-ups, walk-ins and the credit badges are all explained behind the ? button.'],
        ])
        @if($onboarding)
            <div class="rt-tour" wire:key="tour-{{ $onRecorder ? 'recorder' : 'list' }}" x-data="rtTour(@js($tourSteps))" x-cloak>
                <div class="rt-tour-mask" :style="`top:0;left:0;right:0;height:${Math.max(0, rect.top)}px`"></div>
                <div class="rt-tour-mask" :style="`top:${rect.top + rect.height}px;left:0;right:0;bottom:0`"></div>
                <div class="rt-tour-mask" :style="`top:${rect.top}px;left:0;width:${Math.max(0, rect.left)}px;height:${rect.height}px`"></div>
                <div class="rt-tour-mask" :style="`top:${rect.top}px;left:${rect.left + rect.width}px;right:0;height:${rect.height}px`"></div>
                <div class="rt-tour-ring" x-show="found" :style="`top:${rect.top}px;left:${rect.left}px;width:${rect.width}px;height:${rect.height}px`"></div>
                <div class="rt-tour-pop" :style="popTop !== null ? `top:${popTop}px` : `bottom:${popBottom}px`">
                    <div class="ttl" x-text="step.title"></div>
                    <div class="txt" x-text="step.text"></div>
                    <div class="rt-tour-foot">
                        <span class="rt-tour-count" x-text="`${i + 1} / ${steps.length}`"></span>
                        <button type="button" class="rt-tour-skip" @click="finish()">Skip</button>
                        <span class="grow"></span>
                        <button type="button" class="back" x-show="i > 0" @click="back()">Back</button>
                        <button type="button" class="rt-sheet-done" @click="next()" x-text="i < steps.length - 1 ? 'Next' : 'Done'"></button>
                    </div>
                </div>
            </div>
        @endif

        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('rtTour', (steps) => ({
                    steps,
                    i: 0,
                    found: true,
                    rect: { top: 0, left: 0, width: 0, height: 0 },
                    popTop: 0,
                    popBottom: 0,
                    _on: null,
                    init() {
                        this._on = () => this.position();
                        window.addEventListener('resize', this._on);
                        window.addEventListener('scroll', this._on, true);
                        this.$nextTick(() => this.go(0));
                    },
                    destroy() {
                        window.removeEventListener('resize', this._on);
                        window.removeEventListener('scroll', this._on, true);
                    },
                    get step() { return this.steps[this.i] || { title: '', text: '', target: '' }; },
                    go(n) {
                        this.i = Math.max(0, Math.min(this.steps.length - 1, n));
                        const el = document.querySelector('[data-tour="' + (this.step.target || '') + '"]');
                        if (el) { el.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
                        setTimeout(() => this.position(), 260);
                    },
                    position() {
                        const el = document.querySelector('[data-tour="' + (this.step.target || '') + '"]');
                        this.found = !! el;
                        if (el) {
                            const r = el.getBoundingClientRect(), pad = 6, gap = 12;
                            this.rect = { top: r.top - pad, left: r.left - pad, width: r.width + pad * 2, height: r.height + pad * 2 };
                            if ((r.top + r.height / 2) < window.innerHeight / 2) {
                                this.popTop = this.rect.top + this.rect.height + gap;
                            } else {
                                this.popTop = null;
                                this.popBottom = window.innerHeight - this.rect.top + gap;
                            }
                        } else {
                            this.rect = { top: -9999, left: 0, width: 0, height: 0 };
                            this.popTop = Math.round(window.innerHeight / 2 - 90);
                        }
                    },
                    next() { this.i < this.steps.length - 1 ? this.go(this.i + 1) : this.finish(); },
                    back() { this.go(this.i - 1); },
                    finish() { this.$wire.completeOnboarding(); },
                }));
            });
        </script>

        {{-- ============================ HELP MODAL ============================ --}}
        <div class="rt-help-overlay" x-cloak x-show="help" @click.self="help = false" role="dialog" aria-modal="true" aria-label="How session credits work">
            <div class="rt-help-card">
                <button type="button" class="rt-help-close" @click="help = false" aria-label="Close">✕</button>
                <h3 class="rt-help-title">How session credits work</h3>
                <button type="button" class="rt-linkbtn" @click="help = false" wire:click="openOnboarding" style="margin-bottom:.25rem">↺ Replay the getting-started guide</button>
                <div class="rt-help-section">
                    <h4>The five rules</h4>
                    <ol class="rt-help-rules">
                        <li>Each month's registration buys N sessions ("credits" — usually 4).</li>
                        <li>Attending your own weekly class uses this month's credit — present, late and absent all use one (the spot was held); excused does not.</li>
                        <li>Unused credits never disappear — unused sessions from previous months become "carried" credits.</li>
                        <li>Carried credits are spent by joining an extra session in the same program: free as a make-up, oldest first, same program only and up to the session's own month. With none left, they pay the walk-in fee.</li>
                        <li>Regular monthly sessions never touch carried credits — this month's fee covers this month's classes.</li>
                    </ol>
                </div>
                <div class="rt-help-section">
                    <h4>Badge cheat-sheet</h4>
                    <div class="rt-help-badges">
                        <div class="rt-help-badgerow"><span class="rt-badge credits">2/4</span> <span>in progress — 2 of 4 paid sessions used.</span></div>
                        <div class="rt-help-badgerow"><span class="rt-badge credits full">4/4 · paid up</span> <span>all paid sessions used — renewal due soon.</span></div>
                        <div class="rt-help-badgerow"><span class="rt-badge credits over">5/4 · +1 over</span> <span>over-delivered — never blocked, just flagged.</span></div>
                        <div class="rt-help-badgerow"><span class="rt-badge carry">+2 carried</span> <span>unused past sessions, usable as free make-ups.</span></div>
                        <div class="rt-help-badgerow"><span class="rt-badge extra">make-up</span> <span>extra session paid by a carried credit.</span></div>
                        <div class="rt-help-badgerow"><span class="rt-badge walkin">walk-in · RM40</span> <span>extra session, no credits left — pays the fee.</span></div>
                    </div>
                </div>
                <div class="rt-help-foot">Credits belong to the program they were bought for. Nothing is ever blocked; over-limit sessions are simply flagged for renewal.</div>
            </div>
        </div>
    </x-coach-shell>
</x-filament::page>
