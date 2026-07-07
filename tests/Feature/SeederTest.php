<?php

use App\Models\AssessmentScore;
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

    // Baseline = weekend catalog only (2 programs on their weekend slots), no students or enrolments.
    expect(Skill::query()->active()->count())->toBe(7)
        ->and(Program::count())->toBe(2)
        ->and(Student::count())->toBe(0);

    $coach = User::where('email', 'coach@academy.test')->firstOrFail();
    expect($coach->hasRole('super_admin'))->toBeTrue()
        ->and($coach->hasRole('coach'))->toBeTrue();
});

it('seeds demo data sharing the same rubric, with history and scores', function () {
    $this->seed(DemoSeeder::class);

    // The rubric is shared, not duplicated: still 7 skills, not 14.
    expect(Skill::query()->count())->toBe(7)
        ->and(Program::count())->toBe(4)
        ->and(TrainingSession::count())->toBeGreaterThan(0)
        ->and(AssessmentScore::count())->toBeGreaterThan(0);
});
