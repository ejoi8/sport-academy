<?php

namespace App\Models;

use App\Enums\ScheduleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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

    public function scopePubliclyBookable(Builder $query): Builder
    {
        return $query
            ->where('is_open', true)
            ->where('capacity', '>', 0)
            ->whereIn('period', [
                now()->startOfMonth()->format('Y-m'),
                now()->startOfMonth()->addMonth()->format('Y-m'),
            ]);
    }

    public function deletionBlockedReason(): ?string
    {
        if ($this->enrollments()->exists() || $this->trainingSessions()->exists()) {
            return 'This timeslot has enrolments or recorded sessions. Close registration instead.';
        }

        return null;
    }

    public function label(): string
    {
        return trim(($this->program?->name ?? 'Program').' · '.$this->scheduleLabel());
    }

    public function monthLabel(): string
    {
        return Carbon::parse($this->period.'-01')->format('F Y');
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

    /**
     * The offering's nearest session date: today/next upcoming occurrence in its month, else the
     * most recent past one. One-offs return their specific date; null when undeterminable.
     */
    public function nearestOccurrence(): ?Carbon
    {
        if ($this->schedule_type === ScheduleType::OneOff) {
            return $this->specific_date?->copy();
        }

        if ($this->weekday === null) {
            return null;
        }

        $cursor = Carbon::parse($this->period.'-01')->startOfMonth();
        $end = $cursor->copy()->endOfMonth();
        $today = today();

        $occurrences = collect();

        while ($cursor->lte($end)) {
            if ($cursor->dayOfWeekIso === $this->weekday) {
                $occurrences->push($cursor->copy());
            }

            $cursor->addDay();
        }

        if ($occurrences->isEmpty()) {
            return null;
        }

        return $occurrences->first(fn (Carbon $date): bool => $date->gte($today)) ?? $occurrences->last();
    }

    public function heldSeatsCount(): int
    {
        return (int) ($this->held_seats_count ?? $this->enrollments()
            ->whereIn('status', ['active', 'pending'])
            ->count());
    }

    public function seatsLeft(): int
    {
        return max(0, $this->capacity - $this->heldSeatsCount());
    }

    public function isFull(): bool
    {
        return $this->seatsLeft() <= 0;
    }
}
