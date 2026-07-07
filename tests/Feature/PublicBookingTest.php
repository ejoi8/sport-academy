<?php

use App\Enums\EnrollmentStatus;
use App\Livewire\PublicSite\BookingWizard;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Program;
use App\Models\Sport;
use App\Models\Student;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingReceived;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

function publicProgram(array $program = [], array $offering = []): array
{
    $sport = Sport::create(['name' => 'Football', 'is_active' => true]);

    $programModel = Program::create(array_merge([
        'sport_id' => $sport->id,
        'name' => 'Group Training',
        'description' => 'Saturday academy sessions.',
        'base_price_sen' => 12000,
        'walk_in_fee_sen' => 4000,
        'default_sessions' => 4,
        'is_active' => true,
    ], $program));

    $offeringModel = Offering::create(array_merge([
        'program_id' => $programModel->id,
        'period' => now()->format('Y-m'),
        'schedule_type' => 'recurring',
        'weekday' => 6,
        'start_time' => '09:00',
        'end_time' => '10:30',
        'capacity' => 2,
        'session_count' => 4,
        'price_sen' => 12000,
        'is_open' => true,
    ], $offering));

    return [$programModel, $offeringModel];
}

function parentUser(string $name = 'Parent User'): User
{
    $user = User::factory()->create(['name' => $name]);
    $user->assignRole(Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']));

    return $user;
}

it('books a class as a guest, creating the account, child, pending enrolment, and reference', function () {
    Notification::fake();

    [, $offering] = publicProgram();

    Livewire::test(BookingWizard::class, ['offering' => $offering])
        ->set('studentName', 'Adam Rahman')
        ->set('guardianName', 'Aida Rahman')
        ->set('guardianPhone', '0123456789')
        ->set('accountName', 'Aida Rahman')
        ->set('accountEmail', 'aida@example.test')
        ->set('accountPhone', '0123456789')
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'password123')
        ->set('agreedToPolicy', true)
        ->call('submit')
        ->assertSet('step', 4);

    $user = User::where('email', 'aida@example.test')->firstOrFail();
    $student = Student::where('name', 'Adam Rahman')->firstOrFail();
    $enrollment = Enrollment::where('student_id', $student->id)->firstOrFail();

    expect($student->parent_id)->toBe($user->id)
        ->and($enrollment->status)->toBe(EnrollmentStatus::Pending)
        ->and($enrollment->source)->toBe('online')
        ->and($enrollment->booking_reference)->toStartWith('BK-'.now()->year.'-');

    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, BookingReceived::class);
});

it('lets a logged-in parent book an existing child and rejects a duplicate booking', function () {
    Notification::fake();

    $parent = parentUser();
    $student = Student::create([
        'parent_id' => $parent->id,
        'name' => 'Nadia',
        'is_active' => true,
    ]);

    [, $offering] = publicProgram();

    $this->actingAs($parent);

    Livewire::test(BookingWizard::class, ['offering' => $offering])
        ->set('useExistingStudent', true)
        ->set('existingStudentId', $student->id)
        ->set('accountPhone', '0123456789')
        ->set('agreedToPolicy', true)
        ->call('submit')
        ->assertSet('step', 4);

    expect(Enrollment::where('student_id', $student->id)->where('offering_id', $offering->id)->count())->toBe(1);

    Livewire::test(BookingWizard::class, ['offering' => $offering])
        ->set('useExistingStudent', true)
        ->set('existingStudentId', $student->id)
        ->set('accountPhone', '0123456789')
        ->set('agreedToPolicy', true)
        ->call('submit')
        ->assertHasErrors(['submit']);
});

it('requires a contact phone before booking, and saves it onto a phone-less account', function () {
    Notification::fake();

    $parent = parentUser(); // factory users carry no phone
    $student = Student::create(['parent_id' => $parent->id, 'name' => 'Nadia', 'is_active' => true]);
    [, $offering] = publicProgram();

    $this->actingAs($parent);

    // Without a phone the booking is refused — gateways like toyyibPay cannot bill without one.
    Livewire::test(BookingWizard::class, ['offering' => $offering])
        ->set('useExistingStudent', true)
        ->set('existingStudentId', $student->id)
        ->set('agreedToPolicy', true)
        ->call('submit')
        ->assertHasErrors(['accountPhone']);

    expect(Enrollment::count())->toBe(0);

    // Supplying one lets the booking through and persists it to the account for next time.
    Livewire::test(BookingWizard::class, ['offering' => $offering])
        ->set('useExistingStudent', true)
        ->set('existingStudentId', $student->id)
        ->set('accountPhone', '0198765432')
        ->set('agreedToPolicy', true)
        ->call('submit')
        ->assertSet('step', 4);

    expect($parent->refresh()->phone)->toBe('0198765432');
});

