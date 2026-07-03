<?php

use App\Filament\Pages\RunTraining;
use App\Models\AssessmentScore;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Program;
use App\Models\Skill;
use App\Models\Student;
use App\Models\TrainingSession;
use App\Models\User;
use Database\Seeders\BaselineSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

/**
 * @return array{0: User, 1: Offering, 2: Skill, 3: string}
 */
function runTrainingContext(): array
{
    test()->seed(BaselineSeeder::class);

    $coach = User::where('email', 'coach@academy.test')->firstOrFail();
    $offering = Offering::where('is_open', true)->has('enrollments')->firstOrFail();
    $skill = Skill::query()->orderBy('sort_order')->firstOrFail();
    $student = $offering->enrollments()->with('student')->first()->student;

    test()->actingAs($coach);

    return [$coach, $offering, $skill, 's'.$student->id];
}

it('records attendance and rubric scores for the enrolled roster', function () {
    [$coach, $offering, $skill, $key] = runTrainingContext();

    $enrolledCount = Enrollment::where('offering_id', $offering->id)
        ->whereIn('status', ['active', 'pending', 'overdue'])
        ->count();

    Livewire::test(RunTraining::class)
        ->set('offeringId', $offering->id)
        ->call('startSession')
        ->assertSet('roster.'.$key.'.type', 'enrolled')
        ->call('setStatus', $key, 'present')
        ->call('setScore', $key, $skill->id, 4)
        ->call('save');

    $session = TrainingSession::firstOrFail();
    expect($session->offering_id)->toBe($offering->id)
        ->and($session->coach_id)->toBe($coach->id);

    expect(Attendance::count())->toBe($enrolledCount);

    $this->assertDatabaseHas('assessment_scores', ['skill_id' => $skill->id, 'score' => 4]);
});

it('shows an empty roster until the session is started', function () {
    [, $offering, , $key] = runTrainingContext();

    Livewire::test(RunTraining::class)
        ->set('offeringId', $offering->id)
        ->assertSet('started', false)
        ->assertCount('roster', 0)          // no phantom roster on a date with no session
        ->call('startSession')
        ->assertSet('started', true)
        ->assertSet('roster.'.$key.'.type', 'enrolled'); // now the enrolled roster loads
});

it('tapping the selected score pill again clears it, and saving deletes the stored score', function () {
    [, $offering, $skill, $key] = runTrainingContext();

    $component = Livewire::test(RunTraining::class)
        ->set('offeringId', $offering->id)
        ->call('startSession')
        ->call('setScore', $key, $skill->id, 3)
        ->call('save');

    $this->assertDatabaseHas('assessment_scores', ['skill_id' => $skill->id, 'score' => 3]);

    $component->call('setScore', $key, $skill->id, 3) // toggle off
        ->assertSet('roster.'.$key.'.scores.'.$skill->id, null)
        ->call('save');

    $this->assertDatabaseMissing('assessment_scores', ['skill_id' => $skill->id, 'score' => 3]);
});

it('does not record a fee or scores for a walk-in marked absent', function () {
    [, $offering, $skill] = runTrainingContext();

    Livewire::test(RunTraining::class)
        ->set('offeringId', $offering->id)
        ->call('startSession')
        ->call('startAdd')
        ->set('newName', 'No Show')
        ->call('addNewWalkIn') // first new walk-in => key 'n1'
        ->call('setScore', 'n1', $skill->id, 5)
        ->call('setStatus', 'n1', 'absent')
        ->call('save');

    $student = Student::where('name', 'No Show')->firstOrFail();

    $this->assertDatabaseHas('attendances', [
        'student_id' => $student->id,
        'status' => 'absent',
        'walk_in_fee_sen' => null,
    ]);
    $this->assertDatabaseMissing('assessment_scores', ['score' => 5]);
});

it('re-saving does not duplicate a newly created walk-in student', function () {
    [, $offering] = runTrainingContext();

    $before = Student::count();

    Livewire::test(RunTraining::class)
        ->set('offeringId', $offering->id)
        ->call('startSession')
        ->call('startAdd')
        ->set('newName', 'Twice Saver')
        ->call('addNewWalkIn')
        ->call('save')
        ->call('save'); // second save must not create another student

    expect(Student::where('name', 'Twice Saver')->count())->toBe(1)
        ->and(Student::count())->toBe($before + 1);
});

it('hydrates a previously saved session when the same timeslot and date is re-opened', function () {
    [, $offering, $skill, $key] = runTrainingContext();

    Livewire::test(RunTraining::class)
        ->set('offeringId', $offering->id)
        ->call('startSession')
        ->call('setStatus', $key, 'late')
        ->call('setScore', $key, $skill->id, 2)
        ->call('save');

    // A fresh page load (mount defaults to this offering + today) must reflect the saved state.
    Livewire::test(RunTraining::class)
        ->assertSet('offeringId', $offering->id)
        ->assertSet('savedSessionExists', true)
        ->assertSet('roster.'.$key.'.status', 'late')
        ->assertSet('roster.'.$key.'.scores.'.$skill->id, 2);
});

