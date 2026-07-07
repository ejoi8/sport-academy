<?php

namespace App\Livewire\PublicSite;

use App\Models\AssessmentScore;
use App\Models\Program;
use App\Models\Student;
use App\Models\TrainingSession;
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
                    ->with('defaultCoach')
                    ->withCount([
                        'enrollments as held_seats_count' => fn ($enrollments) => $enrollments->whereIn('status', ['active', 'pending']),
                    ])
                    ->orderBy('period')
                    ->orderBy('weekday')
                    ->orderBy('start_time'),
            ])
            ->orderBy('name')
            ->get();

        // Honest numbers for the "why train with us" band — hidden until there's real history.
        $sessionsDelivered = TrainingSession::count();

        return view('livewire.public-site.program-browser', [
            'programs' => $programs,
            'stats' => $sessionsDelivered > 0 ? [
                'students' => Student::where('is_active', true)->count(),
                'sessions' => $sessionsDelivered,
                'scores' => AssessmentScore::count(),
            ] : null,
            'contact' => config('academy.contact'),
        ])->layout('layouts.public', [
            'title' => 'Football Academy',
        ]);
    }
}
