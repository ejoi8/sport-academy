<?php

use App\Filament\Pages\CoachHome;
use App\Models\User;
use Database\Seeders\BaselineSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->seed(BaselineSeeder::class);
    $this->coach = User::where('email', 'coach@academy.test')->firstOrFail();
    $this->actingAs($this->coach);
});

it('renders the coach home for an authenticated coach', function () {
    $this->get(CoachHome::getUrl())
        ->assertOk()
        ->assertSee('Hi,')
        ->assertSee('My classes');
});

it('computes stats and timeslots without error', function () {
    $component = Livewire::test(CoachHome::class)->assertOk();

    expect($component->instance()->stats())->toHaveKeys(['sessions_week', 'to_assess', 'attendance'])
        ->and($component->instance()->timeslots())->toBeArray();
});

it('sends a coach to the console home as the panel home', function () {
    expect(Filament::getCurrentPanel()->getHomeUrl())->toContain('coach/home');
});

it('registers the home in navigation only for coaches', function () {
    expect(CoachHome::shouldRegisterNavigation())->toBeTrue();

    $admin = User::where('email', 'admin@academy.test')->first();

    if ($admin) {
        $this->actingAs($admin);
        expect(CoachHome::shouldRegisterNavigation())->toBeFalse();
    }
});
