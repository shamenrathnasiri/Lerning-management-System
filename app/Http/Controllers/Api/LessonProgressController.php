<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LessonProgress;
use Illuminate\Http\Request;

class LessonProgressController extends Controller
{
    public function index(Request $request, int $courseId)
    {
        return response()->json(
            LessonProgress::with('lesson:id,title,slug')
                ->where('user_id', $request->user()->id)
                ->where('course_id', $courseId)
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lesson_id' => ['required', 'exists:lessons,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'is_completed' => ['nullable', 'boolean'],
            'watch_time_seconds' => ['nullable', 'integer', 'min:0'],
        ]);

        $progress = LessonProgress::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'lesson_id' => $validated['lesson_id'],
            ],
            [
                'course_id' => $validated['course_id'],
                'is_completed' => $validated['is_completed'] ?? false,
                'watch_time_seconds' => $validated['watch_time_seconds'] ?? 0,
                'completed_at' => ($validated['is_completed'] ?? false) ? now() : null,
            ]
        );

        return response()->json($progress);
    }
}
