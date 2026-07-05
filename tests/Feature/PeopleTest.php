<?php

use App\Filament\Resources\Enrollments\Pages\CreateEnrollment;
use App\Filament\Resources\Enrollments\Pages\ListEnrollments;
use App\Filament\Resources\Students\Pages\CreateStudent;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\RelationManagers\AttendancesRelationManager;
use App\Filament\Resources\Students\RelationManagers\EnrollmentsRelationManager;
use App\Models\AssessmentScore;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Program;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\Sport;
use App\Models\Student;
use App\Models\TrainingSession;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $admin = User::factory()->create();
    $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    $this->actingAs($admin);
});

function anOffering(): Offering
{
    $sport = Sport::create(['name' => 'Football', 'is_active' => true]);
    $program = Program::create(['sport_id' => $sport->id, 'name' => 'Group', 'base_price_sen' => 12000, 'walk_in_fee_sen' => 4000, 'default_sessions' => 4]);

    return Offering::create([
        'program_id' => $program->id,
        'period' => now()->format('Y-m'),
        'schedule_type' => 'recurring',
        'weekday' => 3,
        'start_time' => '18:00',
        'end_time' => '19:30',
        'capacity' => 12,
        'session_count' => 4,
        'price_sen' => 12000,
        'is_open' => true,
    ]);
}

