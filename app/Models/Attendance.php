<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\ParticipantType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    protected $fillable = [
        'training_session_id',
        'student_id',
        'enrollment_id',
        'participant_type',
        'status',
        'coach_id',
        'walk_in_fee_sen',
        'note',
        'marked_by',
    ];

    protected function casts(): array
    {
        return [
            'participant_type' => ParticipantType::class,
            'status' => AttendanceStatus::class,
            'walk_in_fee_sen' => 'integer',
        ];
    }

    public function trainingSession(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(AssessmentScore::class);
    }
}
