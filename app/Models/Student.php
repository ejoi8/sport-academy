<?php

namespace App\Models;

use App\Enums\Gender;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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

    public function deletionBlockedReason(): ?string
    {
        return $this->attendances()->exists()
            ? 'This student has recorded sessions. Set them inactive instead.'
            : null;
    }

    public function getAgeAttribute(): ?int
    {
        return $this->dob?->age;
    }

    /**
     * The oldest enrolment that still has an unexpired session credit to spend — the pool a
     * make-up would draw from. Null means no live credits, so the student is a paying walk-in.
     *
     * Make-up credits are same-program only (see docs/credits-policy.md) — Run Training is
     * responsible for applying that restriction, and always passes the session's program AND
     * period; a future month's prepaid credits must never fund a make-up today. Null args keep
     * legacy behaviour for generic callers (model tests call it no-arg).
     */
    public function liveCreditEnrollment(?int $programId = null, ?string $maxPeriod = null): ?Enrollment
    {
        return $this->enrollments()
            ->whereIn('status', ['active', 'pending', 'overdue'])
            ->where(fn ($query) => $query->whereNull('credits_expire_at')->orWhereDate('credits_expire_at', '>=', today()))
            ->when($programId, fn ($query) => $query->whereHas('offering', fn ($q) => $q->where('program_id', $programId)))
            ->when($maxPeriod, fn ($query) => $query->whereHas('offering', fn ($q) => $q->where('period', '<=', $maxPeriod)))
            ->withCount(['attendances as used_credits' => fn ($query) => $query->whereIn('status', Enrollment::CREDIT_CONSUMING_STATUSES)])
            ->with('offering.program')
            ->orderBy('created_at')
            ->get()
            ->first(fn (Enrollment $enrollment): bool => $enrollment->creditsRemaining() > 0);
    }

    /**
     * Lifetime session-credit totals across all live-status enrolments. Owed and over are summed
     * PER ENROLMENT (a surplus in one month never nets against a shortfall in another).
     *
     * @return array{purchased:int, attended:int, owed:int, over:int}
     */
    public function creditSummary(): array
    {
        $enrollments = $this->enrollments()
            ->whereIn('status', ['active', 'pending', 'overdue'])
            ->withCount(['attendances as used_credits' => fn ($query) => $query->whereIn('status', Enrollment::CREDIT_CONSUMING_STATUSES)])
            ->get();

        return [
            'purchased' => (int) $enrollments->sum('sessions_included'),
            'attended' => (int) $enrollments->sum('used_credits'),
            'owed' => (int) $enrollments->sum(fn (Enrollment $enrollment): int => max(0, $enrollment->sessions_included - $enrollment->used_credits)),
            'over' => (int) $enrollments->sum(fn (Enrollment $enrollment): int => max(0, $enrollment->used_credits - $enrollment->sessions_included)),
        ];
    }

    /**
     * Per-skill assessment summary — times scored, average, and latest score — in rubric order.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function assessmentSummary(): Collection
    {
        return AssessmentScore::query()
            ->whereHas('attendance', fn ($query) => $query->where('student_id', $this->id))
            ->with(['skill.category', 'attendance.trainingSession'])
            ->get()
            ->groupBy('skill_id')
            ->map(function (Collection $scores): array {
                $skill = $scores->first()->skill;
                $latest = $scores
                    ->sortByDesc(fn (AssessmentScore $score) => $score->attendance?->trainingSession?->session_date)
                    ->first();

                return [
                    'skill' => $skill?->name ?? '—',
                    'category' => $skill?->category?->name ?? '—',
                    'sort' => $skill?->sort_order ?? 0,
                    'count' => $scores->count(),
                    'average' => round((float) $scores->avg('score'), 1),
                    'latest' => $latest?->score,
                ];
            })
            ->sortBy('sort')
            ->values();
    }

    /**
     * Every recorded session for this student, newest first — date, timeslot, status, coach, note
     * and the per-skill scores (in rubric order). This is the session-by-session detail behind the
     * attendance and assessment summaries.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function sessionHistory(): Collection
    {
        return $this->attendances()
            ->with(['trainingSession.offering.program', 'coach', 'scores.skill'])
            ->get()
            ->sortByDesc(fn (Attendance $attendance): string => (string) $attendance->trainingSession?->session_date)
            ->values()
            ->map(fn (Attendance $attendance): array => [
                'date' => $attendance->trainingSession?->session_date
                    ? Carbon::parse($attendance->trainingSession->session_date)
                    : null,
                'timeslot' => $attendance->trainingSession?->offering?->label() ?? '—',
                'status' => $attendance->status->value,
                'type' => $attendance->participant_type->value,
                'coach' => $attendance->coach?->name,
                'note' => $attendance->note,
                'scores' => $attendance->scores
                    ->sortBy(fn (AssessmentScore $score): int => $score->skill?->sort_order ?? 0)
                    ->map(fn (AssessmentScore $score): array => ['skill' => $score->skill?->name, 'score' => $score->score])
                    ->values()
                    ->all(),
            ]);
    }

    /**
     * This student's attendance tally by status.
     *
     * @return array<string, int>
     */
    public function attendanceCounts(): array
    {
        return $this->attendances()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    public function carriedCreditsCount(): int
    {
        return (int) $this->enrollments()
            ->whereIn('status', ['active', 'pending', 'overdue'])
            // Only unexpired credits can actually fund a make-up, so only they count as "carried"
            // — matches liveCreditEnrollment() and Run Training's carry-over query.
            ->where(fn ($query) => $query->whereNull('credits_expire_at')->orWhereDate('credits_expire_at', '>=', today()))
            ->withCount(['attendances as used_credits' => fn ($query) => $query->whereIn('status', Enrollment::CREDIT_CONSUMING_STATUSES)])
            ->get()
            ->sum(fn (Enrollment $enrollment): int => max(0, $enrollment->sessions_included - $enrollment->used_credits));
    }
}
