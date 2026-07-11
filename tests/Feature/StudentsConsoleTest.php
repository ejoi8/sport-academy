<?php

use App\Filament\Pages\Students;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\BaselineSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->seed(BaselineSeeder::class);
    $this->coach = User::where('email', 'coach@coach.com')->firstOrFail();
    $this->actingAs($this->coach);
});

it('renders the students console for an authenticated coach', function () {
    $this->get(Students::getUrl())
        ->assertOk()
        ->assertSee('Students');
});

it('lists and drills into a student profile', function () {
    $student = Student::create(['name' => 'Lee Wei', 'is_active' => true]);

    Livewire::test(Students::class)
        ->assertSee('Lee Wei')
        ->call('openStudent', $student->id)
        ->assertSet('studentId', $student->id)
        ->assertSee('Lee Wei')
        ->assertSee('Attended')
        ->assertSee('Recent sessions');
});

it('requires a name when creating', function () {
    Livewire::test(Students::class)
        ->call('startCreate')
        ->call('saveStudent')
        ->assertHasErrors(['fName' => 'required']);
});

it('creates a student and drops into the new profile', function () {
    Livewire::test(Students::class)
        ->call('startCreate')
        ->set('fName', 'Amir Hafiz')
        ->set('fGuardianName', 'Zulkifli')
        ->set('fGuardianPhone', '0123456789')
        ->call('saveStudent')
        ->assertHasNoErrors()
        ->assertSet('editing', false);

    $student = Student::where('name', 'Amir Hafiz')->first();

    expect($student)->not->toBeNull()
        ->and($student->is_active)->toBeTrue()
        ->and($student->guardian_phone)->toBe('0123456789');
});

it('rejects a duplicate IC number', function () {
    Student::create(['name' => 'First', 'ic_number' => '990101015555', 'is_active' => true]);

    Livewire::test(Students::class)
        ->call('startCreate')
        ->set('fName', 'Second')
        ->set('fIc', '990101015555')
        ->call('saveStudent')
        ->assertHasErrors(['fIc']);
});

it('edits an existing student', function () {
    $student = Student::create(['name' => 'Old Name', 'is_active' => true]);

    Livewire::test(Students::class)
        ->set('studentId', $student->id)
        ->call('startEdit')
        ->assertSet('fName', 'Old Name')
        ->set('fName', 'New Name')
        ->call('saveStudent')
        ->assertHasNoErrors();

    expect($student->fresh()->name)->toBe('New Name');
});

it('toggles active status', function () {
    $student = Student::create(['name' => 'Toggler', 'is_active' => true]);

    Livewire::test(Students::class)
        ->set('studentId', $student->id)
        ->call('toggleActive');

    expect($student->fresh()->is_active)->toBeFalse();
});

it('drops a stale student id back to the list', function () {
    Livewire::test(Students::class, ['studentId' => 999999])
        ->assertSet('studentId', null);
});

it('pages the roster with load more', function () {
    foreach (range(1, 45) as $i) {
        Student::create(['name' => sprintf('Kid %02d', $i), 'is_active' => true]);
    }

    $component = Livewire::test(Students::class);

    expect($component->instance()->matchingCount())->toBe(45)
        ->and($component->instance()->results())->toHaveCount(40); // first page

    $component->call('loadMore');

    expect($component->instance()->results())->toHaveCount(45); // rest revealed
});

it('resets paging when the search changes', function () {
    foreach (range(1, 50) as $i) {
        Student::create(['name' => 'Alpha '.$i, 'is_active' => true]);
    }
    Student::create(['name' => 'Zenith Unique', 'is_active' => true]);

    $component = Livewire::test(Students::class)->call('loadMore'); // perPage now 80

    $component->set('search', 'Zenith');

    expect($component->instance()->results())->toHaveCount(1)
        ->and($component->get('perPage'))->toBe(40); // paging reset for the new search
});

/** Build a student enrolled in a baseline offering, with one recorded, scored session under it. */
function enrolmentWithSession(User $coach): array
{
    $offering = \App\Models\Offering::query()->firstOrFail();
    $skill = \App\Models\Skill::query()->firstOrFail();

    $student = Student::create(['name' => 'Reportee '.uniqid(), 'is_active' => true]);

    $enrollment = \App\Models\Enrollment::create([
        'student_id' => $student->id,
        'offering_id' => $offering->id,
        'status' => \App\Enums\EnrollmentStatus::Active,
        'price_sen' => 10000,
        'sessions_included' => 4,
    ]);

    $session = \App\Models\TrainingSession::create([
        'offering_id' => $offering->id,
        'session_date' => today()->toDateString(),
        'coach_id' => $coach->id,
        'created_by' => $coach->id,
    ]);

    $attendance = \App\Models\Attendance::create([
        'training_session_id' => $session->id,
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'participant_type' => \App\Enums\ParticipantType::Enrolled,
        'status' => \App\Enums\AttendanceStatus::Present,
        'coach_id' => $coach->id,
        'marked_by' => $coach->id,
    ]);

    \App\Models\AssessmentScore::create(['attendance_id' => $attendance->id, 'skill_id' => $skill->id, 'score' => 4]);

    return [$student, $enrollment, $skill];
}

it('opens a per-enrolment session report with its sessions and scores', function () {
    [$student, $enrollment, $skill] = enrolmentWithSession($this->coach);

    Livewire::test(Students::class)
        ->call('openEnrollment', $enrollment->id)
        ->assertSet('enrollmentId', $enrollment->id)
        ->assertSet('studentId', $student->id)
        ->assertSee('Skill averages')
        ->assertSee($skill->name)
        ->assertSee('Sessions')
        ->call('backToStudent')
        ->assertSet('enrollmentId', null)
        ->assertSet('studentId', $student->id);
});

it('resolves a deep-linked enrolment to its student', function () {
    [$student, $enrollment] = enrolmentWithSession($this->coach);

    Livewire::test(Students::class, ['enrollmentId' => $enrollment->id])
        ->assertSet('studentId', $student->id);
});
