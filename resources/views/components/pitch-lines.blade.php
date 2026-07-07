{{-- Top-down football-pitch line markings — the site's signature background motif.
     Absolutely fills its (relative) parent; pass classes for opacity/size tweaks. --}}
@props(['stroke' => '#ffffff', 'opacity' => '0.16'])
<svg {{ $attributes->merge(['class' => 'pointer-events-none absolute inset-0 h-full w-full']) }}
    viewBox="0 0 400 260" preserveAspectRatio="xMidYMid slice" fill="none" aria-hidden="true">
    <g stroke="{{ $stroke }}" stroke-width="2" opacity="{{ $opacity }}">
        {{-- outer boundary --}}
        <rect x="14" y="14" width="372" height="232" rx="2"/>
        {{-- halfway line + centre circle --}}
        <line x1="200" y1="14" x2="200" y2="246"/>
        <circle cx="200" cy="130" r="38"/>
        <circle cx="200" cy="130" r="2.5" fill="{{ $stroke }}"/>
        {{-- left penalty box + six-yard box + arc --}}
        <rect x="14" y="62" width="58" height="136"/>
        <rect x="14" y="96" width="24" height="68"/>
        <path d="M72 106 A38 38 0 0 1 72 154"/>
        {{-- right penalty box + six-yard box + arc --}}
        <rect x="328" y="62" width="58" height="136"/>
        <rect x="362" y="96" width="24" height="68"/>
        <path d="M328 106 A38 38 0 0 0 328 154"/>
        {{-- corner arcs --}}
        <path d="M14 26 A12 12 0 0 0 26 14"/>
        <path d="M374 14 A12 12 0 0 0 386 26"/>
        <path d="M386 234 A12 12 0 0 0 374 246"/>
        <path d="M26 246 A12 12 0 0 0 14 234"/>
    </g>
</svg>
