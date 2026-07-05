<?php

use App\Filament\Pages\RunTraining;
use App\Filament\Resources\Enrollments\Pages\EditEnrollment;
use App\Filament\Resources\Offerings\Pages\EditOffering;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Program;
use App\Models\Sport;
use App\Models\Student;
use App\Models\TrainingSession;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $admin = User::factory()->create();
    $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    $this->actingAs($admin);
});

function guardrailOffering(array $overrides = []): Offering
{
    $sport = Sport::create(['name' => 'Football', 'is_active' => true]);
    $program = Program::create([
        'sport_id' => $sport->id,
        'name' => 'Group',
        'base_price_sen' => 12000,
        'walk_in_fee_sen' => 4000,
        'default_sessions' => 4,
    ]);

    return Offering::create(array_merge([
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
    ], $overrides));
}

function guardrailEnrollment(array $overrides = []): Enrollment
{
    $student = $overrides['student'] ?? Student::create(['name' => fake()->name(), 'is_active' => true]);
    $offering = $overrides['offering'] ?? guardrailOffering();

    return Enrollment::create([
        'student_id' => $student->id,
        'offering_id' => $offering->id,
        'status' => 'active',
        'price_sen' => 12000,
        'sessions_included' => 4,
        'credits_expire_at' => null,
        ...collect($overrides)->except(['student', 'offering'])->all(),
    ]);
}

function consumeGuardrailCredit(Enrollment $enrollment): Attendance
{
    $session = TrainingSession::create([
        'offering_id' => $enrollment->offering_id,
        'session_date' => now()->toDateString(),
    ]);

    return Attendance::create([
        'training_session_id' => $session->id,
        'student_id' => $enrollment->student_id,
        'enrollment_id' => $enrollment->id,
        'participant_type' => 'enrolled',
        'status' => 'present',
    ]);
}

it('locks enrolment snapshot fields once credits have been consumed', function () {
    $locked = guardrailEnrollment();
    consumeGuardrailCredit($locked);

    $editable = guardrailEnrollment();

    Livewire::test(EditEnrollment::class, ['record' => $locked->getRouteKey()])
        ->assertFormFieldDisabled('price_sen')
        ->assertFormFieldDisabled('sessions_included');

    Livewire::test(EditEnrollment::class, ['record' => $editable->getRouteKey()])
        ->assertFormFieldEnabled('price_sen')
        ->assertFormFieldEnabled('sessions_included');
});

it('blocks deleting an enrolment with recorded sessions in the policy and edit action', function () {
    $enrollment = guardrailEnrollment();
    consumeGuardrailCredit($enrollment);

    expect($this->app['Illuminate\Contracts\Auth\Access\Gate']->inspect('delete', $enrollment)->allowed())->toBeFalse();

    Livewire::test(EditEnrollment::class, ['record' => $enrollment->getRouteKey()])
        ->assertActionHidden('delete');

    expect(Enrollment::withTrashed()->find($enrollment->id))->not->toBeNull();
});

it('allows deleting a clean enrolment', function () {
    $enrollment = guardrailEnrollment();

    expect($this->app['Illuminate\Contracts\Auth\Access\Gate']->inspect('delete', $enrollment)->allowed())->toBeTrue();

    Livewire::test(EditEnrollment::class, ['record' => $enrollment->getRouteKey()])
        ->callAction('delete');

    expect(Enrollment::withTrashed()->find($enrollment->id)?->trashed())->toBeTrue();
});

