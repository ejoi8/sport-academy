<?php

namespace Database\Seeders;

use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\Sport;
use Illuminate\Database\Seeder;

class RubricSeeder extends Seeder
{
    /**
     * The assessment rubric (skill category => skills) that Run Training scores.
     */
    public const RUBRIC = [
        'Technical' => ['Passing', 'Shooting', 'Dribbling', 'First touch'],
        'Physical' => ['Speed', 'Stamina'],
        'Mental' => ['Teamwork'],
    ];

    /**
     * The Football sport and its rubric. Idempotent, so both the baseline and
     * demo seeders can call it; other seeders read the sport/skills back by query.
     */
    public function run(): void
    {
        $sport = Sport::firstOrCreate(['name' => 'Football']);

        $sort = 0;
        foreach (self::RUBRIC as $categoryName => $skillNames) {
            $category = SkillCategory::firstOrCreate(
                ['sport_id' => $sport->id, 'name' => $categoryName],
                ['sort_order' => $sort++],
            );

            foreach ($skillNames as $skillName) {
                Skill::firstOrCreate(
                    ['skill_category_id' => $category->id, 'name' => $skillName],
                    ['sport_id' => $sport->id, 'sort_order' => $sort++],
                );
            }
        }
    }
}
