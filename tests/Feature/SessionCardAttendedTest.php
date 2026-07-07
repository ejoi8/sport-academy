<?php

use App\Filament\Pages\RunTraining;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\BaselineSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

it('counts only present and late players as attended on the session card', function () {
    $this->seed(BaselineSeeder::class);

    $coach = User::where('email', 'coach@academy.test')->firstOrFail();
    $offering = Offering::query()
        ->where('is_open', true)
        ->whereHas('program', fn ($query) => $query->where('name', 'Group'))
        ->where('weekday', 6)
        ->orderBy('start_time')
        ->firstOrFail();

    // Three enrolled players on the Saturday Group slot.
    $keys = collect(['Ari Absent', 'Pia Present', 'Leo Late'])->map(function (string $name, int $index) use ($offering): string {
        $student = Student::create(['name' => $name, 'ic_number' => '99'.str_pad((string) $index, 10, '0', STR_PAD_LEFT), 'is_active' => true]);
        Enrollment::create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => 'active',
            'price_sen' => $offering->price_sen,
            'sessions_included' => $offering->session_count,
        ]);

        return 's'.$student->id;
    });

    $date = Carbon::parse($offering->period.'-01')->startOfMonth();
    while ($date->dayOfWeekIso !== $offering->weekday) {
        $date->addDay();
    }
    $date = $date->toDateString();

    $this->actingAs($coach);

    // One absent, one late, one present (present is the default) — 2 of the 3 actually showed.
    Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->set('offeringId', $offering->id)
        ->call('setStatus', $keys[0], 'absent')
        ->call('setStatus', $keys[2], 'late')
        ->call('save');

    // The card's "attended" tally reflects who showed (present + late), excluding the absentee.
    $card = collect(Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->instance()
        ->sessionsOnDate)
        ->firstWhere('id', $offering->id);

    expect($card['recorded'])->toBeTrue()
        ->and($card['enrolled'])->toBe(3)
        ->and($card['attended'])->toBe(2);
});
