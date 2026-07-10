{{-- A single collapsed player row: identity + quick status cycle. Tapping the body opens the
     editing bottom sheet (skill scores, coach, note). --}}
@php($cycle = ['present' => 'late', 'late' => 'absent', 'absent' => 'excused', 'excused' => 'present'])
@php($next = $cycle[$row['status']] ?? 'present')
@php($word = ucfirst($row['status']))
@php($scored = collect($row['scores'])->filter(fn ($s) => ! is_null($s))->count())
@php($total = count($row['scores']))
@php($absent = in_array($row['status'], ['absent', 'excused'], true))
@php($fully = $total > 0 && $scored === $total && ! $absent)
@php($over = $row['type'] === 'enrolled' && ! is_null($row['credits_total'] ?? null) && (int) $row['credits_total'] > 0 && (int) $row['credits_used'] > (int) $row['credits_total'])

<div class="rt-prow {{ $fully ? 'done' : '' }} {{ $absent ? 'excused' : '' }} {{ $over ? 'overlimit' : '' }}">
    <button type="button" class="rt-prow-main" wire:click="toggle('{{ $key }}')">
        <span class="rt-pav" aria-hidden="true">{{ mb_substr($row['name'], 0, 1) }}</span>
        <span style="min-width:0">
            <span class="rt-pname">{{ $row['name'] }}</span>
            <span class="rt-psub">
                @if($row['type'] === 'enrolled')
                    @if(! is_null($row['credits_total']))<span class="rt-badge credits {{ $over ? 'over' : '' }}">{{ (int) $row['credits_used'] }}/{{ (int) $row['credits_total'] }}</span>@endif
                    @if(($row['carry_over'] ?? 0) > 0)<span class="rt-badge carry">+{{ $row['carry_over'] }}</span>@endif
                @elseif($row['type'] === 'make_up')
                    <span class="rt-badge extra">make-up</span>
                @else
                    <span class="rt-badge walkin">RM{{ number_format(($row['fee_sen'] ?? 0) / 100, 0) }}</span>
                @endif
                @if(! $absent)<span class="rt-scored {{ $fully ? 'full' : '' }}">{{ $scored }}/{{ $total }} scored</span>@endif
            </span>
        </span>
    </button>
    <button type="button" class="rt-cyc {{ $row['status'] }}" wire:click="setStatus('{{ $key }}', '{{ $next }}')" title="Currently {{ $word }} — tap to change">
        <span class="d" aria-hidden="true"></span>{{ $word }}
    </button>
    @if($removable)<button type="button" class="rt-remove" wire:click="removeRow('{{ $key }}')" aria-label="Remove {{ $row['name'] }}">✕</button>@endif
</div>
