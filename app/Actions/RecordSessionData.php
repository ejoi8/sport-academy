<?php

namespace App\Actions;

/**
 * The input for recording one training session — everything RecordTrainingSession needs, and
 * nothing about the UI it came from. Build it with one of the two named constructors so it reads
 * clearly whether you are recording into an existing timeslot or a brand-new (ad-hoc) one.
 */
final class RecordSessionData
{
    /**
     * @param  array<string, array<string, mixed>>  $roster  one entry per player, keyed by the
     *                                                       caller's row key (e.g. 's123' for a student, 'n1' for a new walk-in). Each entry carries:
     *                                                       student_id, name, ic, phone, type (enrolled|make_up|walk_in), status, coach_id, fee_sen,
     *                                                       enrollment_id, scores (skill id => 1-5 or null), note.
     */
    private function __construct(
        public readonly ?int $offeringId,
        public readonly ?int $newProgramId,
        public readonly ?string $newStartTime,
        public readonly ?string $newEndTime,
        public readonly string $date,
        public readonly ?int $coachId,
        public readonly int $markedBy,
        public readonly array $roster,
    ) {}

    /**
     * Record into a timeslot that already exists.
     *
     * @param  array<string, array<string, mixed>>  $roster
     */
    public static function forExisting(int $offeringId, string $date, ?int $coachId, int $markedBy, array $roster): self
    {
        return new self($offeringId, null, null, null, $date, $coachId, $markedBy, $roster);
    }

    /**
     * Record a brand-new one-off session — its timeslot is created as part of the save.
     *
     * @param  array<string, array<string, mixed>>  $roster
     */
    public static function forNewSession(int $programId, string $startTime, ?string $endTime, string $date, ?int $coachId, int $markedBy, array $roster): self
    {
        return new self(null, $programId, $startTime, $endTime, $date, $coachId, $markedBy, $roster);
    }
}
