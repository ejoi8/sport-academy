<?php

use App\Enums\EnrollmentStatus;
use App\Filament\Widgets\AdminStatsWidget;
use App\Filament\Widgets\CoachScoreTrendWidget;
use App\Filament\Widgets\CoachStatsWidget;
use App\Filament\Widgets\CoachTimeslotsWidget;
use App\Filament\Widgets\EnrolmentsTrendWidget;
use App\Filament\Widgets\FollowUpWidget;
use App\Filament\Widgets\TimeslotCapacityWidget;
use App\Filament\Widgets\UnusedCreditsWidget;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->seed(DemoSeeder::class);
    // coach@academy.test is super_admin + coach, so it can view every widget.
    $this->coach = User::where('email', 'coach@academy.test')->firstOrFail();
    $this->actingAs($this->coach);
});

function widgetMethod(object $widget, string $method): mixed
{
    $reflection = new ReflectionMethod($widget, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($widget);
}

it('computes four admin stats without error', function () {
    expect(widgetMethod(new AdminStatsWidget, 'getStats'))->toHaveCount(4);
});

it('computes four coach stats without error', function () {
    expect(widgetMethod(new CoachStatsWidget, 'getStats'))->toHaveCount(4);
});

it('returns six months of data for both trend charts', function () {
    foreach ([new EnrolmentsTrendWidget, new CoachScoreTrendWidget] as $chart) {
        $data = widgetMethod($chart, 'getData');

        expect($data['labels'])->toHaveCount(6)
            ->and($data['datasets'][0]['data'])->toHaveCount(6);
    }
});

it('queries and renders every table widget', function () {
    $period = now()->format('Y-m');

    Livewire::test(TimeslotCapacityWidget::class)
        ->assertOk()
        ->assertCountTableRecords(Offering::where('period', $period)->count());

    Livewire::test(FollowUpWidget::class)
        ->assertOk()
        ->assertCanSeeTableRecords(
            Enrollment::whereIn('status', [EnrollmentStatus::Overdue, EnrollmentStatus::Pending])->get()
        );

    Livewire::test(CoachTimeslotsWidget::class)
        ->assertOk()
        ->assertCountTableRecords(
            Offering::where('default_coach_id', $this->coach->id)
                ->where('period', $period)
                ->where('is_open', true)
                ->count()
        );
});

it('gates each widget to the right roles', function () {
    $adminWidgets = [AdminStatsWidget::class, EnrolmentsTrendWidget::class, TimeslotCapacityWidget::class, FollowUpWidget::class];
    $coachWidgets = [CoachStatsWidget::class, CoachTimeslotsWidget::class, CoachScoreTrendWidget::class];

    $adminOnly = User::factory()->create();
    $adminOnly->assignRole('admin');
    $coachOnly = User::factory()->create();
    $coachOnly->assignRole('coach');
    $parent = User::factory()->create();
    $parent->assignRole('parent');

    $this->actingAs($adminOnly);
    foreach ($adminWidgets as $widget) {
        expect($widget::canView())->toBeTrue();
    }
    foreach ($coachWidgets as $widget) {
        expect($widget::canView())->toBeFalse();
    }

    $this->actingAs($coachOnly);
    foreach ($coachWidgets as $widget) {
        expect($widget::canView())->toBeTrue();
    }
    foreach ($adminWidgets as $widget) {
        expect($widget::canView())->toBeFalse();
    }

    $this->actingAs($parent);
    foreach ([...$adminWidgets, ...$coachWidgets] as $widget) {
        expect($widget::canView())->toBeFalse();
    }
});

it('lists students with unused credits for staff, not parents', function () {
    Livewire::test(UnusedCreditsWidget::class)->assertOk();

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $coach = User::factory()->create();
    $coach->assignRole('coach');
    $parent = User::factory()->create();
    $parent->assignRole('parent');

    $this->actingAs($admin);
    expect(UnusedCreditsWidget::canView())->toBeTrue();

    $this->actingAs($coach);
    expect(UnusedCreditsWidget::canView())->toBeTrue();

    $this->actingAs($parent);
    expect(UnusedCreditsWidget::canView())->toBeFalse();
});
