<?php

use App\Filament\Resources\SkillCategories\Pages\CreateSkillCategory;
use App\Filament\Resources\SkillCategories\Pages\EditSkillCategory;
use App\Filament\Resources\SkillCategories\RelationManagers\SkillsRelationManager;
use App\Filament\Resources\Skills\Pages\CreateSkill;
use App\Filament\Resources\Skills\Pages\ListSkills;
use App\Models\SkillCategory;
use App\Models\Sport;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $admin = User::factory()->create();
    $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

    $this->actingAs($admin);
});

it('creates a skill category', function () {
    $sport = Sport::create(['name' => 'Football', 'is_active' => true]);

    Livewire::test(CreateSkillCategory::class)
        ->fillForm([
            'sport_id' => $sport->id,
            'name' => 'Technical',
            'sort_order' => 1,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('skill_categories', [
        'sport_id' => $sport->id,
        'name' => 'Technical',
        'sort_order' => 1,
    ]);
});

it('creates a skill through the standalone resource, deriving the sport from its category', function () {
    $sport = Sport::create(['name' => 'Football', 'is_active' => true]);
    $category = SkillCategory::create(['sport_id' => $sport->id, 'name' => 'Technical', 'sort_order' => 1]);

    Livewire::test(CreateSkill::class)
        ->fillForm([
            'skill_category_id' => $category->id,
            'name' => 'Dribbling',
            'sort_order' => 2,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('skills', [
        'skill_category_id' => $category->id,
        'sport_id' => $sport->id,
        'name' => 'Dribbling',
        'sort_order' => 2,
        'is_active' => true,
    ]);
});

it('adds a skill through the category relation manager, inheriting category and sport', function () {
    $sport = Sport::create(['name' => 'Football', 'is_active' => true]);
    $category = SkillCategory::create(['sport_id' => $sport->id, 'name' => 'Physical', 'sort_order' => 2]);

    Livewire::test(SkillsRelationManager::class, [
        'ownerRecord' => $category,
        'pageClass' => EditSkillCategory::class,
    ])
        ->assertOk()
        ->callAction(TestAction::make('create')->table(), [
            'name' => 'Speed',
            'sort_order' => 1,
            'is_active' => true,
        ]);

    $this->assertDatabaseHas('skills', [
        'skill_category_id' => $category->id,
        'sport_id' => $sport->id,
        'name' => 'Speed',
    ]);
});

it('renders the skills list', function () {
    Livewire::test(ListSkills::class)->assertOk();
});
