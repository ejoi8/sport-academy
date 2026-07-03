<?php

use App\Filament\Pages\RunTraining;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Program;
use App\Models\Sport;
use App\Models\Student;
use App\Models\TrainingSession;
use App\Models\User;
use Database\Seeders\BaselineSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Create an active enrolment granting the given number of session credits. */
function enrolmentWithCredits(int $credits): Enrollment
{
    $sport = Sport::create(['name' => 'Football', 'is_active' => true]);
    $program = Program::create(['sport_id' => $sport->id, 'name' => 'Group', 'base_price_sen' => 12000, 'walk_in_fee_sen' => 4000, 'default_sessions' => $credits]);
    $offering = Offering::create([
        'program_id' => $program->id,
        'period' => now()->format('Y-m'),
        'schedule_type' => 'recurring',
        'weekday' => 3,
        'start_time' => '18:00',
        'end_time' => '19:30',
        'capacity' => 12,
        'session_count' => $credits,
        'price_sen' => 12000,
        'is_open' => true,
    ]);
    $student = Student::create(['name' => 'Kid A', 'is_active' => true]);

    return Enrollment::create([
        'student_id' => $student->id,
        'offering_id' => $offering->id,
        'status' => 'active',
        'price_sen' => 12000,
        'sessions_included' => $credits,
    ]);
}

/** Record one attendance against an enrolment on its own session. */
function attend(Enrollment $enrolment, string $status, int $dayOffset): void
{
    $session = TrainingSession::create([
        'offering_id' => $enrolment->offering_id,
        'session_date' => now()->startOfMonth()->addDays($dayOffset)->toDateString(),
    ]);

    Attendance::create([
        'training_session_id' => $session->id,
        'student_id' => $enrolment->student_id,
        'enrollment_id' => $enrolment->id,
        'participant_type' => 'enrolled',
        'status' => $status,
    ]);
}

it('counts present, late and absent as consumed credits but not excused', function () {
    $enrolment = enrolmentWithCredits(5);

    attend($enrolment, 'present', 1);
    attend($enrolment, 'late', 8);
    attend($enrolment, 'absent', 15);
    attend($enrolment, 'excused', 22);

    expect($enrolment->creditsUsed())->toBe(3)
        ->and($enrolment->creditsRemaining())->toBe(2)
        ->and($enrolment->hasLiveCredits())->toBeTrue();
});

it('treats a fully-consumed enrolment as a walk-in, not a free make-up', function () {
    // The July→August case: paid for 2, attended both, then shows up elsewhere.
    $enrolment = enrolmentWithCredits(2);
    attend($enrolment, 'present', 1);
    attend($enrolment, 'present', 8);

    expect($enrolment->creditsRemaining())->toBe(0)
        ->and($enrolment->student->liveCreditEnrollment())->toBeNull();
});

it('offers a make-up while the enrolment still has a live credit', function () {
    $enrolment = enrolmentWithCredits(2);
    attend($enrolment, 'present', 1); // 1 of 2 used, 1 left

    $found = $enrolment->student->liveCreditEnrollment();

    expect($found)->not->toBeNull()
        ->and($found->is($enrolment))->toBeTrue()
        ->and($found->creditsRemaining())->toBe(1);
});

it('consumes a credit and links the attendance to the enrolment when a session is saved', function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->seed(BaselineSeeder::class);
    $coach = User::where('email', 'coach@academy.test')->firstOrFail();
    $this->actingAs($coach);

    $offering = Offering::where('is_open', true)->has('enrollments')->firstOrFail();
    $enrolment = $offering->enrollments()->where('status', 'active')->firstOrFail();

    Livewire::test(RunTraining::class)
        ->set('offeringId', $offering->id)
        ->call('startSession') // load the enrolled roster (all present), then record it
        ->call('save');

    $enrolment->refresh();

    expect($enrolment->creditsUsed())->toBe(1);

    $attendance = Attendance::where('student_id', $enrolment->student_id)
        ->where('enrollment_id', $enrolment->id)
        ->first();

    expect($attendance)->not->toBeNull()
        ->and($attendance->status->value)->toBe('present');
});
