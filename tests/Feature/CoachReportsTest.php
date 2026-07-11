<?php

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\ParticipantType;
use App\Filament\Pages\CoachReports;
use App\Models\AssessmentScore;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Skill;
use App\Models\Student;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\Reporting\ProgressSummary;
use Database\Seeders\BaselineSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->seed(BaselineSeeder::class);
    $this->coach = User::where('email', 'coach@academy.test')->firstOrFail();
    $this->actingAs($this->coach);
});

/** One recorded, scored, present session led by the given coach this month. */
function scoredSession(User $coach, ?Offering $offering = null): Skill
{
    $offering ??= Offering::query()->firstOrFail();
    $skill = Skill::query()->firstOrFail();
    $student = Student::create(['name' => 'Rep '.uniqid(), 'is_active' => true]);

    $enrollment = Enrollment::create([
        'student_id' => $student->id,
        'offering_id' => $offering->id,
        'status' => EnrollmentStatus::Active,
        'price_sen' => 10000,
        'sessions_included' => 4,
    ]);

    $session = TrainingSession::create([
        'offering_id' => $offering->id,
        'session_date' => today()->toDateString(),
        'coach_id' => $coach->id,
        'created_by' => $coach->id,
    ]);

    $attendance = Attendance::create([
        'training_session_id' => $session->id,
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'participant_type' => ParticipantType::Enrolled,
        'status' => AttendanceStatus::Present,
        'coach_id' => $coach->id,
        'marked_by' => $coach->id,
    ]);

    AssessmentScore::create(['attendance_id' => $attendance->id, 'skill_id' => $skill->id, 'score' => 4]);

    return $skill;
}

it('renders the reports tab for a coach', function () {
    $this->get(CoachReports::getUrl())
        ->assertOk()
        ->assertSee('Reports')
        ->assertSee('Average score');
});

it('summarises the coach delivery, attendance and progress', function () {
    $skill = scoredSession($this->coach);

    $component = Livewire::test(CoachReports::class)->assertOk()->assertSee($skill->name);

    expect($component->instance()->attendance()['sessions_delivered'])->toBe(1)
        ->and($component->instance()->attendance()['attendance_rate'])->toBe(100.0)
        ->and($component->instance()->overallAverage())->toBe(4.0)
        ->and($component->instance()->trend())->toHaveCount(6);
});

it('scopes progress to the signed-in coach', function () {
    $offerings = Offering::query()->orderBy('id')->take(2)->get();

    scoredSession($this->coach, $offerings[0]);

    $other = User::factory()->create();
    scoredSession($other, $offerings[1]);

    // The coach's roll-up counts only their own scores (1), not the other coach's.
    $mine = collect(ProgressSummary::build($this->coach->id)['by_program'])->sum('total_scores');
    $all = collect(ProgressSummary::build()['by_program'])->sum('total_scores');

    expect($mine)->toBe(1)->and($all)->toBe(2);
});
