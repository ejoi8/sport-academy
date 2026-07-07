<?php

use App\Filament\Resources\Offerings\Pages\CreateOffering;
use App\Filament\Resources\Offerings\Pages\EditOffering;
use App\Filament\Resources\Offerings\Pages\ListOfferings;
use App\Filament\Resources\Offerings\RelationManagers\EnrollmentsRelationManager;
use App\Filament\Resources\Programs\Pages\CreateProgram;
use App\Filament\Resources\Programs\Pages\EditProgram;
use App\Filament\Resources\Programs\RelationManagers\OfferingsRelationManager;
use App\Models\Enrollment;
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

it('creates a one-off clinic offering with a start and end time', function () {
    $sport = Sport::create(['name' => 'Football', 'is_active' => true]);
    $program = Program::create(['sport_id' => $sport->id, 'name' => 'Football Clinic', 'base_price_sen' => 9000, 'walk_in_fee_sen' => 3000, 'default_sessions' => 1]);

    Livewire::test(CreateOffering::class)
        ->fillForm([
            'program_id' => $program->id,
            'period' => now()->format('Y-m'),
            'schedule_type' => 'one_off',
            'specific_date' => now()->startOfMonth()->addDays(10)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'capacity' => 20,
            'session_count' => 1,
            'price_sen' => '90.00',
            'is_open' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $offering = Offering::where('program_id', $program->id)->where('schedule_type', 'one_off')->firstOrFail();

    expect($offering->specific_date->toDateString())->toBe(now()->startOfMonth()->addDays(10)->toDateString())
        ->and(substr((string) $offering->start_time, 0, 5))->toBe('09:00')
        ->and(substr((string) $offering->end_time, 0, 5))->toBe('12:00');
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

it('clones a selected recurring timeslot into the target month', function () {
    $offering = aRecurringOffering();
    $toPeriod = now()->addMonthNoOverflow()->format('Y-m');

    Livewire::test(ListOfferings::class)
        ->callTableBulkAction('cloneToMonth', [$offering], ['period' => $toPeriod]);

    $this->assertDatabaseHas('offerings', [
        'program_id' => $offering->program_id,
        'period' => $toPeriod,
        'schedule_type' => 'recurring',
        'weekday' => $offering->weekday,
        'start_time' => $offering->start_time,
        'capacity' => $offering->capacity,
        'price_sen' => $offering->price_sen,
    ]);
});

it('never clones a one-off offering', function () {
    $sport = Sport::create(['name' => 'Football', 'is_active' => true]);
    $program = Program::create(['sport_id' => $sport->id, 'name' => 'Clinic', 'base_price_sen' => 9000, 'walk_in_fee_sen' => 3000, 'default_sessions' => 1]);
    $offering = Offering::create([
        'program_id' => $program->id,
        'period' => now()->format('Y-m'),
        'schedule_type' => 'one_off',
        'specific_date' => now()->startOfMonth()->addDays(10)->toDateString(),
        'start_time' => '09:00',
        'end_time' => '12:00',
        'capacity' => 20,
        'session_count' => 1,
        'price_sen' => 9000,
        'is_open' => true,
    ]);
    $toPeriod = now()->addMonthNoOverflow()->format('Y-m');

    Livewire::test(ListOfferings::class)
        ->callTableBulkAction('cloneToMonth', [$offering], ['period' => $toPeriod]);

    expect(Offering::where('program_id', $program->id)->where('period', $toPeriod)->exists())->toBeFalse();
});

it('never clones an offering belonging to an inactive program', function () {
    $offering = aRecurringOffering(programIsActive: false);
    $toPeriod = now()->addMonthNoOverflow()->format('Y-m');

    Livewire::test(ListOfferings::class)
        ->callTableBulkAction('cloneToMonth', [$offering], ['period' => $toPeriod]);

    expect(Offering::where('program_id', $offering->program_id)->where('period', $toPeriod)->exists())->toBeFalse();
});

it('is idempotent: running the clone twice creates no duplicate', function () {
    $offering = aRecurringOffering();
    $toPeriod = now()->addMonthNoOverflow()->format('Y-m');

    Livewire::test(ListOfferings::class)
        ->callTableBulkAction('cloneToMonth', [$offering], ['period' => $toPeriod])
        ->callTableBulkAction('cloneToMonth', [$offering], ['period' => $toPeriod]);

    expect(Offering::where('program_id', $offering->program_id)
        ->where('period', $toPeriod)
        ->where('weekday', $offering->weekday)
        ->where('start_time', $offering->start_time)
        ->count())->toBe(1);
});

it('does not skip a target offering that exists at a different time', function () {
    $offering = aRecurringOffering(weekday: 3, startTime: '18:00');
    $toPeriod = now()->addMonthNoOverflow()->format('Y-m');

    // A different timeslot for the same program already exists next month (the "second team" case).
    Offering::create([
        'program_id' => $offering->program_id,
        'period' => $toPeriod,
        'schedule_type' => 'recurring',
        'weekday' => 5,
        'start_time' => '10:00',
        'end_time' => '11:30',
        'capacity' => $offering->capacity,
        'session_count' => $offering->session_count,
        'price_sen' => $offering->price_sen,
        'is_open' => true,
    ]);

    Livewire::test(ListOfferings::class)
        ->callTableBulkAction('cloneToMonth', [$offering], ['period' => $toPeriod]);

    expect(Offering::where('program_id', $offering->program_id)->where('period', $toPeriod)->count())->toBe(2);
});

it('never creates or touches enrollments when cloning', function () {
    $offering = aRecurringOffering();
    $student = Student::create(['name' => 'Kid A', 'is_active' => true]);
    Enrollment::create([
        'offering_id' => $offering->id,
        'student_id' => $student->id,
        'status' => 'active',
        'price_sen' => $offering->price_sen,
        'sessions_included' => $offering->session_count,
    ]);
    $toPeriod = now()->addMonthNoOverflow()->format('Y-m');
    $countBefore = Enrollment::count();

    Livewire::test(ListOfferings::class)
        ->callTableBulkAction('cloneToMonth', [$offering], ['period' => $toPeriod]);

    expect(Enrollment::count())->toBe($countBefore);
});

it('bulk-closes and re-opens registration on selected timeslots', function () {
    $one = aRecurringOffering();
    $two = aRecurringOffering();

    Livewire::test(ListOfferings::class)
        ->callTableBulkAction('closeRegistration', [$one, $two]);

    expect($one->refresh()->is_open)->toBeFalse()
        ->and($two->refresh()->is_open)->toBeFalse();

    // Re-opening only the one selected leaves the other closed.
    Livewire::test(ListOfferings::class)
        ->callTableBulkAction('openRegistration', [$one]);

    expect($one->refresh()->is_open)->toBeTrue()
        ->and($two->refresh()->is_open)->toBeFalse();
});

it('defaults the offerings list to the current period', function () {
    $current = aRecurringOffering();
    $past = aRecurringOffering(period: now()->subMonthNoOverflow()->format('Y-m'));

    Livewire::test(ListOfferings::class)
        ->assertCanSeeTableRecords([$current])
        ->assertCanNotSeeTableRecords([$past]);
});

function aRecurringOffering(
    ?string $period = null,
    int $weekday = 3,
    string $startTime = '18:00',
    bool $programIsActive = true,
): Offering {
    $sport = Sport::create(['name' => 'Football', 'is_active' => true]);
    $program = Program::create([
        'sport_id' => $sport->id,
        'name' => 'Group',
        'base_price_sen' => 12000,
        'walk_in_fee_sen' => 4000,
        'is_active' => $programIsActive,
    ]);

    return Offering::create([
        'program_id' => $program->id,
        'period' => $period ?? now()->format('Y-m'),
        'schedule_type' => 'recurring',
        'weekday' => $weekday,
        'start_time' => $startTime,
        'end_time' => '19:30',
        'capacity' => 12,
        'session_count' => 5,
        'price_sen' => 12000,
        'is_open' => true,
    ]);
}
