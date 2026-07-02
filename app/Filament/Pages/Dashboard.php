<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    /**
     * A two-column grid on medium screens and up; single column on mobile.
     */
    public function getColumns(): int|array
    {
        return ['default' => 1, 'md' => 2];
    }

    public function getHeading(): string
    {
        $firstName = str(auth()->user()?->name ?? '')->before(' ')->toString();
        $greeting = match (true) {
            now()->hour < 12 => 'Good morning',
            now()->hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        return trim($greeting.($firstName !== '' ? ', '.$firstName : ''));
    }

    public function getSubheading(): ?string
    {
        return now()->format('l, j F Y');
    }
}
