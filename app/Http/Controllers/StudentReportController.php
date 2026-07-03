<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentReportController extends Controller
{
    /**
     * A print-friendly progress report for one student (staff only).
     */
    public function __invoke(Request $request, Student $student): View
    {
        abort_unless(
            (bool) $request->user()?->hasAnyRole(['admin', 'coach', 'super_admin']),
            403,
        );

        return view('reports.student', [
            'student' => $student->load('parent'),
            'summary' => $student->assessmentSummary(),
            'attendance' => $student->attendanceCounts(),
        ]);
    }
}