it('rejects online booking for a full class and counts pending seats', function () {
    $booker = parentUser();
    $otherParent = parentUser('Other Parent');
    $otherStudent = Student::create([
        'parent_id' => $otherParent->id,
        'name' => 'Held Seat',
        'is_active' => true,
    ]);

    [, $offering] = publicProgram([], ['capacity' => 1]);

    Enrollment::create([
        'student_id' => $otherStudent->id,
        'offering_id' => $offering->id,
        'status' => EnrollmentStatus::Pending->value,
        'price_sen' => $offering->price_sen,
        'sessions_included' => $offering->session_count,
        'source' => 'online',
    ]);

    $this->actingAs($booker);

    Livewire::test(BookingWizard::class, ['offering' => $offering])
        ->set('useExistingStudent', false)
        ->set('studentName', 'Late Booker')
        ->set('accountPhone', '0123456789')
        ->set('agreedToPolicy', true)
        ->call('submit')
        ->assertHasErrors(['submit']);

    expect($offering->fresh()->seatsLeft())->toBe(0);
});

it('lists only open current-and-next-month offerings on the public pages', function () {
    [$program, $currentOffering] = publicProgram();
    Offering::create([
        'program_id' => $program->id,
        'period' => now()->addMonth()->format('Y-m'),
        'schedule_type' => 'recurring',
        'weekday' => 3,
        'start_time' => '18:00',
        'end_time' => '19:30',
        'capacity' => 10,
        'session_count' => 4,
        'price_sen' => 12000,
        'is_open' => true,
    ]);
    Offering::create([
        'program_id' => $program->id,
        'period' => now()->addMonths(2)->format('Y-m'),
        'schedule_type' => 'recurring',
        'weekday' => 2,
        'start_time' => '17:00',
        'end_time' => '18:30',
        'capacity' => 10,
        'session_count' => 4,
        'price_sen' => 12000,
        'is_open' => true,
    ]);
    Offering::create([
        'program_id' => $program->id,
        'period' => now()->format('Y-m'),
        'schedule_type' => 'recurring',
        'weekday' => 1,
        'start_time' => '16:00',
        'end_time' => '17:00',
        'capacity' => 10,
        'session_count' => 4,
        'price_sen' => 12000,
        'is_open' => false,
    ]);

    Enrollment::create([
        'student_id' => Student::create(['name' => 'Seat Hold', 'is_active' => true])->id,
        'offering_id' => $currentOffering->id,
        'status' => EnrollmentStatus::Pending->value,
        'price_sen' => $currentOffering->price_sen,
        'sessions_included' => $currentOffering->session_count,
        'source' => 'online',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Programs for this month and next')
        ->assertSee('1 seats left')
        ->assertDontSee(now()->addMonths(2)->format('F Y'));

    $this->get(route('programs.show', $program))
        ->assertOk()
        ->assertSee(now()->format('F Y'))
        ->assertSee(now()->addMonth()->format('F Y'))
        ->assertDontSee('Mon 16:00');
});

it('scopes the family page to the authenticated parent only', function () {
    $parentA = parentUser('Parent A');
    $parentB = parentUser('Parent B');

    Student::create(['parent_id' => $parentA->id, 'name' => 'A Child', 'is_active' => true]);
    Student::create(['parent_id' => $parentB->id, 'name' => 'B Child', 'is_active' => true]);

    $this->actingAs($parentA)
        ->get(route('family.index'))
        ->assertOk()
        ->assertSee('A Child')
        ->assertDontSee('B Child');
});

it('sends a booking confirmed notification when an online pending enrolment is activated', function () {
    Notification::fake();

    $parent = parentUser();
    $student = Student::create([
        'parent_id' => $parent->id,
        'name' => 'Confirm Me',
        'is_active' => true,
    ]);

    [, $offering] = publicProgram();

    $enrollment = Enrollment::create([
        'student_id' => $student->id,
        'offering_id' => $offering->id,
        'status' => EnrollmentStatus::Pending->value,
        'price_sen' => $offering->price_sen,
        'sessions_included' => $offering->session_count,
        'source' => 'online',
        'booking_reference' => 'BK-2026-000123',
    ]);

    $enrollment->update(['status' => EnrollmentStatus::Active->value]);

    Notification::assertSentTo($parent, BookingConfirmed::class);
});
