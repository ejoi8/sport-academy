<?php

use App\Filament\Pages\Reports\CreditLiabilityReport;
use App\Filament\Pages\Reports\RevenueReport;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Program;
use App\Models\Sport;
use App\Models\Student;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\Reporting\CreditLiabilitySummary;
use App\Support\Reporting\RevenueSummary;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

function reportStaff(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

    return $user;
}

function reportParent(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']));

    return $user;
}

function reportProgram(): Program
{
    $sport = Sport::firstOrCreate(['name' => 'Football'], ['is_active' => true]);

    return Program::create(['sport_id' => $sport->id, 'name' => 'Group', 'base_price_sen' => 12000, 'walk_in_fee_sen' => 4000, 'default_sessions' => 4]);
}

function reportOffering(Program $program, string $period, int $price = 12000, int $sessions = 4): Offering
{
    return Offering::create([
        'program_id' => $program->id, 'period' => $period, 'schedule_type' => 'recurring', 'weekday' => 3,
        'start_time' => '18:00', 'end_time' => '19:30', 'capacity' => 20, 'session_count' => $sessions,
        'price_sen' => $price, 'is_open' => true,
    ]);
}

function reportEnrol(Offering $offering, string $status, ?Student $student = null): Enrollment
{
    $student ??= Student::create(['name' => fake()->name(), 'is_active' => true]);

    return Enrollment::create([
        'student_id' => $student->id, 'offering_id' => $offering->id, 'status' => $status,
        'price_sen' => $offering->price_sen, 'sessions_included' => $offering->session_count,
    ]);
}

function reportConsume(Enrollment $enrolment, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        $session = TrainingSession::firstOrCreate([
            'offering_id' => $enrolment->offering_id,
            'session_date' => now()->startOfMonth()->addDays($i)->toDateString(),
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

it('summarises revenue as billed = collected + outstanding, broken down by program', function () {
    $program = reportProgram();
    $period = now()->format('Y-m');
    $offering = reportOffering($program, $period);

    reportEnrol($offering, 'active');
    reportEnrol($offering, 'pending');
    reportEnrol($offering, 'overdue');

    $data = RevenueSummary::for($period);

    expect($data['billed_sen'])->toBe(36000)
        ->and($data['collected_sen'])->toBe(12000)
        ->and($data['outstanding_sen'])->toBe(24000)
        ->and($data['enrollment_count'])->toBe(3)
        ->and($data['by_program']['Group']['active'])->toBe(1)
        ->and($data['by_program']['Group']['pending'])->toBe(1)
        ->and($data['by_program']['Group']['overdue'])->toBe(1);
});

it('counts renewing vs new enrolments by prior-month history', function () {
    $program = reportProgram();
    $period = now()->format('Y-m');
    $priorPeriod = now()->subMonthNoOverflow()->format('Y-m');

    $returning = Student::create(['name' => 'Returning Kid', 'is_active' => true]);
    reportEnrol(reportOffering($program, $priorPeriod), 'active', $returning);

    $current = reportOffering($program, $period);
    reportEnrol($current, 'active', $returning); // renewing
    reportEnrol($current, 'active');             // new

    $data = RevenueSummary::for($period);

    expect($data['renewing_count'])->toBe(1)
        ->and($data['new_count'])->toBe(1);
});

it('values credit liability at price per session and excludes over-delivered enrolments', function () {
    $program = reportProgram();
    $offering = reportOffering($program, now()->format('Y-m'));

    $active = reportEnrol($offering, 'active'); // 4 sessions @ 12000 -> 3000/session
    reportConsume($active, 1);                  // 3 remaining -> 9000 of liability

    $over = reportEnrol($offering, 'active');
    reportConsume($over, 5);                     // used 5 of 4 -> over-delivered, 0 remaining

    $data = CreditLiabilitySummary::build();

    expect($data['total_remaining_credits'])->toBe(3)
        ->and($data['total_value_sen'])->toBe(9000)
        ->and($data['over_delivered_count'])->toBe(1)
        ->and($data['by_program']['Group']['remaining_credits'])->toBe(3);
});

it('renders the report pages for staff and hides them from non-staff', function () {
    $this->actingAs(reportStaff());
    Livewire::test(RevenueReport::class)->assertOk();
    Livewire::test(CreditLiabilityReport::class)->assertOk();

    expect(RevenueReport::canAccess())->toBeTrue();

    $this->actingAs(reportParent());
    expect(RevenueReport::canAccess())->toBeFalse()
        ->and(CreditLiabilityReport::canAccess())->toBeFalse();
});

it('serves the revenue print sheet and CSV to staff, forbids others', function () {
    $program = reportProgram();
    reportEnrol(reportOffering($program, now()->format('Y-m')), 'active');

    $this->actingAs(reportStaff())
        ->get(route('reports.revenue'))
        ->assertOk()
        ->assertSee('Revenue');

    $this->actingAs(reportStaff())
        ->get(route('reports.revenue', ['format' => 'csv']))
        ->assertOk()
        ->assertDownload();

    $this->actingAs(reportParent())
        ->get(route('reports.revenue'))
        ->assertForbidden();
});

it('serves the credit-liability sheet and the enrolment/payment CSV exports to staff only', function () {
    $this->actingAs(reportStaff())->get(route('reports.credit-liability'))->assertOk()->assertSee('Credit Liability');
    $this->actingAs(reportStaff())->get(route('reports.enrollments.csv'))->assertOk()->assertDownload();
    $this->actingAs(reportStaff())->get(route('reports.payments.csv'))->assertOk()->assertDownload();

    $this->actingAs(reportParent())->get(route('reports.enrollments.csv'))->assertForbidden();
    $this->actingAs(reportParent())->get(route('reports.credit-liability'))->assertForbidden();
});
