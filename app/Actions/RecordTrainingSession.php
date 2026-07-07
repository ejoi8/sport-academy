<?php

namespace App\Actions;

use App\Enums\AttendanceStatus;
use App\Enums\ParticipantType;
use App\Enums\ScheduleType;
use App\Models\AssessmentScore;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Program;
use App\Models\Student;
use App\Models\TrainingSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The one place attendance, assessment scores and credit consumption are written.
 *
 * It takes a plain RecordSessionData (no Livewire, no HTTP) and does the whole save in a single
 * transaction, so any caller — the Run Training screen today, a JSON API tomorrow — records a
 * session exactly the same way. Credits are consumed simply by writing the attendance rows;
 * there is no counter to update (see Enrollment::creditsUsed()).
 */
class RecordTrainingSession
{
    public function execute(RecordSessionData $data): RecordSessionResult
    {
        return DB::transaction(function () use ($data): RecordSessionResult {
            // For a brand-new session the one-off timeslot is created here, inside the same
            // transaction — so if anything below fails, no orphan timeslot is left behind.
            $offering = $data->offeringId
                ? Offering::findOrFail($data->offeringId)
                : $this->createOffering($data);

            $session = TrainingSession::firstOrCreate(
                ['offering_id' => $offering->id, 'session_date' => $data->date],
                ['coach_id' => $data->coachId, 'created_by' => $data->markedBy],
            );

            // Keep the session's lead coach aligned with the one chosen for it.
            if ($session->coach_id !== $data->coachId) {
                $session->update(['coach_id' => $data->coachId]);
            }

            // Write one attendance row per roster entry, remembering each id for the scores below.
            $roster = $data->roster;
            $attendanceIdByStudent = [];

            foreach ($roster as $key => $row) {
                $studentId = $this->resolveStudentId($row);
                $roster[$key]['student_id'] = $studentId; // remember it so a re-save reuses the student
                $attendance = $this->writeAttendance($offering, $session, $studentId, $row, $data->markedBy);
                $attendanceIdByStudent[$studentId] = $attendance->id;
            }

            // Replace this session's rubric scores in two statements — one delete, one bulk insert.
            AssessmentScore::query()
                ->whereIn('attendance_id', array_values($attendanceIdByStudent))
                ->delete();

            $scoreRows = $this->buildScoreRows($roster, $attendanceIdByStudent);

            if ($scoreRows !== []) {
                AssessmentScore::insert($scoreRows);
            }

            // Remove anyone no longer on the roster (their scores cascade away). This is also how
            // deleting a player reverses their credit consumption — the attendance row simply goes.
            Attendance::query()
                ->where('training_session_id', $session->id)
                ->whereNotIn('student_id', array_keys($attendanceIdByStudent))
                ->delete();

            return new RecordSessionResult($session, $roster);
        });
    }

    /**
     * Create the one-off timeslot for a brand-new session, from the chosen program + time.
     */
    protected function createOffering(RecordSessionData $data): Offering
    {
        $program = Program::findOrFail($data->newProgramId);

        return Offering::create([
            'program_id' => $program->id,
            'period' => Carbon::parse($data->date)->format('Y-m'),
            'schedule_type' => ScheduleType::OneOff->value,
            'specific_date' => $data->date,
            'start_time' => $data->newStartTime,
            'end_time' => $data->newEndTime,
            'capacity' => 0,
            'session_count' => $program->default_sessions,
            'price_sen' => $program->base_price_sen,
            'default_coach_id' => $data->coachId,
            'is_open' => true,
        ]);
    }

    /**
     * The student id for a roster row: an existing student, one matched by IC, or a freshly
     * created walk-in student the first time they are saved.
     *
     * @param  array<string, mixed>  $row
     */
    protected function resolveStudentId(array $row): int
    {
        if (! empty($row['student_id'])) {
            return (int) $row['student_id'];
        }

        $ic = trim((string) ($row['ic'] ?? ''));
        $existing = $ic !== '' ? Student::where('ic_number', $ic)->first() : null;

        return (int) ($existing?->id ?? Student::create([
            'name' => $row['name'],
            'guardian_phone' => $row['phone'] ?? null,
            'ic_number' => $ic !== '' ? $ic : null,
            'is_active' => true,
        ])->id);
    }

    /**
     * Create or update the attendance row for one player in this session.
     *
     * @param  array<string, mixed>  $row
     */
    protected function writeAttendance(Offering $offering, TrainingSession $session, int $studentId, array $row, int $markedBy): Attendance
    {
        $isWalkIn = $row['type'] === ParticipantType::WalkIn->value;
        $isAbsent = $this->isAbsentStatus($row['status']);

        $attendance = Attendance::firstOrNew([
            'training_session_id' => $session->id,
            'student_id' => $studentId,
        ]);

        $attendance->fill([
            'participant_type' => $row['type'],
            'status' => $row['status'],
            'coach_id' => ! empty($row['coach_id']) ? (int) $row['coach_id'] : null,
            // A walk-in only pays a fee when they actually attended.
            'walk_in_fee_sen' => ($isWalkIn && ! $isAbsent) ? ($row['fee_sen'] ?? null) : null,
            'note' => filled($row['note'] ?? null) ? $row['note'] : null,
            'marked_by' => $markedBy,
            // The credit pool this consumes: own enrolment (enrolled), a live-credit pool (make-up)
            // or none (walk-in). The pool chosen when the row was added is kept; a fresh resolve is
            // only a fallback for rows that somehow arrive without one.
            'enrollment_id' => $isWalkIn ? null : ($attendance->enrollment_id ?? $row['enrollment_id'] ?? $this->resolveEnrollmentId($offering, $studentId, $row['type'])),
        ]);

        $attendance->save();

        return $attendance;
    }

    /**
     * The enrolment whose credit an attendance consumes: the student's enrolment in this timeslot
     * (enrolled), their oldest live-credit pool in the same program (make-up), or none (walk-in).
     */
    protected function resolveEnrollmentId(Offering $offering, int $studentId, string $type): ?int
    {
        if ($type === ParticipantType::Enrolled->value) {
            return Enrollment::query()
                ->where('offering_id', $offering->id)
                ->where('student_id', $studentId)
                ->value('id');
        }

        if ($type === ParticipantType::MakeUp->value) {
            return Student::find($studentId)?->liveCreditEnrollment($offering->program_id, $offering->period)?->id;
        }

        return null;
    }

    /**
     * Build the rubric score rows to bulk-insert. Absent players and cleared pills contribute
     * nothing, so they are simply left out.
     *
     * @param  array<string, array<string, mixed>>  $roster
     * @param  array<int, int>  $attendanceIdByStudent  student id => attendance id
     * @return array<int, array<string, mixed>>
     */
    protected function buildScoreRows(array $roster, array $attendanceIdByStudent): array
    {
        $now = now();
        $rows = [];

        foreach ($roster as $row) {
            if ($this->isAbsentStatus($row['status'])) {
                continue;
            }

            $attendanceId = $attendanceIdByStudent[$row['student_id']] ?? null;

            if (! $attendanceId) {
                continue;
            }

            foreach ($row['scores'] as $skillId => $score) {
                if (is_null($score)) {
                    continue;
                }

                $rows[] = [
                    'attendance_id' => $attendanceId,
                    'skill_id' => (int) $skillId,
                    'score' => (int) $score,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return $rows;
    }

    protected function isAbsentStatus(string $status): bool
    {
        return in_array($status, [AttendanceStatus::Absent->value, AttendanceStatus::Excused->value], true);
    }
}
