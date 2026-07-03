<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    protected $fillable = [
        'sport_id',
        'name',
        'description',
        'base_price_sen',
        'walk_in_fee_sen',
        'default_sessions',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price_sen' => 'integer',
            'walk_in_fee_sen' => 'integer',
            'default_sessions' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(Offering::class);
    }
}
