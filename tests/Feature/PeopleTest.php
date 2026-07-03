<?php

use App\Filament\Resources\Enrollments\Pages\CreateEnrollment;
use App\Filament\Resources\Enrollments\Pages\ListEnrollments;
use App\Filament\Resources\Students\Pages\CreateStudent;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\RelationManagers\EnrollmentsRelationManager;
use App\Models\Offering;
use App\Models\Program;
use App\Models\Sport;
use App\Models\Student;
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
