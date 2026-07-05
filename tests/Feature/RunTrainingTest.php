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
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

/**
 * @return array{0: User, 1: Offering, 2: Skill, 3: string, 4: string}
 */
function runTrainingContext(): array
{
    test()->seed(BaselineSeeder::class);

    $coach = User::where('email', 'coach@academy.test')->firstOrFail();
    $offering = Offering::where('is_open', true)->has('enrollments')->firstOrFail();
    $skill = Skill::query()->orderBy('sort_order')->firstOrFail();
    $student = $offering->enrollments()->with('student')->first()->student;

    // A date the offering actually runs — its first weekday occurrence that month, so it shows up
    // in that day's session list.
    $date = Carbon::parse($offering->period.'-01')->startOfMonth();
    while ($date->dayOfWeekIso !== $offering->weekday) {
        $date->addDay();
    }

    test()->actingAs($coach);

    return [$coach, $offering, $skill, 's'.$student->id, $date->toDateString()];
}

it('records attendance and rubric scores for the enrolled roster', function () {
    [$coach, $offering, $skill, $key, $date] = runTrainingContext();

    $enrolledCount = Enrollment::where('offering_id', $offering->id)
        ->whereIn('status', ['active', 'pending', 'overdue'])
        ->count();

    Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->set('offeringId', $offering->id)
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

it('auto-loads the enrolled prospects (in memory only) when a timeslot is opened', function () {
    [, $offering, , $key, $date] = runTrainingContext();

    Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->set('offeringId', $offering->id)
        ->assertSet('roster.'.$key.'.type', 'enrolled')  // prospects show immediately
        ->assertSet('savedSessionExists', false);         // but nothing is recorded yet

    // Just viewing the roster writes nothing to the database.
    expect(TrainingSession::count())->toBe(0)
        ->and(Attendance::count())->toBe(0);
});

it('tapping the selected score pill again clears it, and saving deletes the stored score', function () {
    [, $offering, $skill, $key, $date] = runTrainingContext();

    $component = Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->set('offeringId', $offering->id)
        ->call('setScore', $key, $skill->id, 3)
        ->call('save');

    $this->assertDatabaseHas('assessment_scores', ['skill_id' => $skill->id, 'score' => 3]);

    $component->call('setScore', $key, $skill->id, 3) // toggle off
        ->assertSet('roster.'.$key.'.scores.'.$skill->id, null)
        ->call('save');

    $this->assertDatabaseMissing('assessment_scores', ['skill_id' => $skill->id, 'score' => 3]);
});

it('does not record a fee or scores for a walk-in marked absent', function () {
    [, $offering, $skill, , $date] = runTrainingContext();

    Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->set('offeringId', $offering->id)
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
    [, $offering, , , $date] = runTrainingContext();

    $before = Student::count();

    Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->set('offeringId', $offering->id)
        ->call('startAdd')
        ->set('newName', 'Twice Saver')
        ->call('addNewWalkIn')
        ->call('save')
        ->call('save'); // second save must not create another student

    expect(Student::where('name', 'Twice Saver')->count())->toBe(1)
        ->and(Student::count())->toBe($before + 1);
});

it('hydrates a previously saved session when the same timeslot and date is re-opened', function () {
    [, $offering, $skill, $key, $date] = runTrainingContext();

    Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->set('offeringId', $offering->id)
        ->call('setStatus', $key, 'late')
        ->call('setScore', $key, $skill->id, 2)
        ->call('save');

    // Re-opening the same date + timeslot must reflect the saved state.
    Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->set('offeringId', $offering->id)
        ->assertSet('savedSessionExists', true)
        ->assertSet('roster.'.$key.'.status', 'late')
        ->assertSet('roster.'.$key.'.scores.'.$skill->id, 2);
});

it('deletes a saved session and cascades its attendance and scores', function () {
    [, $offering, $skill, $key, $date] = runTrainingContext();

    Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->set('offeringId', $offering->id)
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
    [, $offering, $skill, $key, $date] = runTrainingContext();

    Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->set('offeringId', $offering->id)
        ->call('setScore', $key, $skill->id, 3)
        ->call('save');

    $savedCount = Attendance::count();
    expect($savedCount)->toBeGreaterThan(1);

    // Re-open (hydrated), remove one participant, save again.
    Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->set('offeringId', $offering->id)
        ->assertSet('roster.'.$key.'.scores.'.$skill->id, 3)
        ->call('removeRow', $key)
        ->call('save');

    expect(Attendance::where('student_id', (int) substr($key, 1))->count())->toBe(0)
        ->and(Attendance::count())->toBe($savedCount - 1);
});

it('defaults each student to the head coach and persists a per-student coach change', function () {
    [$coach, $offering, , $key, $date] = runTrainingContext();

    $other = User::factory()->create(['name' => 'Coach Two']);
    $studentId = (int) substr($key, 1);

    Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->set('offeringId', $offering->id)
        ->assertSet('headCoachId', $coach->id)
        ->assertSet('roster.'.$key.'.coach_id', $coach->id) // defaulted to the timeslot head coach
        ->set('roster.'.$key.'.coach_id', $other->id)       // reassign just this student
        ->call('save');

    $this->assertDatabaseHas('attendances', [
        'student_id' => $studentId,
        'coach_id' => $other->id,
    ]);
});

it("keeps the recorded session's coach when the slot default changes", function () {
    [$coach, $offering, , , $date] = runTrainingContext();

    Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->set('offeringId', $offering->id)
        ->call('save');

    $newDefault = User::factory()->create(['name' => 'New Default Coach']);
    $offering->update(['default_coach_id' => $newDefault->id]);

    // Re-opening the saved session must still show the coach who actually led it, not the
    // slot's new default.
    Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->set('offeringId', $offering->id)
        ->assertSet('headCoachId', $coach->id);
});

it('bulk-assigns every player to one coach', function () {
    [, $offering, , $key, $date] = runTrainingContext();

    $other = User::factory()->create(['name' => 'Coach Bulk']);
    $studentId = (int) substr($key, 1);

    Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->set('offeringId', $offering->id)
        ->set('bulkCoachId', $other->id)
        ->call('assignAll')
        ->assertSet('roster.'.$key.'.coach_id', $other->id)
        ->call('save');

    $this->assertDatabaseHas('attendances', ['student_id' => $studentId, 'coach_id' => $other->id]);
});

it('adds a coach on the fly who becomes assignable', function () {
    [, $offering, , , $date] = runTrainingContext();

    Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->set('offeringId', $offering->id)
        ->call('startAddCoach')
        ->set('newCoachName', 'Coach Fresh')
        ->call('saveCoach')
        ->assertSet('addingCoach', false);

    $this->assertDatabaseHas('users', ['name' => 'Coach Fresh']);
});

it('surfaces a session recorded on an off-schedule date', function () {
    [, $offering, , $key] = runTrainingContext();

    // A Thursday has no scheduled timeslot (BaselineSeeder has Wed + Sat only) — a legacy
    // off-schedule recording on this date must still surface, not become unreachable.
    $thursday = Carbon::parse($offering->period.'-01')->startOfMonth();
    while ($thursday->dayOfWeekIso !== 4) {
        $thursday->addDay();
    }

    $session = TrainingSession::create([
        'offering_id' => $offering->id,
        'session_date' => $thursday->toDateString(),
        'coach_id' => $offering->default_coach_id,
    ]);

    Attendance::create([
        'training_session_id' => $session->id,
        'student_id' => (int) substr($key, 1),
        'enrollment_id' => null,
        'participant_type' => 'enrolled',
        'status' => 'present',
    ]);

    $component = Livewire::test(RunTraining::class)
        ->set('date', $thursday->toDateString());

    expect(collect($component->instance()->sessionsOnDate)->pluck('id'))->toContain($offering->id);

    // It's the only session on that date, so it auto-expands, already marked as saved.
    $component->assertSet('offeringId', $offering->id)
        ->assertSet('savedSessionExists', true);
});

it('runs a new session on an off-schedule date by choosing a program and time', function () {
    [, $offering] = runTrainingContext();
    $program = Program::firstOrFail();

    // A Thursday has no scheduled timeslot (BaselineSeeder has Wed + Sat only).
    $thursday = Carbon::parse($offering->period.'-01')->startOfMonth();
    while ($thursday->dayOfWeekIso !== 4) {
        $thursday->addDay();
    }

    Livewire::test(RunTraining::class)
        ->set('date', $thursday->toDateString())
        ->assertSet('offeringId', null)          // off-schedule -> nothing auto-selected
        ->call('toggleNewSession')                 // choose "＋ Create new session"
        ->assertSet('creatingSession', true)
        ->set('adHocProgramId', $program->id)    // name the program + time
        ->set('adHocTime', '18:00')
        ->call('startAdd')
        ->set('newName', 'Ad Hoc Kid')
        ->call('addNewWalkIn')
        ->call('save')
        ->assertSet('creatingSession', false);

    // The one-off timeslot is written only on Save, together with its session.
    $adHoc = Offering::where('schedule_type', 'one_off')
        ->whereDate('specific_date', $thursday->toDateString())
        ->firstOrFail();

    expect($adHoc->program_id)->toBe($program->id);
    $this->assertDatabaseHas('training_sessions', [
        'offering_id' => $adHoc->id,
        'session_date' => $thursday->toDateString(),
    ]);
});

it('can add a new session on a date that already has a timeslot', function () {
    [, $offering, , , $date] = runTrainingContext(); // group runs on this date
    $program = Program::firstOrFail();

    Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->assertSet('offeringId', $offering->id) // the day's lone class auto-expands...
        ->call('toggleNewSession')                   // ...yet a fresh session is still reachable
        ->assertSet('creatingSession', true)
        ->set('adHocProgramId', $program->id)
        ->set('adHocTime', '20:00')
        ->call('startAdd')
        ->set('newName', 'Extra Kid')
        ->call('addNewWalkIn')
        ->call('save');

    // A second, one-off timeslot now exists on that date alongside the recurring one.
    expect(Offering::whereDate('specific_date', $date)->where('schedule_type', 'one_off')->count())->toBe(1);
});

it('warns about an overlapping timeslot but still allows the new session', function () {
    [, $offering, , , $date] = runTrainingContext(); // the group runs on this date + time
    $program = Program::firstOrFail();
    $time = substr((string) $offering->start_time, 0, 5);

    $component = Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->call('toggleNewSession')
        ->set('adHocProgramId', $program->id)
        ->set('adHocTime', $time);

    // The existing timeslot at that time is surfaced as a soft overlap warning...
    expect($component->instance()->overlappingTimeslots)->not->toBeEmpty();

    // ...but recording is still permitted — it may be a deliberate second team.
    $component->call('startAdd')
        ->set('newName', 'Team B Kid')
        ->call('addNewWalkIn')
        ->call('save')
        ->assertSet('creatingSession', false);

    expect(Offering::whereDate('specific_date', $date)->where('schedule_type', 'one_off')->count())->toBe(1);
});

it('flags a partial time-range overlap, not just an identical start time', function () {
    [, $offering, , , $date] = runTrainingContext(); // group runs 18:00–19:30 on this date
    $program = Program::firstOrFail();

    // A start time inside the existing range but different from its start — the old same-start-only
    // check would have missed this; the range check should catch it.
    $inside = Carbon::parse($offering->start_time)->addMinutes(30)->format('H:i');

    $component = Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->call('toggleNewSession')
        ->set('adHocProgramId', $program->id)
        ->set('adHocTime', $inside);

    expect($component->instance()->overlappingTimeslots)->not->toBeEmpty();

    // A slot that starts after the existing one ends is not a conflict.
    $component->set('adHocTime', Carbon::parse($offering->end_time)->addMinutes(30)->format('H:i'));
    expect($component->instance()->overlappingTimeslots)->toBeEmpty();
});

it('writes nothing when a staged new session is abandoned', function () {
    runTrainingContext();
    $program = Program::firstOrFail();
    $before = Offering::count();

    Livewire::test(RunTraining::class)
        ->call('toggleNewSession')
        ->set('adHocProgramId', $program->id)
        ->set('adHocTime', '18:00');
    // never saved

    expect(Offering::count())->toBe($before);
});

it('removes the one-off timeslot when its ad-hoc session is deleted', function () {
    [, $offering] = runTrainingContext();
    $program = Program::firstOrFail();

    $thursday = Carbon::parse($offering->period.'-01')->startOfMonth();
    while ($thursday->dayOfWeekIso !== 4) {
        $thursday->addDay();
    }

    $component = Livewire::test(RunTraining::class)
        ->set('date', $thursday->toDateString())
        ->call('toggleNewSession')
        ->set('adHocProgramId', $program->id)
        ->set('adHocTime', '18:00')
        ->call('startAdd')
        ->set('newName', 'Ad Hoc Kid')
        ->call('addNewWalkIn')
        ->call('save');

    $adHocId = Offering::where('schedule_type', 'one_off')
        ->whereDate('specific_date', $thursday->toDateString())
        ->value('id');
    expect($adHocId)->not->toBeNull();

    $component->call('deleteSession');

    // The session and its one-off timeslot are both gone — no orphan is left behind.
    expect(Offering::find($adHocId))->toBeNull();
    $this->assertDatabaseMissing('training_sessions', ['offering_id' => $adHocId]);
});

it('re-prices walk-ins added before the program was chosen', function () {
    runTrainingContext();
    $program = Program::where('walk_in_fee_sen', '>', 0)->firstOrFail();

    $component = Livewire::test(RunTraining::class)
        ->call('toggleNewSession')             // walk-in fee starts at 0 (no program yet)
        ->call('startAdd')
        ->set('newName', 'Early Bird')
        ->call('addNewWalkIn');

    expect($component->get('roster')['n1']['fee_sen'])->toBe(0);

    $component->set('adHocProgramId', $program->id);   // choosing the program re-prices

    expect($component->get('roster')['n1']['fee_sen'])->toBe($program->walk_in_fee_sen);
});

it('lists the sessions on a date and expands one to record', function () {
    [, $offering, , $key, $date] = runTrainingContext(); // group runs on this date

    $component = Livewire::test(RunTraining::class)
        ->set('date', $date);

    // The day's sessions are listed as cards...
    expect(collect($component->instance()->sessionsOnDate)->pluck('id'))->toContain($offering->id);

    // ...and, being the only session that day, it auto-expands with its enrolled roster loaded.
    $component->assertSet('offeringId', $offering->id)
        ->assertSet('roster.'.$key.'.type', 'enrolled');

    // Toggling it collapses it...
    $component->call('toggleSession', $offering->id)
        ->assertSet('offeringId', null);

    // ...and toggling it again re-opens it.
    $component->call('toggleSession', $offering->id)
        ->assertSet('offeringId', $offering->id)
        ->assertSet('roster.'.$key.'.type', 'enrolled');
});

it('auto-expands the only session on a date', function () {
    [, $offering, , , $date] = runTrainingContext(); // group runs on this date, alone

    Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->assertSet('offeringId', $offering->id);
});

it('still lists a class closed for registration', function () {
    [, $offering, , , $date] = runTrainingContext();

    $offering->update(['is_open' => false]);

    $component = Livewire::test(RunTraining::class)
        ->set('date', $date);

    // is_open gates new registrations, not whether the class can still be delivered/recorded.
    expect(collect($component->instance()->sessionsOnDate)->pluck('id'))->toContain($offering->id);
});

it('persists and re-hydrates a per-student note', function () {
    [, $offering, , $key, $date] = runTrainingContext();

    Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->set('offeringId', $offering->id)
        ->set('roster.'.$key.'.note', 'Great movement today')
        ->call('save');

    $this->assertDatabaseHas('attendances', [
        'student_id' => (int) substr($key, 1),
        'note' => 'Great movement today',
    ]);

    Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->set('offeringId', $offering->id)
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
        ->assertCount('roster', 0)
        ->call('save');

    expect(TrainingSession::where('offering_id', $empty->id)->count())->toBe(0);
});

it('shows carry-over credits from another enrolment', function () {
    [, $offering, , $key, $date] = runTrainingContext();

    $studentId = (int) substr($key, 1);
    $otherOffering = Offering::where('id', '!=', $offering->id)->firstOrFail();

    Enrollment::create([
        'student_id' => $studentId,
        'offering_id' => $otherOffering->id,
        'status' => 'active',
        'price_sen' => 28000,
        'sessions_included' => 4,
    ]);

    Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->set('offeringId', $offering->id)
        ->assertSet('roster.'.$key.'.carry_over', 4);
});

it('refreshes credit counters and reports over-delivery on save', function () {
    [, $offering, , $key, $date] = runTrainingContext();

    $studentId = (int) substr($key, 1);
    $enrollment = Enrollment::where('offering_id', $offering->id)->where('student_id', $studentId)->firstOrFail();
    $enrollment->update(['sessions_included' => 1]);

    $component = Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->set('offeringId', $offering->id)
        ->call('save')
        ->assertSet('roster.'.$key.'.credits_used', 1);

    // The next week's occurrence of the same recurring class — a second, separate session.
    $nextWeek = Carbon::parse($date)->addDays(7)->toDateString();

    $component
        ->set('date', $nextWeek)
        ->set('offeringId', $offering->id)
        ->call('save')
        ->assertSet('roster.'.$key.'.credits_used', 2)
        ->assertNotified(
            Notification::make()->success()->title('Training session saved')->body('1 player is now over their paid sessions.'),
        );

    $enrollment->refresh();
    expect($enrollment->creditsUsed())->toBe(2);
});

it('offers only a paying walk-in for a different-program session', function () {
    [, $offering, , $key] = runTrainingContext(); // Group, with a fresh (live-credit) enrolment
    $studentId = (int) substr($key, 1);
    $studentName = Student::findOrFail($studentId)->name;

    // A different program's offering — same seeder, no shared credits.
    $oneToOne = Offering::where('id', '!=', $offering->id)->firstOrFail();
    expect($oneToOne->program_id)->not->toBe($offering->program_id);

    $saturday = Carbon::parse($oneToOne->period.'-01')->startOfMonth();
    while ($saturday->dayOfWeekIso !== $oneToOne->weekday) {
        $saturday->addDay();
    }

    $component = Livewire::test(RunTraining::class)
        ->set('date', $saturday->toDateString())
        ->set('offeringId', $oneToOne->id)
        ->call('startAdd')
        ->set('search', $studentName);

    $result = collect($component->instance()->results)->firstWhere('id', $studentId);
    expect($result['type'])->toBe('walk_in');

    // Nothing blocks the add even though this student has no credits for THIS program.
    $component->call('addExisting', $studentId)
        ->assertSet('roster.s'.$studentId.'.type', 'walk_in')
        ->assertSet('roster.s'.$studentId.'.fee_sen', $oneToOne->program->walk_in_fee_sen)
        ->call('save');

    $this->assertDatabaseHas('attendances', [
        'student_id' => $studentId,
        'participant_type' => 'walk_in',
        'enrollment_id' => null,
        'walk_in_fee_sen' => $oneToOne->program->walk_in_fee_sen,
    ]);
});

it('still offers a same-program make-up', function () {
    [, $offering, , $key] = runTrainingContext(); // Group, with a fresh (live-credit) enrolment
    $studentId = (int) substr($key, 1);
    $studentName = Student::findOrFail($studentId)->name;
    $groupEnrolmentId = Enrollment::where('offering_id', $offering->id)->where('student_id', $studentId)->value('id');

    // A second Group offering — same program, different weekday/time, same period.
    $secondGroup = Offering::create([
        'program_id' => $offering->program_id,
        'period' => $offering->period,
        'schedule_type' => 'recurring',
        'weekday' => 5, // Friday — different from the main Wed slot
        'start_time' => '19:00',
        'end_time' => '20:00',
        'capacity' => 10,
        'session_count' => 4,
        'price_sen' => $offering->price_sen,
        'is_open' => true,
    ]);

    $friday = Carbon::parse($secondGroup->period.'-01')->startOfMonth();
    while ($friday->dayOfWeekIso !== $secondGroup->weekday) {
        $friday->addDay();
    }

    $component = Livewire::test(RunTraining::class)
        ->set('date', $friday->toDateString())
        ->set('offeringId', $secondGroup->id)
        ->call('startAdd')
        ->set('search', $studentName);

    $result = collect($component->instance()->results)->firstWhere('id', $studentId);
    expect($result['type'])->toBe('make_up');

    $component->call('addExisting', $studentId)
        ->assertSet('roster.s'.$studentId.'.type', 'make_up')
        ->call('save');

    $this->assertDatabaseHas('attendances', [
        'student_id' => $studentId,
        'participant_type' => 'make_up',
        'enrollment_id' => $groupEnrolmentId,
    ]);
});

it('coach can force walk-in even when a make-up is available', function () {
    [, $offering, , $key] = runTrainingContext(); // Group, with a fresh (live-credit) enrolment
    $studentId = (int) substr($key, 1);
    $studentName = Student::findOrFail($studentId)->name;

    // Same setup as the same-program make-up case above.
    $secondGroup = Offering::create([
        'program_id' => $offering->program_id,
        'period' => $offering->period,
        'schedule_type' => 'recurring',
        'weekday' => 5,
        'start_time' => '19:00',
        'end_time' => '20:00',
        'capacity' => 10,
        'session_count' => 4,
        'price_sen' => $offering->price_sen,
        'is_open' => true,
    ]);

    $friday = Carbon::parse($secondGroup->period.'-01')->startOfMonth();
    while ($friday->dayOfWeekIso !== $secondGroup->weekday) {
        $friday->addDay();
    }

    Livewire::test(RunTraining::class)
        ->set('date', $friday->toDateString())
        ->set('offeringId', $secondGroup->id)
        ->call('startAdd')
        ->set('search', $studentName)
        ->call('addExisting', $studentId, true) // the escape hatch: force a walk-in
        ->assertSet('roster.s'.$studentId.'.type', 'walk_in')
        ->assertSet('roster.s'.$studentId.'.fee_sen', $secondGroup->program->walk_in_fee_sen)
        ->call('save');

    $this->assertDatabaseHas('attendances', [
        'student_id' => $studentId,
        'participant_type' => 'walk_in',
        'enrollment_id' => null,
        'walk_in_fee_sen' => $secondGroup->program->walk_in_fee_sen,
    ]);
});

it('renders the Run Training panel page for an authenticated coach', function () {
    [$coach] = runTrainingContext();

    $this->actingAs($coach)
        ->get('/app/run-training')
        ->assertOk()
        ->assertSee('How credits work');
});

it('deep-links a date and session from the query string', function () {
    [, $offering, , $key, $date] = runTrainingContext();

    Livewire::withQueryParams(['date' => $date, 'session' => $offering->id])
        ->test(RunTraining::class)
        ->assertSet('date', $date)
        ->assertSet('offeringId', $offering->id)
        ->assertSet('roster.'.$key.'.type', 'enrolled');
});

it('drops a query-string session that does not run on the date', function () {
    [, $offering] = runTrainingContext();

    // A Thursday has no scheduled timeslot (BaselineSeeder has Wed + Sat only) — a `session` id
    // that does not actually run there must be silently dropped, not force-opened.
    $thursday = Carbon::parse($offering->period.'-01')->startOfMonth();
    while ($thursday->dayOfWeekIso !== 4) {
        $thursday->addDay();
    }

    Livewire::withQueryParams(['date' => $thursday->toDateString(), 'session' => $offering->id])
        ->test(RunTraining::class)
        ->assertSet('offeringId', null);
});

it('falls back to today for an unparseable date', function () {
    runTrainingContext();

    Livewire::withQueryParams(['date' => 'banana'])
        ->test(RunTraining::class)
        ->assertSet('date', today()->toDateString())
        ->assertOk();
});

it('lets a URL session take precedence over lone-session auto-expand', function () {
    [, $offering, , , $date] = runTrainingContext(); // group runs alone on this date

    // A second same-day, same-period offering at a different time — so the day no longer has
    // exactly one session and auto-expand would not fire.
    $second = Offering::create([
        'program_id' => $offering->program_id,
        'period' => $offering->period,
        'schedule_type' => 'recurring',
        'weekday' => $offering->weekday,
        'start_time' => '21:00',
        'end_time' => '22:00',
        'capacity' => 10,
        'session_count' => 4,
        'price_sen' => $offering->price_sen,
        'is_open' => true,
    ]);

    Livewire::withQueryParams(['date' => $date, 'session' => $second->id])
        ->test(RunTraining::class)
        ->assertSet('offeringId', $second->id);
});

it("returns an offering's nearest occurrence", function () {
    [, $offering] = runTrainingContext();

    $nearest = $offering->nearestOccurrence();
    expect($nearest)->not->toBeNull()
        ->and($nearest->dayOfWeekIso)->toBe($offering->weekday);

    $oneOff = Offering::create([
        'program_id' => $offering->program_id,
        'period' => $offering->period,
        'schedule_type' => 'one_off',
        'specific_date' => '2026-03-15',
        'start_time' => '18:00',
        'capacity' => 10,
        'price_sen' => $offering->price_sen,
        'is_open' => true,
    ]);

    expect($oneOff->nearestOccurrence()->toDateString())->toBe('2026-03-15');
});
