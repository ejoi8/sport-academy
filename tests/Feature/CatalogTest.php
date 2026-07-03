<?php

use App\Filament\Resources\Offerings\Pages\CreateOffering;
use App\Filament\Resources\Offerings\Pages\EditOffering;
use App\Filament\Resources\Offerings\Pages\ListOfferings;
use App\Filament\Resources\Offerings\RelationManagers\EnrollmentsRelationManager;
use App\Filament\Resources\Programs\Pages\CreateProgram;
use App\Filament\Resources\Programs\Pages\EditProgram;
use App\Filament\Resources\Programs\RelationManagers\OfferingsRelationManager;
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

it('creates a program, storing the RM prices as sen', function () {
    $sport = Sport::create(['name' => 'Football', 'is_active' => true]);

    Livewire::test(CreateProgram::class)
        ->fillForm([
            'sport_id' => $sport->id,
            'name' => 'Group Training',
            'base_price_sen' => '120.00',
            'walk_in_fee_sen' => '40.00',
            'default_sessions' => 6,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('programs', [
        'name' => 'Group Training',
        'base_price_sen' => 12000,
        'walk_in_fee_sen' => 4000,
        'default_sessions' => 6,
    ]);
});

it('creates a recurring timeslot offering', function () {
    $sport = Sport::create(['name' => 'Football', 'is_active' => true]);
    $program = Program::create(['sport_id' => $sport->id, 'name' => 'Group', 'base_price_sen' => 12000, 'walk_in_fee_sen' => 4000]);

    Livewire::test(CreateOffering::class)
        ->fillForm([
            'program_id' => $program->id,
            'period' => now()->format('Y-m'),
            'schedule_type' => 'recurring',
            'weekday' => 3,
            'start_time' => '18:00',
            'end_time' => '19:30',
            'capacity' => 12,
            'session_count' => 5,
            'price_sen' => '120.00',
            'is_open' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('offerings', [
        'program_id' => $program->id,
        'period' => now()->format('Y-m'),
        'weekday' => 3,
        'capacity' => 12,
        'session_count' => 5,
        'price_sen' => 12000,
        'is_open' => true,
    ]);
});

it('renders the timeslots list', function () {
    Livewire::test(ListOfferings::class)->assertOk();
});

it('creates a timeslot through the program relation manager, inheriting the program', function () {
    $sport = Sport::create(['name' => 'Football', 'is_active' => true]);
    $program = Program::create(['sport_id' => $sport->id, 'name' => 'Group', 'base_price_sen' => 12000, 'walk_in_fee_sen' => 4000]);

    Livewire::test(OfferingsRelationManager::class, [
        'ownerRecord' => $program,
        'pageClass' => EditProgram::class,
    ])
        ->assertOk()
        ->callAction(TestAction::make('create')->table(), [
            'period' => now()->format('Y-m'),
            'schedule_type' => 'recurring',
            'weekday' => 2,
            'start_time' => '17:00',
            'end_time' => '18:00',
            'capacity' => 10,
            'price_sen' => '90.00',
            'is_open' => true,
        ]);

    $this->assertDatabaseHas('offerings', [
        'program_id' => $program->id,
        'weekday' => 2,
        'capacity' => 10,
        'price_sen' => 9000,
    ]);
});

it('enrols a student through the offering relation manager', function () {
    $sport = Sport::create(['name' => 'Football', 'is_active' => true]);
    $program = Program::create(['sport_id' => $sport->id, 'name' => 'Group', 'base_price_sen' => 12000, 'walk_in_fee_sen' => 4000]);
    $offering = Offering::create([
        'program_id' => $program->id,
        'period' => now()->format('Y-m'),
        'schedule_type' => 'recurring',
        'weekday' => 3,
        'start_time' => '18:00',
        'end_time' => '19:30',
        'capacity' => 12,
        'session_count' => 5,
        'price_sen' => 12000,
        'is_open' => true,
    ]);
    $student = Student::create(['name' => 'Kid A', 'is_active' => true]);

    Livewire::test(EnrollmentsRelationManager::class, [
        'ownerRecord' => $offering,
        'pageClass' => EditOffering::class,
    ])
        ->assertOk()
        // sessions_included is left out so it snapshots from the offering's session_count.
        ->callAction(TestAction::make('create')->table(), [
            'student_id' => $student->id,
            'status' => 'active',
            'price_sen' => '120.00',
        ]);

    $this->assertDatabaseHas('enrollments', [
        'offering_id' => $offering->id,
        'student_id' => $student->id,
        'status' => 'active',
        'price_sen' => 12000,
        'sessions_included' => 5,
    ]);
});
