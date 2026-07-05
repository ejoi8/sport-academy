<?php

namespace App\Livewire\PublicSite;

use App\Models\Program;
use Livewire\Component;

class ProgramShow extends Component
{
    public Program $program;

    public function mount(Program $program): void
    {
        $program->load([
            'offerings' => fn ($query) => $query->publiclyBookable()
                ->withCount([
                    'enrollments as held_seats_count' => fn ($enrollments) => $enrollments->whereIn('status', ['active', 'pending']),
                ])
                ->orderBy('period')
                ->orderBy('weekday')
                ->orderBy('start_time'),
        ]);

        abort_if($program->offerings->isEmpty(), 404);

        $this->program = $program;
    }

    public function render()
    {
        return view('livewire.public-site.program-show')->layout('layouts.public', [
            'title' => $this->program->name,
        ]);
    }
}
