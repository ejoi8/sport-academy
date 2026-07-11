<?php

use App\Filament\Resources\Offerings\Pages\ListOfferings;
use App\Models\Offering;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\BaselineSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->seed(BaselineSeeder::class); // this month's offerings, all open
    $this->period = now()->format('Y-m');
    $this->actingAs(User::where('email', 'coach@academy.test')->firstOrFail()); // super_admin
});

it('closes every offering in a month in one action', function () {
    expect(Offering::where('period', $this->period)->where('is_open', true)->count())->toBeGreaterThan(1);

    Livewire::test(ListOfferings::class)
        ->callAction('closeMonth', data: ['period' => $this->period, 'program_id' => '']);

    expect(Offering::where('period', $this->period)->where('is_open', true)->count())->toBe(0)
        ->and(Offering::where('period', $this->period)->where('is_open', false)->count())->toBeGreaterThan(1);
});

it('opens a month back up', function () {
    Offering::where('period', $this->period)->update(['is_open' => false]);

    Livewire::test(ListOfferings::class)
        ->callAction('openMonth', data: ['period' => $this->period, 'program_id' => '']);

    expect(Offering::where('period', $this->period)->where('is_open', false)->count())->toBe(0);
});

it('scopes to a single programme when chosen', function () {
    $group = Program::where('name', 'Group')->firstOrFail();

    Livewire::test(ListOfferings::class)
        ->callAction('closeMonth', data: ['period' => $this->period, 'program_id' => (string) $group->id]);

    // Group's slots closed, other programmes untouched.
    expect(Offering::where('period', $this->period)->where('program_id', $group->id)->where('is_open', true)->count())->toBe(0)
        ->and(Offering::where('period', $this->period)->where('program_id', '!=', $group->id)->where('is_open', true)->count())->toBeGreaterThan(0);
});

it('leaves a different month untouched', function () {
    $next = now()->addMonthNoOverflow()->format('Y-m');
    // Clone this month's offerings into next month so there's another month present.
    Offering::where('period', $this->period)->get()->each(function (Offering $o) use ($next): void {
        Offering::create(array_merge($o->only([
            'program_id', 'schedule_type', 'weekday', 'start_time', 'end_time', 'capacity',
            'session_count', 'price_sen', 'default_coach_id',
        ]), ['period' => $next, 'is_open' => true]));
    });

    Livewire::test(ListOfferings::class)
        ->callAction('closeMonth', data: ['period' => $this->period, 'program_id' => '']);

    expect(Offering::where('period', $this->period)->where('is_open', true)->count())->toBe(0)
        ->and(Offering::where('period', $next)->where('is_open', true)->count())->toBeGreaterThan(0); // next month still open
});
