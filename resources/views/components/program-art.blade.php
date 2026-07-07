{{-- Illustrated tile artwork: pitch-line markings + a line-drawn football + a dot-grid pattern,
     drawn in white over the parent's theme gradient. `seed` (e.g. the program id) picks one of
     three stable arrangements so cards vary without flickering between page loads. --}}
@props(['seed' => 0])
@php($variant = ((int) $seed) % 3)
@php($ball = match ($variant) {
    0 => ['x' => 315, 'y' => 128, 'r' => 92],   // large, right of centre
    1 => ['x' => 70, 'y' => 235, 'r' => 110],   // peeking from bottom-left
    default => ['x' => 345, 'y' => 38, 'r' => 72], // smaller, top-right
})
<svg {{ $attributes->merge(['class' => 'pointer-events-none absolute inset-0 h-full w-full']) }}
    viewBox="0 0 400 260" preserveAspectRatio="xMidYMid slice" fill="none" aria-hidden="true">
    <defs>
        <pattern id="dots-{{ $seed }}" width="18" height="18" patternUnits="userSpaceOnUse">
            <circle cx="2" cy="2" r="1.4" fill="#ffffff"/>
        </pattern>
        <clipPath id="tile-{{ $seed }}"><rect width="400" height="260"/></clipPath>
    </defs>

    <g clip-path="url(#tile-{{ $seed }})">
        {{-- soft light bloom behind the ball --}}
        <circle cx="{{ $ball['x'] }}" cy="{{ $ball['y'] }}" r="{{ $ball['r'] + 46 }}" fill="#ffffff" opacity="0.07"/>

        {{-- dot-grid corner, opposite the ball --}}
        <rect x="{{ $variant === 1 ? 250 : 16 }}" y="{{ $variant === 2 ? 160 : 16 }}" width="132" height="86"
            fill="url(#dots-{{ $seed }})" opacity="0.22"/>

        {{-- pitch markings, kept faint so the ball leads --}}
        <g stroke="#ffffff" stroke-width="2" opacity="0.14">
            <rect x="14" y="14" width="372" height="232" rx="2"/>
            <line x1="200" y1="14" x2="200" y2="246"/>
            <circle cx="200" cy="130" r="38"/>
            <rect x="14" y="62" width="58" height="136"/>
            <rect x="328" y="62" width="58" height="136"/>
        </g>

        {{-- the football: rim, pentagon, spokes --}}
        <g transform="translate({{ $ball['x'] }} {{ $ball['y'] }}) scale({{ round($ball['r'] / 40, 3) }})"
            stroke="#ffffff" stroke-width="2.6" opacity="0.5">
            <circle r="40"/>
            <path d="M0 -15 L14.3 -4.6 L8.8 12.1 L-8.8 12.1 L-14.3 -4.6 Z"/>
            <line x1="0" y1="-15" x2="0" y2="-40"/>
            <line x1="14.3" y1="-4.6" x2="38" y2="-12.4"/>
            <line x1="8.8" y1="12.1" x2="23.5" y2="32.4"/>
            <line x1="-8.8" y1="12.1" x2="-23.5" y2="32.4"/>
            <line x1="-14.3" y1="-4.6" x2="-38" y2="-12.4"/>
        </g>
    </g>
</svg>
