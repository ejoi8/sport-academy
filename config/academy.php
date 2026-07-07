<?php

return [
    'rubrics' => [
        'outfield' => [
            'first_touch' => 'First Touch / Control',
            'passing' => 'Passing',
            'dribbling' => 'Dribbling',
            'shooting' => 'Shooting / Finishing',
            'positioning' => 'Positioning / Awareness',
            'attitude' => 'Attitude / Effort',
            'teamwork' => 'Teamwork',
        ],
        'goalkeeper' => [
            'handling' => 'Handling',
            'shot_stopping' => 'Shot-stopping',
            'positioning' => 'Positioning / Angles',
            'distribution' => 'Distribution / Kicking',
            'footwork' => 'Footwork',
            'communication' => 'Communication',
        ],
    ],

    'features' => [
        'advanced_reports' => [
            'key' => 'advanced_reports',
            'label' => 'Advanced Reports',
            'price_sen' => 990,            // RM9.90 / child / month — the ONLY launch price
            // 'term_price_sen' => 2490,   // DEFERRED no-op (pin P8); add only when term ships
            'blurb' => 'Term report card PDF, progress trends, radar & full history.',
            'unlocks' => [
                'Term Report Card PDF (downloadable & shareable)',
                'Progress dashboard + skill trend over time',
                'Radar chart (skill snapshot)',
                'Multi-month / full-history view',
            ],
        ],
        // future_module => [ ... ]  // a new add-on is just another entry here
    ],

    'score' => ['min' => 1, 'max' => 5],

    // Public-site contact details. Placeholder values — update with the academy's real
    // numbers/address (or set the env keys if they differ per environment).
    'contact' => [
        'phone' => env('ACADEMY_PHONE', '+60 12-345 6789'),
        'whatsapp' => env('ACADEMY_WHATSAPP', '60123456789'), // digits only, for wa.me links
        'email' => env('ACADEMY_EMAIL', 'hello@footballacademy.my'),
        'address' => env('ACADEMY_ADDRESS', 'Kompleks Sukan Putrajaya, Presint 5, Putrajaya'),
        'hours' => env('ACADEMY_HOURS', 'Sat–Sun · 8am–7pm'),
    ],
];
