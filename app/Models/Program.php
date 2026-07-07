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

    /**
     * A friendly icon for the booking cards, inferred from the program name. A placeholder until
     * real class imagery exists — extend the map (or add an image column) when photos are added.
     */
    public function emoji(): string
    {
        $name = mb_strtolower($this->name);

        return match (true) {
            str_contains($name, 'keeper'), str_contains($name, 'goal') => '🧤',
            str_contains($name, '1-on-1'), str_contains($name, 'one-to-one'), str_contains($name, 'private') => '🎯',
            str_contains($name, 'clinic') => '🏆',
            str_contains($name, 'camp') => '⛺',
            default => '⚽',
        };
    }

    /**
     * Each program gets its own colour world for the booking-site tiles, keyed off the same name
     * heuristics as emoji(). Returned as hex pairs (used in inline gradients — the blade layer
     * can't rely on dynamic Tailwind classes from model code).
     *
     * @return array{from:string, to:string, soft:string, ink:string}
     */
    public function theme(): array
    {
        $name = mb_strtolower($this->name);

        return match (true) {
            str_contains($name, 'keeper'), str_contains($name, 'goal') => ['from' => '#0d9488', 'to' => '#115e59', 'soft' => '#ecfdf9', 'ink' => '#0f766e'],
            str_contains($name, '1-on-1'), str_contains($name, 'one-to-one'), str_contains($name, 'private') => ['from' => '#7c3aed', 'to' => '#5b21b6', 'soft' => '#f5f1fe', 'ink' => '#6d28d9'],
            str_contains($name, 'clinic'), str_contains($name, 'camp') => ['from' => '#d97706', 'to' => '#b45309', 'soft' => '#fef6e7', 'ink' => '#b45309'],
            default => ['from' => '#2563eb', 'to' => '#1d4ed8', 'soft' => '#eff4fe', 'ink' => '#1d4ed8'],
        };
    }
}
