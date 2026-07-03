<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enrollment extends Model
{
    use SoftDeletes;

    /**
     * Attendance statuses that consume a session credit. A no-show still burns the
     * credit (they held the slot); an excused absence does not (it can be made up).
     */
    public const CREDIT_CONSUMING_STATUSES = [
        AttendanceStatus::Present->value,
        AttendanceStatus::Late->value,
        AttendanceStatus::Absent->value,
    ];

    protected $fillable = [
        'student_id',
        'offering_id',
        'status',
        'price_sen',
        'sessions_included',
        'credits_expire_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'price_sen' => 'integer',
            'sessions_included' => 'integer',
            'credits_expire_at' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Credits consumed so far — attendances against this enrolment that count per policy.
     * Prefers a `used_credits` count loaded via withCount() to avoid an extra query.
     */
    public function creditsUsed(): int
    {
        return $this->used_credits
            ?? $this->attendances()->whereIn('status', self::CREDIT_CONSUMING_STATUSES)->count();
    }

    public function creditsRemaining(): int
    {
        return max(0, $this->sessions_included - $this->creditsUsed());
    }

    public function hasLiveCredits(): bool
    {
        return $this->creditsRemaining() > 0
            && ($this->credits_expire_at === null || $this->credits_expire_at->gte(today()));
    }
}
