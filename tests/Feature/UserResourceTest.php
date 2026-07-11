<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->seed(RoleSeeder::class);
});

function makeUser(string $role): User
{
    $user = User::create([
        'name' => ucfirst($role).' '.uniqid(),
        'email' => $role.uniqid().'@x.test',
        'phone' => '0123456789',
        'password' => 'password',
        'email_verified_at' => now(),
    ]);
    $user->assignRole($role);

    return $user;
}

it('gates the users resource to staff admins, not coaches', function () {
    $this->actingAs(makeUser('super_admin'))->get(UserResource::getUrl('index'))->assertOk();
    // A coach can enter the panel but is bounced away from the Users resource (no access).
    $this->actingAs(makeUser('coach'))->get(UserResource::getUrl('index'))->assertRedirect();
});

it('allows access for admin + super_admin only', function () {
    $this->actingAs(makeUser('admin'));
    expect(UserResource::canAccess())->toBeTrue();

    $this->actingAs(makeUser('coach'));
    expect(UserResource::canAccess())->toBeFalse();
});

it('creates a staff account with a role, hashed password and auto-verified', function () {
    $this->actingAs(makeUser('super_admin'));
    $coachRole = Role::where('name', 'coach')->value('id');

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'New Coach',
            'email' => 'newcoach@x.test',
            'phone' => '0111222333',
            'password' => 'secret1234',
            'roles' => [$coachRole],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'newcoach@x.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('coach'))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('secret1234', $user->password))->toBeTrue();
});

it('blocks deleting a parent who still has children', function () {
    $parent = makeUser('parent');
    Student::create(['name' => 'Kid', 'parent_id' => $parent->id, 'is_active' => true]);

    expect($parent->deletionBlockedReason())->toContain('parent')
        ->and(makeUser('parent')->deletionBlockedReason())->toBeNull(); // childless parent is deletable
});

it('protects super-admins and self from a plain admin, but a super-admin can manage anyone', function () {
    $admin = makeUser('admin');
    $super = makeUser('super_admin');
    $coach = makeUser('coach');

    $this->actingAs($admin);
    expect(UserResource::canEdit($super))->toBeFalse()      // can't touch a super-admin
        ->and(UserResource::canDelete($super))->toBeFalse()
        ->and(UserResource::canEdit($coach))->toBeTrue()     // can manage a normal account
        ->and(UserResource::canDelete($coach))->toBeTrue()
        ->and(UserResource::canDelete($admin))->toBeFalse(); // not your own account

    $this->actingAs($super);
    expect(UserResource::canEdit($super))->toBeTrue()
        ->and(UserResource::canDelete($coach))->toBeTrue();
});
