<?php

namespace App\Models;

use App\Enums\Gender;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_id',
        'guardian_name',
        'guardian_phone',
        'name',
        'ic_number',
        'dob',
        'gender',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'gender' => Gender::class,
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->dob?->age;
    }

    /**
     * The oldest enrolment that still has an unexpired session credit to spend — the pool a
     * make-up would draw from. Null means no live credits, so the student is a paying walk-in.
     */
    public function liveCreditEnrollment(): ?Enrollment
    {
        return $this->enrollments()
            ->whereIn('status', ['active', 'pending', 'overdue'])
            ->where(fn ($query) => $query->whereNull('credits_expire_at')->orWhereDate('credits_expire_at', '>=', today()))
            ->withCount(['attendances as used_credits' => fn ($query) => $query->whereIn('status', Enrollment::CREDIT_CONSUMING_STATUSES)])
            ->with('offering.program')
            ->orderBy('created_at')
            ->get()
            ->first(fn (Enrollment $enrollment): bool => $enrollment->creditsRemaining() > 0);
    }
}
