<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\Exams\ExamRevisionPublisher;
use Illuminate\Http\JsonResponse;

class ExamPublishController extends Controller
{
    public function publish(Exam $exam, ExamRevisionPublisher $publisher): JsonResponse
    {
        try {
            $revision = $publisher->publish($exam, request()->user());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'exam_id' => $exam->id,
            'revision_id' => $revision->id,
            'revision_number' => $revision->revision_number,
        ], 201);
    }
}
