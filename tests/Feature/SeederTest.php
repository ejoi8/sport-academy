<?php

use App\Models\AssessmentScore;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Program;
use App\Models\Skill;
use App\Models\Student;
use App\Models\TrainingSession;
use App\Models\User;
use Database\Seeders\BaselineSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds a usable baseline: rubric, catalog, and a coach login', function () {
    $this->seed(BaselineSeeder::class);

    // Baseline = weekend catalog only (3 programs across 5 weekend slots), no students or enrolments.
    expect(Skill::query()->active()->count())->toBe(7)
        ->and(Program::count())->toBe(3)
        ->and(Student::count())->toBe(0)
        ->and(Offering::count())->toBe(5); // Group×1 (Sat) + 1-on-1×2 + Goalkeeper×2 (Sat + Sun)

    // Monthly fees + slot capacities mirror the published package table.
    $group = Program::where('name', 'Group')->firstOrFail();
    $oneToOne = Program::where('name', '1-on-1')->firstOrFail();
    $goalkeeper = Program::where('name', 'Goalkeeper')->firstOrFail();

    expect($group->base_price_sen)->toBe(16000)   // RM160
        ->and($oneToOne->base_price_sen)->toBe(24000) // RM240
        ->and($goalkeeper->base_price_sen)->toBe(12000) // RM120
        ->and(Offering::where('program_id', $group->id)->value('capacity'))->toBe(40)
        ->and(Offering::where('program_id', $oneToOne->id)->value('capacity'))->toBe(12)
        ->and(Offering::where('program_id', $goalkeeper->id)->value('capacity'))->toBe(12);

    $coach = User::where('email', 'coach@academy.test')->firstOrFail();
    expect($coach->hasRole('super_admin'))->toBeTrue()
        ->and($coach->hasRole('coach'))->toBeTrue();
});

it('seeds demo data sharing the same rubric, with history and scores', function () {
    DemoSeeder::$historyMonths = 4; // small, fast slice of the 5-year dataset
    $this->seed(DemoSeeder::class);

    // The rubric is shared, not duplicated: still 7 skills, not 14. Three real programmes now.
    expect(Skill::query()->count())->toBe(7)
        ->and(Program::count())->toBe(3)
        ->and(TrainingSession::count())->toBeGreaterThan(0)
        ->and(AssessmentScore::count())->toBeGreaterThan(0);

    // Current month is registered but not yet recorded: enrolments this month, zero sessions dated
    // in the current month.
    $currentPeriod = now()->format('Y-m');
    expect(Enrollment::whereHas('offering', fn ($q) => $q->where('period', $currentPeriod))->count())->toBeGreaterThan(0)
        ->and(TrainingSession::whereBetween('session_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->count())->toBe(0);
});
