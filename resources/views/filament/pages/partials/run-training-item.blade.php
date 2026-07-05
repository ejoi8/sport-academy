@php($scored = collect($row['scores'])->filter(fn ($s) => ! is_null($s))->count())
@php($total = count($row['scores']))
@php($isOpen = $expandedKey === $key)
@php($absent = in_array($row['status'], ['absent', 'excused'], true))
@php($fullyScored = $total > 0 && $scored === $total && ! $absent)
@php($coachName = $coachOptions[$row['coach_id']] ?? null)
@php($used = (int) ($row['credits_used'] ?? 0))
@php($totalCredits = (int) ($row['credits_total'] ?? 0))
@php($over = $row['type'] === 'enrolled' && ! is_null($row['credits_total'] ?? null) && (int) ($row['credits_total']) > 0 && $used > $totalCredits)

<div class="rt-item {{ $isOpen ? 'open' : '' }} {{ $fullyScored ? 'done' : ($absent ? 'excused' : '') }} {{ $over ? 'overlimit' : '' }}">
    <div class="rt-row" role="button" tabindex="0" aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
        wire:click="toggle('{{ $key }}')"
        wire:keydown.enter="toggle('{{ $key }}')"
        wire:keydown.space.prevent="toggle('{{ $key }}')">
        <span class="chev" aria-hidden="true">▾</span>
        <span class="rt-name">
            {{ $row['name'] }}
            <span class="rt-sub">@if($row['student_id'] ?? null)ID {{ $row['student_id'] }}@else new @endif@if(! empty($row['ic'])) · No. KP {{ $row['ic'] }}@endif@if($over) · <span class="rt-overnote">no paid credits left — renewal due</span>@endif</span>
        </span>

        @if($row['type'] === 'enrolled')
            @if($row['payment_status'])
                <span class="rt-badge pay-{{ $row['payment_status'] }}">{{ $row['payment_status'] === 'active' ? 'paid' : $row['payment_status'] }}</span>
            @endif
            @if(! is_null($row['credits_total']))
                @if($used < $totalCredits)
                    <span class="rt-badge credits" title="Sessions used of paid credits">{{ $used }}/{{ $totalCredits }}</span>
                @elseif($used === $totalCredits && $totalCredits > 0)
                    <span class="rt-badge credits full" title="Sessions used of paid credits">{{ $used }}/{{ $totalCredits }} · paid up</span>
                @else
                    <span class="rt-badge credits over" title="Sessions used of paid credits">{{ $used }}/{{ $totalCredits }} · +{{ $used - $totalCredits }} over</span>
                @endif
            @endif
            @if(($row['carry_over'] ?? 0) > 0)
                <span class="rt-badge carry" title="Unused sessions from other enrolments — usable as make-ups">+{{ $row['carry_over'] }} carried</span>
            @endif
        @elseif($row['type'] === 'make_up')
            <span class="rt-badge extra">make-up</span>
            @if(! is_null($row['credits_total'] ?? null))
                <span class="rt-badge credits" title="The carried pool this make-up draws from — one credit is used on save">pool {{ (int) ($row['credits_used'] ?? 0) }}/{{ (int) $row['credits_total'] }}</span>
            @endif
        @else
            <span class="rt-badge walkin">walk-in · RM{{ number_format(($row['fee_sen'] ?? 0) / 100, 2) }}</span>
        @endif

        <span class="rt-rowmeta">{{ $coachName ?? 'no coach' }} · {{ ucfirst($row['status']) }} · {{ $absent ? '—' : $scored.'/'.$total }}@if($fullyScored) <span class="rt-done">✓ done</span>@endif</span>

        @if($removable)
            <button type="button" class="rt-remove" wire:click.stop="removeRow('{{ $key }}')" title="Remove" aria-label="Remove {{ $row['name'] }}">✕</button>
        @endif
    </div>

    @if($isOpen)
        <div class="rt-card">
            <div class="rt-cardtop">
                <label class="rt-field">
                    Coach
                    <select wire:model.live="roster.{{ $key }}.coach_id">
                        <option value="">— unassigned —</option>
                        @foreach($coachOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="rt-att">
                    @foreach(['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused'] as $val => $label)
                        <button type="button" class="{{ $row['status'] === $val ? 'sel '.$val : '' }}" wire:click="setStatus('{{ $key }}', '{{ $val }}')">{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            @if($skills->isEmpty())
                <div class="rt-muted">No active skills configured — set up a rubric to score players.</div>
            @else
                <div class="rt-skills" @if($absent) style="opacity:.45" title="Scores are not recorded for an absent player" @endif>
                    @foreach($skills as $skill)
                        <div class="rt-skill">
                            <span class="n">{{ $skill->name }}</span>
                            <span class="rt-pills">
                                @for($n = 1; $n <= 5; $n++)
                                    <button type="button" class="rt-pill {{ ($row['scores'][$skill->id] ?? null) === $n ? 'sel' : '' }}"
                                        wire:click="setScore('{{ $key }}', {{ $skill->id }}, {{ $n }})" @disabled($absent)>{{ $n }}</button>
                                @endfor
                            </span>
                        </div>
                    @endforeach
                </div>
                @if($absent && $scored > 0)
                    <div class="rt-warn">Scores stay on screen but are not saved while marked {{ $row['status'] }}.</div>
                @endif
            @endif

            <textarea class="rt-note" wire:model.live.debounce.500ms="roster.{{ $key }}.note" placeholder="Note (optional)"></textarea>
        </div>
    @endif
</div>