it('creates a student', function () {
    Livewire::test(CreateStudent::class)
        ->fillForm([
            'name' => 'Adam Rahman',
            'ic_number' => '150101010001',
            'gender' => 'male',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('students', ['name' => 'Adam Rahman', 'ic_number' => '150101010001']);
});

it('renders the students list', function () {
    Livewire::test(ListStudents::class)->assertOk();
});

it('creates an enrolment through the resource, storing RM price as sen', function () {
    $offering = anOffering();
    $student = Student::create(['name' => 'Kid A', 'is_active' => true]);

    Livewire::test(CreateEnrollment::class)
        ->fillForm([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => 'active',
            'price_sen' => '120.00',
            'sessions_included' => 4,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('enrollments', [
        'student_id' => $student->id,
        'offering_id' => $offering->id,
        'status' => 'active',
        'price_sen' => 12000,
        'sessions_included' => 4,
    ]);
});

it('renders the enrolments list', function () {
    Livewire::test(ListEnrollments::class)->assertOk();
});

it('enrols a student through their own relation manager', function () {
    $offering = anOffering();
    $student = Student::create(['name' => 'Kid B', 'is_active' => true]);

    Livewire::test(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => EditStudent::class,
    ])
        ->assertOk()
        ->callAction(TestAction::make('create')->table(), [
            'offering_id' => $offering->id,
            'status' => 'active',
            'price_sen' => '120.00',
            'sessions_included' => 4,
        ]);

    $this->assertDatabaseHas('enrollments', [
        'student_id' => $student->id,
        'offering_id' => $offering->id,
        'status' => 'active',
    ]);
});

/** Consume `count` credits on an enrolment via present attendances on its own sessions. */
function consumeCredits(Enrollment $enrolment, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        $session = TrainingSession::create([
            'offering_id' => $enrolment->offering_id,
            'session_date' => now()->startOfMonth()->addDays($i * 7)->toDateString(),
        ]);

        Attendance::create([
            'training_session_id' => $session->id,
            'student_id' => $enrolment->student_id,
            'enrollment_id' => $enrolment->id,
            'participant_type' => 'enrolled',
            'status' => 'present',
        ]);
    }
}

it('filters enrolments to those with credits remaining', function () {
    $done = Student::create(['name' => 'All done', 'is_active' => true]);
    $left = Student::create(['name' => 'Has credits', 'is_active' => true]);

    $eDone = Enrollment::create(['student_id' => $done->id, 'offering_id' => anOffering()->id, 'status' => 'active', 'price_sen' => 12000, 'sessions_included' => 4]);
    $eLeft = Enrollment::create(['student_id' => $left->id, 'offering_id' => anOffering()->id, 'status' => 'active', 'price_sen' => 12000, 'sessions_included' => 4]);

    consumeCredits($eDone, 4); // 4 / 4 — finished
    consumeCredits($eLeft, 1); // 1 / 4 — still has credits

    Livewire::test(ListEnrollments::class)
        ->filterTable('unfinished')
        ->assertCanSeeTableRecords([$eLeft])
        ->assertCanNotSeeTableRecords([$eDone]);
});

it('filters enrolments to those over-delivered past their paid sessions', function () {
    $normal = Student::create(['name' => 'Normal', 'is_active' => true]);
    $over = Student::create(['name' => 'Over delivered', 'is_active' => true]);

    $eNormal = Enrollment::create(['student_id' => $normal->id, 'offering_id' => anOffering()->id, 'status' => 'active', 'price_sen' => 12000, 'sessions_included' => 4]);
    $eOver = Enrollment::create(['student_id' => $over->id, 'offering_id' => anOffering()->id, 'status' => 'active', 'price_sen' => 12000, 'sessions_included' => 2]);

    consumeCredits($eNormal, 2); // 2 / 4 — within paid sessions
    consumeCredits($eOver, 3);  // 3 / 2 — over-delivered

    Livewire::test(ListEnrollments::class)
        ->filterTable('over_delivered')
        ->assertCanSeeTableRecords([$eOver])
        ->assertCanNotSeeTableRecords([$eNormal]);
});

it('filters enrolments by the number of sessions attended', function () {
    $two = Student::create(['name' => 'Attended two', 'is_active' => true]);
    $none = Student::create(['name' => 'Attended none', 'is_active' => true]);

    $eTwo = Enrollment::create(['student_id' => $two->id, 'offering_id' => anOffering()->id, 'status' => 'active', 'price_sen' => 12000, 'sessions_included' => 4]);
    $eNone = Enrollment::create(['student_id' => $none->id, 'offering_id' => anOffering()->id, 'status' => 'active', 'price_sen' => 12000, 'sessions_included' => 4]);

    consumeCredits($eTwo, 2); // two present attendances
    // $eNone: never turned up

    Livewire::test(ListEnrollments::class)
        ->filterTable('attended', ['operator' => 'gte', 'count' => 2])
        ->assertCanSeeTableRecords([$eTwo])
        ->assertCanNotSeeTableRecords([$eNone]);
});

it('lists a student\'s attended sessions in a read-only relation manager', function () {
    $student = Student::create(['name' => 'History Kid', 'is_active' => true]);
    $enrolment = Enrollment::create(['student_id' => $student->id, 'offering_id' => anOffering()->id, 'status' => 'active', 'price_sen' => 12000, 'sessions_included' => 4]);
    consumeCredits($enrolment, 2);

    Livewire::test(AttendancesRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => EditStudent::class,
    ])
        ->assertOk()
        ->assertCountTableRecords(2);
});

it('shows a session\'s assessment scores in the read-only view', function () {
    $sport = Sport::create(['name' => 'Football', 'is_active' => true]);
    $category = SkillCategory::create(['sport_id' => $sport->id, 'name' => 'Technical', 'sort_order' => 1]);
    $skill = Skill::create(['sport_id' => $sport->id, 'skill_category_id' => $category->id, 'name' => 'Passing', 'sort_order' => 1]);

    $student = Student::create(['name' => 'Scored Kid', 'is_active' => true]);
    $session = TrainingSession::create(['offering_id' => anOffering()->id, 'session_date' => now()->toDateString()]);
    $attendance = Attendance::create([
        'training_session_id' => $session->id,
        'student_id' => $student->id,
        'participant_type' => 'enrolled',
        'status' => 'present',
    ]);
    AssessmentScore::create(['attendance_id' => $attendance->id, 'skill_id' => $skill->id, 'score' => 4]);

    Livewire::test(AttendancesRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => EditStudent::class,
    ])
        ->mountAction(TestAction::make('view')->table($attendance))
        ->assertSuccessful();

    // The view's infolist reads the session's scores through this relation.
    expect($attendance->scores()->with('skill')->first()?->skill?->name)->toBe('Passing');
});
