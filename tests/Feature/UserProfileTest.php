<?php

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->seed(RoleSeeder::class);
    $this->user = User::create([
        'name' => 'Coach Old',
        'email' => 'old@x.test',
        'phone' => '0111111111',
        'password' => 'password',
        'email_verified_at' => now(),
    ]);
    $this->user->assignRole('coach');
    $this->actingAs($this->user);
});

it('renders the profile page with a phone field', function () {
    $this->get(EditProfile::getUrl())
        ->assertOk()
        ->assertSee('Phone');
});

it('lets a user update their own name and phone', function () {
    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => 'Coach New',
            'email' => 'old@x.test',
            'phone' => '0122223333',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->user->refresh();

    expect($this->user->name)->toBe('Coach New')
        ->and($this->user->phone)->toBe('0122223333');
});
