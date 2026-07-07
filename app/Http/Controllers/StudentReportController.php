<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentReportController extends Controller
{
    /**
     * A print-friendly progress report for one student. Staff may view any student; a parent may
     * view (and print) only their own child.
     */
    public function __invoke(Request $request, Student $student): View
    {
        $user = $request->user();
        $ownsChild = $user !== null && $student->parent_id === $user->id;

        abort_unless(
            (bool) $user?->hasAnyRole(['admin', 'coach', 'super_admin']) || $ownsChild,
            403,
        );

        return view('reports.student', [
            'student' => $student->load('parent'),
            'summary' => $student->assessmentSummary(),
            'attendance' => $student->attendanceCounts(),
            'credits' => $student->creditSummary(),
            'sessions' => $student->sessionHistory(),
        ]);
    }
}
