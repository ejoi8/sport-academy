<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sport extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function skillCategories(): HasMany
    {
        return $this->hasMany(SkillCategory::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class);
    }
}
