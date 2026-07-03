<?php

namespace App\Models;

use App\Enums\ScheduleType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offering extends Model
{
    protected $fillable = [
        'program_id',
        'period',
        'schedule_type',
        'weekday',
        'start_time',
        'end_time',
        'specific_date',
        'capacity',
        'session_count',
        'price_sen',
        'default_coach_id',
        'is_open',
    ];

    protected function casts(): array
    {
        return [
            'schedule_type' => ScheduleType::class,
            'weekday' => 'integer',
            'specific_date' => 'date',
            'capacity' => 'integer',
            'session_count' => 'integer',
            'price_sen' => 'integer',
            'is_open' => 'boolean',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function defaultCoach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_coach_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function trainingSessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    public function label(): string
    {
        return trim(($this->program?->name ?? 'Program').' · '.$this->scheduleLabel());
    }

    public function scheduleLabel(): string
    {
        if ($this->schedule_type === ScheduleType::OneOff) {
            $label = $this->specific_date?->format('D, j M') ?? 'One-off';

            return $this->start_time ? trim($label.' '.substr((string) $this->start_time, 0, 5)) : $label;
        }

        $days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

        return trim(($days[$this->weekday] ?? '').' '.substr((string) $this->start_time, 0, 5));
    }
}
