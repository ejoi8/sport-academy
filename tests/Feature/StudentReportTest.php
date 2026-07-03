<?php

use App\Models\AssessmentScore;
use App\Models\Attendance;
use App\Models\Offering;
use App\Models\Program;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\Sport;
use App\Models\Student;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/** A student with two scored 'Passing' sessions (3 then 5). */
function studentWithScores(): Student
{
    $sport = Sport::create(['name' => 'Football', 'is_active' => true]);
    $category = SkillCategory::create(['sport_id' => $sport->id, 'name' => 'Technical', 'sort_order' => 1]);
    $skill = Skill::create(['sport_id' => $sport->id, 'skill_category_id' => $category->id, 'name' => 'Passing', 'sort_order' => 1]);
    $program = Program::create(['sport_id' => $sport->id, 'name' => 'Group', 'base_price_sen' => 12000, 'walk_in_fee_sen' => 4000, 'default_sessions' => 4]);
    $offering = Offering::create([
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
    $student = Student::create(['name' => 'Adam Rahman', 'ic_number' => '150101010001', 'is_active' => true]);

    foreach ([['day' => 0, 'score' => 3], ['day' => 7, 'score' => 5]] as $entry) {
        $session = TrainingSession::create([
            'offering_id' => $offering->id,
            'session_date' => now()->startOfMonth()->addDays($entry['day'])->toDateString(),
        ]);
        $attendance = Attendance::create([
            'training_session_id' => $session->id,
            'student_id' => $student->id,
            'participant_type' => 'enrolled',
            'status' => 'present',
        ]);
        AssessmentScore::create(['attendance_id' => $attendance->id, 'skill_id' => $skill->id, 'score' => $entry['score']]);
    }

    return $student;
}

it('summarises a student\'s scores per skill', function () {
    $summary = studentWithScores()->assessmentSummary();

    expect($summary)->toHaveCount(1)
        ->and($summary->first())->toMatchArray([
            'skill' => 'Passing',
            'count' => 2,
            'average' => 4.0,
            'latest' => 5, // the later session
        ]);
});

it('renders the progress report for staff', function () {
    $student = studentWithScores();
    $staff = User::factory()->create();
    $staff->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

    $this->actingAs($staff)
        ->get(route('students.report', $student))
        ->assertOk()
        ->assertSee('Progress Report')
        ->assertSee('Adam Rahman')
        ->assertSee('Passing');
});

it('forbids the report to a parent', function () {
    $student = studentWithScores();
    $parent = User::factory()->create();
    $parent->assignRole(Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']));

    $this->actingAs($parent)
        ->get(route('students.report', $student))
        ->assertForbidden();
});
