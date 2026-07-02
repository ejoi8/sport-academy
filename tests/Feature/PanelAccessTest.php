<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->panel = Filament::getPanel('app');
});

it('lets staff into the panel', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);

    expect($user->canAccessPanel($this->panel))->toBeTrue();
})->with(['admin', 'coach', 'super_admin']);

it('keeps parents and role-less users out of the panel', function () {
    $parent = User::factory()->create();
    $parent->assignRole('parent');

    $none = User::factory()->create();

    expect($parent->canAccessPanel($this->panel))->toBeFalse()
        ->and($none->canAccessPanel($this->panel))->toBeFalse();
});
