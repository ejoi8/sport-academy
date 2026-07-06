<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

class Enrollment extends Model
{
    use LogsActivity;
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
        'source',
        'booking_reference',
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('enrolments')
            ->logFillable()
            ->logOnlyDirty();
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

    public function payments(): HasMany
    {
        return $this->hasMany(GatewayPayment::class, 'reference', 'booking_reference');
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(GatewayPayment::class, 'reference', 'booking_reference')->latestOfMany();
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject')->latest();
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

    public function hasRecordedAttendances(): bool
    {
        return $this->attendances()->exists();
    }

    public function snapshotsLocked(): bool
    {
        return $this->creditsUsed() > 0;
    }

    public function deletionBlockedReason(): ?string
    {
        return $this->hasRecordedAttendances()
            ? 'This enrolment has recorded sessions. Cancel it instead (history is kept).'
            : null;
    }

    public function hasLiveCredits(): bool
    {
        return $this->creditsRemaining() > 0
            && ($this->credits_expire_at === null || $this->credits_expire_at->gte(today()));
    }

    public function isOnlineBooking(): bool
    {
        return $this->source === 'online';
    }
}
