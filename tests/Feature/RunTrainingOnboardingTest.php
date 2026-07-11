<?php

use App\Filament\Pages\RunTraining;
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

it('auto-shows the getting-started guide to a coach who has not seen it', function () {
    expect($this->coach->onboarded_run_training_at)->toBeNull();

    Livewire::test(RunTraining::class)->assertSet('onboarding', true);
});

it('marks the coach onboarded and hides the guide on completion', function () {
    Livewire::test(RunTraining::class)
        ->assertSet('onboarding', true)
        ->call('completeOnboarding')
        ->assertSet('onboarding', false);

    expect($this->coach->fresh()->onboarded_run_training_at)->not->toBeNull();
});

it('does not auto-show for a coach who has already onboarded', function () {
    $this->coach->forceFill(['onboarded_run_training_at' => now()])->save();

    Livewire::test(RunTraining::class)->assertSet('onboarding', false);
});

it('can be re-opened from help without resetting the flag', function () {
    $this->coach->forceFill(['onboarded_run_training_at' => now()])->save();

    Livewire::test(RunTraining::class)
        ->assertSet('onboarding', false)
        ->call('openOnboarding')
        ->assertSet('onboarding', true);

    // Re-opening doesn't wipe the record's completion timestamp.
    expect($this->coach->fresh()->onboarded_run_training_at)->not->toBeNull();
});