it('deletes a saved session and cascades its attendance and scores', function () {
    [, $offering, $skill, $key] = runTrainingContext();

    Livewire::test(RunTraining::class)
        ->set('offeringId', $offering->id)
        ->call('startSession')
        ->call('setScore', $key, $skill->id, 4)
        ->call('save')
        ->assertSet('savedSessionExists', true)
        ->call('deleteSession')
        ->assertSet('savedSessionExists', false);

    expect(TrainingSession::count())->toBe(0)
        ->and(Attendance::count())->toBe(0)
        ->and(AssessmentScore::count())->toBe(0);
});

it('removing a saved participant and re-saving deletes their attendance', function () {
    [, $offering, $skill, $key] = runTrainingContext();

    Livewire::test(RunTraining::class)
        ->set('offeringId', $offering->id)
        ->call('startSession')
        ->call('setScore', $key, $skill->id, 3)
        ->call('save');

    $savedCount = Attendance::count();
    expect($savedCount)->toBeGreaterThan(1);

    // Re-open (hydrated), remove one participant, save again.
    Livewire::test(RunTraining::class)
        ->assertSet('roster.'.$key.'.scores.'.$skill->id, 3)
        ->call('removeRow', $key)
        ->call('save');

    expect(Attendance::where('student_id', (int) substr($key, 1))->count())->toBe(0)
        ->and(Attendance::count())->toBe($savedCount - 1);
});

it('defaults each student to the head coach and persists a per-student coach change', function () {
    [$coach, $offering, , $key] = runTrainingContext();

    $other = User::factory()->create(['name' => 'Coach Two']);
    $studentId = (int) substr($key, 1);

    Livewire::test(RunTraining::class)
        ->set('offeringId', $offering->id)
        ->call('startSession')
        ->assertSet('headCoachId', $coach->id)
        ->assertSet('roster.'.$key.'.coach_id', $coach->id) // defaulted to the timeslot head coach
        ->set('roster.'.$key.'.coach_id', $other->id)       // reassign just this student
        ->call('save');

    $this->assertDatabaseHas('attendances', [
        'student_id' => $studentId,
        'coach_id' => $other->id,
    ]);
});

it('bulk-assigns every player to one coach', function () {
    [, $offering, , $key] = runTrainingContext();

    $other = User::factory()->create(['name' => 'Coach Bulk']);
    $studentId = (int) substr($key, 1);

    Livewire::test(RunTraining::class)
        ->set('offeringId', $offering->id)
        ->call('startSession')
        ->set('bulkCoachId', $other->id)
        ->call('assignAll')
        ->assertSet('roster.'.$key.'.coach_id', $other->id)
        ->call('save');

    $this->assertDatabaseHas('attendances', ['student_id' => $studentId, 'coach_id' => $other->id]);
});

it('adds a coach on the fly who becomes assignable', function () {
    [, $offering] = runTrainingContext();

    Livewire::test(RunTraining::class)
        ->set('offeringId', $offering->id)
        ->call('startAddCoach')
        ->set('newCoachName', 'Coach Fresh')
        ->call('saveCoach')
        ->assertSet('addingCoach', false);

    $this->assertDatabaseHas('users', ['name' => 'Coach Fresh']);
});

it('scopes the timeslot dropdown to the selected month and switches with it', function () {
    [, $julyOffering] = runTrainingContext();

    $juneOffering = Offering::create([
        'program_id' => $julyOffering->program_id,
        'period' => now()->subMonthNoOverflow()->format('Y-m'),
        'schedule_type' => 'recurring',
        'weekday' => 3,
        'start_time' => '18:00',
        'end_time' => '19:30',
        'capacity' => 10,
        'price_sen' => 12000,
        'is_open' => true,
    ]);

    Livewire::test(RunTraining::class)
        ->assertSet('period', now()->format('Y-m'))
        ->assertSet('offeringId', $julyOffering->id)     // defaults to a current-month timeslot
        ->set('period', $juneOffering->period)
        ->assertSet('offeringId', $juneOffering->id);    // switching month re-scopes the timeslot
});

it('persists and re-hydrates a per-student note', function () {
    [, $offering, , $key] = runTrainingContext();

    Livewire::test(RunTraining::class)
        ->set('offeringId', $offering->id)
        ->call('startSession')
        ->set('roster.'.$key.'.note', 'Great movement today')
        ->call('save');

    $this->assertDatabaseHas('attendances', [
        'student_id' => (int) substr($key, 1),
        'note' => 'Great movement today',
    ]);

    Livewire::test(RunTraining::class)
        ->assertSet('roster.'.$key.'.note', 'Great movement today');
});

it('does not create a session when the roster is empty', function () {
    runTrainingContext();

    $empty = Offering::create([
        'program_id' => Program::query()->firstOrFail()->id,
        'period' => now()->format('Y-m'),
        'schedule_type' => 'recurring',
        'weekday' => 1,
        'start_time' => '20:00',
        'end_time' => '21:00',
        'capacity' => 10,
        'price_sen' => 5000,
        'is_open' => true,
    ]);

    Livewire::test(RunTraining::class)
        ->set('offeringId', $empty->id)
        ->call('startSession')
        ->assertCount('roster', 0)
        ->call('save');

    expect(TrainingSession::where('offering_id', $empty->id)->count())->toBe(0);
});

it('renders the Run Training panel page for an authenticated coach', function () {
    [$coach] = runTrainingContext();

    $this->actingAs($coach)
        ->get('/app/run-training')
        ->assertOk();
});
