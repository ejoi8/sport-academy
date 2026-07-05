<?php

namespace App\Models;

use App\Enums\Gender;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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

    public function getAgeAttribute(): ?int
    {
        return $this->dob?->age;
    }

    /**
     * The oldest enrolment that still has an unexpired session credit to spend — the pool a
     * make-up would draw from. Null means no live credits, so the student is a paying walk-in.
     *
     * Make-up credits are same-program only (see docs/credits-policy.md) — Run Training is
     * responsible for applying that restriction, and always passes the session's program id.
     * Callers that pass null get today's any-program behaviour (generic credit lookups/tests).
     */
    public function liveCreditEnrollment(?int $programId = null): ?Enrollment
    {
        return $this->enrollments()
            ->whereIn('status', ['active', 'pending', 'overdue'])
            ->where(fn ($query) => $query->whereNull('credits_expire_at')->orWhereDate('credits_expire_at', '>=', today()))
            ->when($programId, fn ($query) => $query->whereHas('offering', fn ($q) => $q->where('program_id', $programId)))
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
}
