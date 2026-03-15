<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Services\ProgressTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonProgressController extends Controller
{
    public function __construct(
        protected ProgressTracker $progressTracker
    ) {}

    // ──────────────────────────────────────────────────────────────────
    // Course Progress Overview
    // ──────────────────────────────────────────────────────────────────

    /**
     * GET /courses/{course}/progress
     * Get detailed course progress for the authenticated user.
     */
    public function index(Request $request, Course $course): JsonResponse
    {
        $progress = $this->progressTracker->getCourseProgress(
            $request->user(),
            $course
        );

        return response()->json($progress);
    }

    /**
     * GET /courses/{course}/progress/circular
     * Get circular progress indicator data.
     */
    public function circularProgress(Request $request, Course $course): JsonResponse
    {
        return response()->json(
            $this->progressTracker->getCircularProgressData($request->user(), $course)
        );
    }

    /**
     * GET /courses/{course}/progress/modules
     * Get module (section) completion checkmarks.
     */
    public function moduleCheckmarks(Request $request, Course $course): JsonResponse
    {
        return response()->json(
            $this->progressTracker->getModuleCheckmarks($request->user(), $course)
        );
    }

    // ──────────────────────────────────────────────────────────────────
    // Lesson Progress Tracking
    // ──────────────────────────────────────────────────────────────────

    /**
     * POST /progress/track
     * Track lesson progress (main entry point — type-aware).
     */
    public function track(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lesson_id'  => ['required', 'exists:lessons,id'],
            'course_id'  => ['required', 'exists:courses,id'],

            // General
            'is_completed'              => ['sometimes', 'boolean'],
            'time_spent_seconds'        => ['sometimes', 'integer', 'min:0'],

            // Video-specific
            'watch_time_seconds'        => ['sometimes', 'integer', 'min:0'],
            'video_resume_position'     => ['sometimes', 'integer', 'min:0'],
            'video_total_duration'      => ['sometimes', 'integer', 'min:0'],
            'video_play_count'          => ['sometimes', 'boolean'],

            // Quiz-specific
            'quiz_attempt_id'           => ['sometimes', 'exists:quiz_attempts,id'],
            'quiz_score'                => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'quiz_passed'               => ['sometimes', 'boolean'],

            // Assignment-specific
            'submission_id'             => ['sometimes', 'exists:assignment_submissions,id'],
            'assignment_status'         => ['sometimes', 'string', 'in:draft,submitted,graded,returned,resubmitted'],
            'assignment_score'          => ['sometimes', 'numeric', 'min:0', 'max:100'],

            // PDF-specific
            'view_duration_seconds'     => ['sometimes', 'integer', 'min:0'],
            'pages_viewed'              => ['sometimes', 'integer', 'min:0'],
            'total_pages'               => ['sometimes', 'integer', 'min:1'],
        ]);

        $lesson = Lesson::findOrFail($validated['lesson_id']);
        $progress = $this->progressTracker->trackProgress(
            $request->user(),
            $lesson,
            $validated
        );

        return response()->json([
            'success'  => true,
            'progress' => $progress->load('lesson:id,title,slug,type'),
            'detail'   => $progress->completion_detail,
        ]);
    }

    /**
     * POST /progress/complete
     * Mark a lesson as complete (shorthand).
     */
    public function markComplete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lesson_id' => ['required', 'exists:lessons,id'],
        ]);

        $lesson = Lesson::findOrFail($validated['lesson_id']);
        $progress = $this->progressTracker->markLessonComplete($request->user(), $lesson);

        return response()->json([
            'success'  => true,
            'message'  => 'Lesson marked as complete.',
            'progress' => $progress,
        ]);
    }

    /**
     * POST /progress/incomplete
     * Mark a lesson as incomplete (undo).
     */
    public function markIncomplete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lesson_id' => ['required', 'exists:lessons,id'],
        ]);

        $lesson = Lesson::findOrFail($validated['lesson_id']);
        $progress = $this->progressTracker->markLessonIncomplete($request->user(), $lesson);

        return response()->json([
            'success'  => true,
            'message'  => 'Lesson marked as incomplete.',
            'progress' => $progress,
        ]);
    }

    /**
     * GET /progress/lesson/{lesson}
     * Get current progress for a specific lesson.
     */
    public function lessonProgress(Request $request, Lesson $lesson): JsonResponse
    {
        $progress = LessonProgress::where('user_id', $request->user()->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        if (!$progress) {
            return response()->json([
                'started'     => false,
                'lesson_id'   => $lesson->id,
                'lesson_type' => $lesson->type,
            ]);
        }

        return response()->json([
            'started'  => true,
            'progress' => $progress,
            'detail'   => $progress->completion_detail,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Resume Learning
    // ──────────────────────────────────────────────────────────────────

    /**
     * GET /courses/{course}/resume
     * Get resume learning state (last lesson, position, next suggestion).
     */
    public function resume(Request $request, Course $course): JsonResponse
    {
        return response()->json(
            $this->progressTracker->getResumeState($request->user(), $course)
        );
    }

    /**
     * GET /courses/{course}/next-lesson
     * Suggest the next lesson to take.
     */
    public function nextLesson(Request $request, Course $course): JsonResponse
    {
        $lesson = $this->progressTracker->suggestNextLesson($request->user(), $course->id);

        if (!$lesson) {
            return response()->json([
                'completed' => true,
                'message'   => 'All lessons completed! 🎉',
            ]);
        }

        return response()->json([
            'completed' => false,
            'lesson'    => [
                'id'       => $lesson->id,
                'title'    => $lesson->title,
                'slug'     => $lesson->slug,
                'type'     => $lesson->type,
                'duration' => $lesson->formatted_duration,
                'section'  => $lesson->section?->title,
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Activity Heatmap & Analytics
    // ──────────────────────────────────────────────────────────────────

    /**
     * GET /progress/heatmap
     * Get activity heatmap data for the authenticated user.
     */
    public function heatmap(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days'      => ['sometimes', 'integer', 'min:30', 'max:365'],
            'course_id' => ['sometimes', 'exists:courses,id'],
        ]);

        return response()->json(
            $this->progressTracker->getActivityHeatmap(
                $request->user(),
                $validated['days'] ?? 365,
                $validated['course_id'] ?? null
            )
        );
    }

    /**
     * GET /courses/{course}/progress/activity
     * Get activity breakdown for a specific course.
     */
    public function activityBreakdown(Request $request, Course $course): JsonResponse
    {
        return response()->json(
            $this->progressTracker->getActivityBreakdown($request->user(), $course->id)
        );
    }

    // ──────────────────────────────────────────────────────────────────
    // Progress Export
    // ──────────────────────────────────────────────────────────────────

    /**
     * GET /courses/{course}/progress/export
     * Export progress data for certificate generation.
     */
    public function exportForCertificate(Request $request, Course $course): JsonResponse
    {
        $exportData = $this->progressTracker->exportForCertificate(
            $request->user(),
            $course
        );

        return response()->json($exportData);
    }
}
