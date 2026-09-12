<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\Invigilation\InvigilationDashboard;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class InvigilationController extends Controller
{
    public function index(InvigilationDashboard $dashboard): Response
    {
        return Inertia::render('Staff/Invigilation/Index', [
            'exams' => $dashboard->liveExams(),
        ]);
    }

    public function show(Exam $exam, InvigilationDashboard $dashboard): Response
    {
        return Inertia::render('Staff/Invigilation/Show', [
            'dashboard' => $dashboard->forExam($exam),
        ]);
    }

    public function status(Exam $exam, InvigilationDashboard $dashboard): JsonResponse
    {
        return response()->json($dashboard->forExam($exam));
    }
}
