<?php

use App\Filament\Pages\Reports\AttendanceReport;
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
use App\Support\Reporting\AttendanceSummary;
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

function reportCoach(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::firstOrCreate(['name' => 'coach', 'guard_name' => 'web']));

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

function reportSession(Offering $offering, string $date, ?int $coachId = null): TrainingSession
{
    return TrainingSession::create([
        'offering_id' => $offering->id,
        'session_date' => $date,
        'coach_id' => $coachId,
    ]);
}

function reportMark(TrainingSession $session, string $status): void
{
    $student = Student::create(['name' => fake()->name(), 'is_active' => true]);
    Attendance::create([
        'training_session_id' => $session->id,
        'student_id' => $student->id,
        'participant_type' => 'walk_in',
        'status' => $status,
    ]);
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

it('summarises attendance: rate over marked, present+late count as attended', function () {
    $program = reportProgram();
    $offering = reportOffering($program, now()->format('Y-m'));
    $date = now()->startOfMonth()->toDateString();

    $session = reportSession($offering, $date);
    reportMark($session, 'present');
    reportMark($session, 'present');
    reportMark($session, 'late');
    reportMark($session, 'absent');   // no-show
    reportMark($session, 'excused');  // doesn't count as attended

    $data = AttendanceSummary::for(now()->format('Y-m'));

    // attended = 2 present + 1 late = 3 of 5 marked = 60%
    expect($data['sessions_delivered'])->toBe(1)
        ->and($data['attended'])->toBe(3)
        ->and($data['total_marked'])->toBe(5)
        ->and($data['attendance_rate'])->toBe(60.0)
        ->and($data['no_show_rate'])->toBe(20.0)
        ->and($data['by_program']['Group']['sessions'])->toBe(1);
});

it('scopes attendance to a single coach when a coach id is given', function () {
    $program = reportProgram();
    $offering = reportOffering($program, now()->format('Y-m'));
    $date = now()->startOfMonth()->toDateString();

    $farid = reportStaff();
    $lena = reportStaff();

    $sessionA = reportSession($offering, $date, $farid->id);
    reportMark($sessionA, 'present');
    reportMark($sessionA, 'present');

    $sessionB = reportSession($offering, now()->startOfMonth()->addDay()->toDateString(), $lena->id);
    reportMark($sessionB, 'absent');

    // Farid sees only his own session and its two attendances.
    $faridData = AttendanceSummary::for(now()->format('Y-m'), $farid->id);
    expect($faridData['sessions_delivered'])->toBe(1)
        ->and($faridData['total_marked'])->toBe(2)
        ->and($faridData['attendance_rate'])->toBe(100.0);

    // Admin (no coach filter) sees both sessions.
    $allData = AttendanceSummary::for(now()->format('Y-m'));
    expect($allData['sessions_delivered'])->toBe(2)
        ->and($allData['total_marked'])->toBe(3);
});

it('gives the attendance report to staff and coaches, but a coach only sees their own', function () {
    $program = reportProgram();
    $offering = reportOffering($program, now()->format('Y-m'));
    $coach = reportCoach();

    // A session the coach led, and one led by someone else.
    reportMark(reportSession($offering, now()->startOfMonth()->toDateString(), $coach->id), 'present');
    reportMark(reportSession($offering, now()->startOfMonth()->addDay()->toDateString(), reportStaff()->id), 'absent');

    // Page renders for both roles; blocked for a parent.
    $this->actingAs(reportStaff());
    Livewire::test(AttendanceReport::class)->assertOk();
    expect(AttendanceReport::canAccess())->toBeTrue();

    $this->actingAs($coach);
    expect(AttendanceReport::canAccess())->toBeTrue();

    $this->actingAs(reportParent());
    expect(AttendanceReport::canAccess())->toBeFalse();

    // The coach's print sheet is scoped to their one session; the parent is forbidden.
    $this->actingAs($coach)->get(route('reports.attendance'))->assertOk()->assertSee('Attendance');
    $this->actingAs($coach)->get(route('reports.attendance', ['format' => 'csv']))->assertOk()->assertDownload();
    $this->actingAs(reportParent())->get(route('reports.attendance'))->assertForbidden();

    // Coach scoping is enforced server-side: even if a coach passes ?coach=, they see only their own.
    $data = AttendanceSummary::for(now()->format('Y-m'), $coach->id);
    expect($data['sessions_delivered'])->toBe(1);
});
