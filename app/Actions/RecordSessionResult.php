<?php

namespace App\Actions;

use App\Models\TrainingSession;

/**
 * What recording a session gives back: the session that was written, plus the roster with any
 * newly created walk-in students' ids filled in — so the caller's UI can remember them and a
 * second save won't create the same student twice.
 */
final class RecordSessionResult
{
    /**
     * @param  array<string, array<string, mixed>>  $roster
     */
    public function __construct(
        public readonly TrainingSession $session,
        public readonly array $roster,
    ) {}
}
