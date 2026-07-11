<?php

use App\Filament\Pages\CoachHome;
use App\Filament\Pages\RunTraining;
use App\Models\Offering;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\LaunchSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->seed(LaunchSeeder::class);
});

it('opens a launch-ready academy with no students', function () {
    expect(Student::count())->toBe(0)
        ->and(Program::count())->toBe(3)
        ->and(Offering::where('is_open', true)->count())->toBe(10); // 5 slots × 2 months

    // Current and next month are both open for registration.
    expect(Offering::where('period', now()->format('Y-m'))->count())->toBe(5)
        ->and(Offering::where('period', now()->addMonthNoOverflow()->format('Y-m'))->count())->toBe(5);
});

it('sets up an admin and a coaching team with the right roles', function () {
    $admin = User::where('email', 'admin@admin.com')->firstOrFail();
    expect($admin->hasRole('super_admin'))->toBeTrue();

    foreach (['coach@coach.com', 'amir@academy.test', 'lena@academy.test', 'hafiz@academy.test'] as $email) {
        $coach = User::where('email', $email)->firstOrFail();
        expect($coach->hasRole('coach'))->toBeTrue()
            ->and($coach->hasRole('super_admin'))->toBeFalse(); // real coach, not a super-admin
    }
});

it('lets a plain coach reach the coach console', function () {
    $coach = User::where('email', 'coach@coach.com')->firstOrFail();

    $this->actingAs($coach)->get(RunTraining::getUrl())->assertOk();
    $this->actingAs($coach)->get(CoachHome::getUrl())->assertOk();
});

it('is safe to run twice (idempotent)', function () {
    $this->seed(LaunchSeeder::class);

    expect(Program::count())->toBe(3)
        ->and(Offering::count())->toBe(10)
        ->and(User::where('email', 'coach@coach.com')->count())->toBe(1);
});
