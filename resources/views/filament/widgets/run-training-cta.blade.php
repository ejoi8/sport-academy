{{-- Prominent Run Training shortcut for the top of the dashboard. Self-contained
     styles (scoped .rtcta-* classes) so it never depends on the panel's Tailwind
     build — a green banner, bold label, and one big tap target into a live session. --}}
<x-filament-widgets::widget>
    <style>
        .rtcta {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.15rem 1.25rem;
            border-radius: 0.85rem;
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: #fff;
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(21, 128, 61, 0.28);
            transition: filter 0.12s ease, transform 0.12s ease, box-shadow 0.12s ease;
        }
        .rtcta:hover { filter: brightness(1.06); box-shadow: 0 6px 20px rgba(21, 128, 61, 0.38); }
        .rtcta:active { transform: translateY(1px); }
        .rtcta:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
        .rtcta__icon {
            flex: none;
            width: 3rem;
            height: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.6rem;
            background: rgba(255, 255, 255, 0.18);
        }
        .rtcta__icon svg { width: 1.5rem; height: 1.5rem; }
        .rtcta__body { flex: 1 1 auto; min-width: 0; }
        .rtcta__title { display: block; font-size: 1.125rem; font-weight: 700; line-height: 1.2; }
        .rtcta__sub { display: block; margin-top: 0.15rem; font-size: 0.85rem; color: rgba(255, 255, 255, 0.85); }
        .rtcta__go { flex: none; display: flex; align-items: center; gap: 0.35rem; font-size: 0.9rem; font-weight: 600; }
        .rtcta__go svg { width: 1.25rem; height: 1.25rem; transition: transform 0.12s ease; }
        .rtcta:hover .rtcta__go svg { transform: translateX(2px); }
        @media (max-width: 640px) {
            .rtcta__title { font-size: 1.02rem; }
            .rtcta__go-label { display: none; }
        }
    </style>

    <a href="{{ $this->getRunTrainingUrl() }}" class="rtcta">
        <span class="rtcta__icon" aria-hidden="true">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" />
            </svg>
        </span>

        <span class="rtcta__body">
            <span class="rtcta__title">Run Training</span>
            <span class="rtcta__sub">Take attendance and assess your squad — one tap away.</span>
        </span>

        <span class="rtcta__go">
            <span class="rtcta__go-label">Open</span>
            <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
            </svg>
        </span>
    </a>
</x-filament-widgets::widget>
