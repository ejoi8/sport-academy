<?php

namespace App\Livewire\PublicSite;

use App\Models\Program;
use Livewire\Component;

class ProgramBrowser extends Component
{
    public function render()
    {
        $programs = Program::query()
            ->where('is_active', true)
            ->whereHas('offerings', fn ($query) => $query->publiclyBookable())
            ->with([
                'offerings' => fn ($query) => $query->publiclyBookable()
                    ->withCount([
                        'enrollments as held_seats_count' => fn ($enrollments) => $enrollments->whereIn('status', ['active', 'pending']),
                    ])
                    ->orderBy('period')
                    ->orderBy('weekday')
                    ->orderBy('start_time'),
            ])
            ->orderBy('name')
            ->get();

        return view('livewire.public-site.program-browser', [
            'programs' => $programs,
        ])->layout('layouts.public', [
            'title' => 'Football Academy',
        ]);
    }
}
