<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enrollment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'student_id',
        'offering_id',
        'status',
        'price_sen',
        'sessions_included',
    ];

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'price_sen' => 'integer',
            'sessions_included' => 'integer',
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
}