it('blocks deleting a timeslot with enrolments or sessions, but allows a clean one', function () {
    $offeringWithEnrollment = guardrailOffering();
    guardrailEnrollment(['offering' => $offeringWithEnrollment]);

    $offeringWithSession = guardrailOffering();
    TrainingSession::create([
        'offering_id' => $offeringWithSession->id,
        'session_date' => now()->toDateString(),
    ]);

    $cleanOffering = guardrailOffering(['period' => now()->addMonth()->format('Y-m'), 'start_time' => '20:00']);

    expect($this->app['Illuminate\Contracts\Auth\Access\Gate']->inspect('delete', $offeringWithEnrollment)->allowed())->toBeFalse()
        ->and($this->app['Illuminate\Contracts\Auth\Access\Gate']->inspect('delete', $offeringWithSession)->allowed())->toBeFalse()
        ->and($this->app['Illuminate\Contracts\Auth\Access\Gate']->inspect('delete', $cleanOffering)->allowed())->toBeTrue();

    Livewire::test(EditOffering::class, ['record' => $offeringWithEnrollment->getRouteKey()])
        ->assertActionHidden('delete');

    Livewire::test(EditOffering::class, ['record' => $cleanOffering->getRouteKey()])
        ->callAction('delete');

    expect(Offering::find($offeringWithEnrollment->id))->not->toBeNull()
        ->and(Offering::find($cleanOffering->id))->toBeNull();
});

it('blocks deleting a student with recorded sessions, but allows a clean one', function () {
    $historyStudent = Student::create(['name' => 'History Kid', 'is_active' => true]);
    $historyEnrollment = guardrailEnrollment(['student' => $historyStudent]);
    consumeGuardrailCredit($historyEnrollment);

    $cleanStudent = Student::create(['name' => 'Clean Kid', 'is_active' => true]);

    expect($this->app['Illuminate\Contracts\Auth\Access\Gate']->inspect('delete', $historyStudent)->allowed())->toBeFalse()
        ->and($this->app['Illuminate\Contracts\Auth\Access\Gate']->inspect('delete', $cleanStudent)->allowed())->toBeTrue();

    Livewire::test(EditStudent::class, ['record' => $historyStudent->getRouteKey()])
        ->assertActionHidden('delete');

    Livewire::test(EditStudent::class, ['record' => $cleanStudent->getRouteKey()])
        ->callAction('delete');

    expect(Student::withTrashed()->find($historyStudent->id)?->trashed())->toBeFalse()
        ->and(Student::withTrashed()->find($cleanStudent->id)?->trashed())->toBeTrue();
});

it('deletes an ad-hoc one-off timeslot when its saved session is deleted', function () {
    $sport = Sport::create(['name' => 'Football', 'is_active' => true]);
    $program = Program::create([
        'sport_id' => $sport->id,
        'name' => 'Clinic',
        'base_price_sen' => 15000,
        'walk_in_fee_sen' => 5000,
        'default_sessions' => 1,
    ]);

    $coach = User::factory()->create();
    $coach->assignRole(Role::firstOrCreate(['name' => 'coach', 'guard_name' => 'web']));
    $this->actingAs($coach);

    $date = Carbon::parse('2026-07-08')->toDateString();

    $component = Livewire::test(RunTraining::class)
        ->set('date', $date)
        ->call('toggleNewSession')
        ->set('adHocProgramId', $program->id)
        ->set('adHocTime', '18:00')
        ->set('adHocEndTime', '19:00')
        ->call('startAdd')
        ->set('newName', 'Ad Hoc Player')
        ->call('addNewWalkIn')
        ->call('save');

    $offeringId = $component->get('offeringId');

    expect($offeringId)->not->toBeNull()
        ->and(Offering::find($offeringId))->not->toBeNull();

    $component->call('deleteSession');

    expect(TrainingSession::where('offering_id', $offeringId)->exists())->toBeFalse()
        ->and(Offering::find($offeringId))->toBeNull();
});

it('writes enrolment activity rows when an enrolment is created and updated', function () {
    $enrollment = guardrailEnrollment();

    $enrollment->update(['status' => 'overdue']);

    expect(Activity::query()->where('subject_type', Enrollment::class)->count())->toBe(2)
        ->and(Activity::query()->where('subject_type', Enrollment::class)->where('description', 'created')->exists())->toBeTrue()
        ->and(Activity::query()->where('subject_type', Enrollment::class)->where('description', 'updated')->exists())->toBeTrue();
});
