<?php

namespace App\Services;

use App\Events\CourseCompleted;
use App\Events\ProgressMilestoneReached;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningActivityLog;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\ProgressMilestone;
use App\Models\QuizAttempt;
use App\Models\AssignmentSubmission;
use App\Models\UserCourseState;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProgressTracker
{
    // ──────────────────────────────────────────────────────────────────
    // 1. Lesson Completion
    // ──────────────────────────────────────────────────────────────────

    /**
     * Track progress for a lesson based on its type.
     * This is the main entry point — it delegates to type-specific handlers.
     */
    public function trackProgress(User $user, Lesson $lesson, array $data = []): LessonProgress
    {
        $progress = LessonProgress::firstOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            ['course_id' => $lesson->course_id, 'first_accessed_at' => now()]
        );

        // Increment interaction count
        $progress->increment('interaction_count');
        $progress->last_accessed_at = now();

        // Delegate to type-specific handler
        $progress = match ($lesson->type) {
            'video'        => $this->trackVideoProgress($progress, $data),
            'quiz'         => $this->trackQuizProgress($progress, $data),
            'assignment'   => $this->trackAssignmentProgress($progress, $data),
            'pdf'          => $this->trackPdfProgress($progress, $data),
            default        => $this->trackGenericProgress($progress, $data),
        };

        // Add time spent
        if (isset($data['time_spent_seconds'])) {
            $progress->time_spent_seconds += (int) $data['time_spent_seconds'];
        }

        $progress->save();

        // Log activity
        $this->logActivity($user, $lesson, $data);

        // Update course state (resume learning)
        $this->updateCourseState($user, $lesson, $data);

        // Update enrollment last_activity_at
        $this->updateEnrollmentActivity($user, $lesson->course_id);

        // Recalculate overall course progress
        $this->recalculateCourseProgress($user, $lesson->course_id);

        return $progress->fresh();
    }

    /**
     * Mark a lesson as complete (shorthand).
     */
    public function markLessonComplete(User $user, Lesson $lesson): LessonProgress
    {
        return $this->trackProgress($user, $lesson, ['is_completed' => true]);
    }

    /**
     * Mark a lesson as incomplete (undo completion).
     */
    public function markLessonIncomplete(User $user, Lesson $lesson): LessonProgress
    {
        $progress = LessonProgress::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        if ($progress) {
            $progress->update([
                'is_completed' => false,
                'completed_at' => null,
            ]);

            $this->recalculateCourseProgress($user, $lesson->course_id);
        }

        return $progress ?? new LessonProgress();
    }

    // ──────────────────────────────────────────────────────────────────
    // 2. Type-Specific Tracking
    // ──────────────────────────────────────────────────────────────────

    /**
     * Track video lesson progress: watch time, resume position.
     */
    protected function trackVideoProgress(LessonProgress $progress, array $data): LessonProgress
    {
        if (isset($data['watch_time_seconds'])) {
            $progress->watch_time_seconds += (int) $data['watch_time_seconds'];
        }

        if (isset($data['video_resume_position'])) {
            $progress->video_resume_position = (int) $data['video_resume_position'];
        }

        if (isset($data['video_total_duration'])) {
            $progress->video_total_duration = (int) $data['video_total_duration'];
        }

        if (isset($data['video_play_count'])) {
            $progress->video_play_count += 1;
        }

        // Calculate watched percentage
        if ($progress->video_total_duration > 0) {
            $progress->video_watched_percentage = min(
                100,
                round(($progress->watch_time_seconds / $progress->video_total_duration) * 100, 2)
            );
        }

        // Auto-complete if watched >= 90% of the video
        if ($progress->video_watched_percentage >= 90 && !$progress->is_completed) {
            $progress->is_completed = true;
            $progress->completed_at = now();
        }

        // Explicit completion override
        if (isset($data['is_completed']) && $data['is_completed']) {
            $progress->is_completed = true;
            $progress->completed_at = $progress->completed_at ?? now();
        }

        return $progress;
    }

    /**
     * Track quiz lesson progress: attempts and scores.
     */
    protected function trackQuizProgress(LessonProgress $progress, array $data): LessonProgress
    {
        if (isset($data['quiz_attempt_id'])) {
            $attempt = QuizAttempt::find($data['quiz_attempt_id']);

            if ($attempt && $attempt->completed_at) {
                $progress->quiz_attempts_count += 1;
                $progress->quiz_latest_score = $attempt->percentage;

                // Update best score
                if (is_null($progress->quiz_best_score) || $attempt->percentage > $progress->quiz_best_score) {
                    $progress->quiz_best_score = $attempt->percentage;
                }

                $progress->quiz_passed = $progress->quiz_passed || $attempt->passed;

                // Mark completed if passed
                if ($attempt->passed && !$progress->is_completed) {
                    $progress->is_completed = true;
                    $progress->completed_at = now();
                }
            }
        }

        // Manual score update
        if (isset($data['quiz_score'])) {
            $progress->quiz_latest_score = (float) $data['quiz_score'];
            $progress->quiz_attempts_count = max(1, $progress->quiz_attempts_count);

            if (is_null($progress->quiz_best_score) || $data['quiz_score'] > $progress->quiz_best_score) {
                $progress->quiz_best_score = (float) $data['quiz_score'];
            }
        }

        if (isset($data['quiz_passed']) && $data['quiz_passed']) {
            $progress->quiz_passed = true;
            if (!$progress->is_completed) {
                $progress->is_completed = true;
                $progress->completed_at = now();
            }
        }

        if (isset($data['is_completed']) && $data['is_completed']) {
            $progress->is_completed = true;
            $progress->completed_at = $progress->completed_at ?? now();
        }

        return $progress;
    }

    /**
     * Track assignment lesson progress: submission status.
     */
    protected function trackAssignmentProgress(LessonProgress $progress, array $data): LessonProgress
    {
        if (isset($data['assignment_status'])) {
            $progress->assignment_status = $data['assignment_status'];
        }

        if (isset($data['assignment_score'])) {
            $progress->assignment_score = (float) $data['assignment_score'];
        }

        if (isset($data['submission_id'])) {
            $submission = AssignmentSubmission::find($data['submission_id']);

            if ($submission) {
                $progress->assignment_status = $submission->status;
                $progress->assignment_score = $submission->score_percentage;

                // Mark as completed when graded
                if ($submission->status === 'graded' && !$progress->is_completed) {
                    $progress->is_completed = true;
                    $progress->completed_at = now();
                }
            }
        }

        if (isset($data['is_completed']) && $data['is_completed']) {
            $progress->is_completed = true;
            $progress->completed_at = $progress->completed_at ?? now();
        }

        return $progress;
    }

    /**
     * Track PDF lesson progress: view duration, pages viewed.
     */
    protected function trackPdfProgress(LessonProgress $progress, array $data): LessonProgress
    {
        if (isset($data['view_duration_seconds'])) {
            $progress->pdf_view_duration_seconds += (int) $data['view_duration_seconds'];
        }

        if (isset($data['pages_viewed'])) {
            $progress->pdf_pages_viewed = max($progress->pdf_pages_viewed, (int) $data['pages_viewed']);
        }

        if (isset($data['total_pages'])) {
            $progress->pdf_total_pages = (int) $data['total_pages'];
        }

        // Auto-complete if all pages viewed
        if ($progress->pdf_total_pages > 0 && $progress->pdf_pages_viewed >= $progress->pdf_total_pages && !$progress->is_completed) {
            $progress->is_completed = true;
            $progress->completed_at = now();
        }

        if (isset($data['is_completed']) && $data['is_completed']) {
            $progress->is_completed = true;
            $progress->completed_at = $progress->completed_at ?? now();
        }

        return $progress;
    }

    /**
     * Track generic lesson (text, article, etc.) progress.
     */
    protected function trackGenericProgress(LessonProgress $progress, array $data): LessonProgress
    {
        if (isset($data['is_completed']) && $data['is_completed']) {
            $progress->is_completed = true;
            $progress->completed_at = $progress->completed_at ?? now();
        }

        return $progress;
    }

    // ──────────────────────────────────────────────────────────────────
    // 3. Course Progress Calculation
    // ──────────────────────────────────────────────────────────────────

    /**
     * Recalculate overall course progress percentage and check milestones.
     */
    public function recalculateCourseProgress(User $user, int $courseId): float
    {
        $totalLessons = Lesson::where('course_id', $courseId)
            ->where('is_published', true)
            ->count();

        $completedLessons = LessonProgress::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->where('is_completed', true)
            ->count();

        $percentage = $totalLessons > 0
            ? round(($completedLessons / $totalLessons) * 100, 2)
            : 0;

        // Update enrollment
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->whereNotIn('status', [Enrollment::STATUS_CANCELLED, Enrollment::STATUS_EXPIRED])
            ->first();

        if ($enrollment) {
            $previousPercentage = (float) $enrollment->progress_percentage;
            $enrollment->update([
                'progress_percentage' => $percentage,
                'last_activity_at'    => now(),
            ]);

            // Auto-transition active → in-progress
            if ($enrollment->status === Enrollment::STATUS_ACTIVE && $percentage > 0) {
                $enrollment->update(['status' => Enrollment::STATUS_IN_PROGRESS]);
            }

            // Check milestones
            $this->checkMilestones($user, $enrollment, $previousPercentage, $percentage);

            // Course completed
            if ($percentage >= 100 && $previousPercentage < 100) {
                $enrollment->markCompleted();
                event(new CourseCompleted($user, $enrollment->fresh()));
            }
        }

        return $percentage;
    }

    /**
     * Get detailed course progress breakdown for a user.
     */
    public function getCourseProgress(User $user, Course $course): array
    {
        $sections = $course->sections()
            ->with(['lessons' => function ($q) {
                $q->published()->ordered();
            }])
            ->ordered()
            ->get();

        $progressRecords = LessonProgress::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->get()
            ->keyBy('lesson_id');

        $totalLessons = 0;
        $completedLessons = 0;
        $totalTimeSpent = 0;
        $sectionProgress = [];

        foreach ($sections as $section) {
            $sectionLessons = [];
            $sectionCompleted = 0;
            $sectionTotal = $section->lessons->count();

            foreach ($section->lessons as $lesson) {
                $totalLessons++;
                $lp = $progressRecords->get($lesson->id);
                $isCompleted = $lp ? $lp->is_completed : false;

                if ($isCompleted) {
                    $completedLessons++;
                    $sectionCompleted++;
                }

                $totalTimeSpent += $lp ? $lp->time_spent_seconds : 0;

                $sectionLessons[] = [
                    'lesson_id'     => $lesson->id,
                    'title'         => $lesson->title,
                    'slug'          => $lesson->slug,
                    'type'          => $lesson->type,
                    'type_label'    => $lesson->type_label,
                    'type_icon'     => $lesson->type_icon,
                    'duration'      => $lesson->formatted_duration,
                    'is_completed'  => $isCompleted,
                    'completed_at'  => $lp?->completed_at?->toISOString(),
                    'detail'        => $lp ? $lp->completion_detail : ['status' => 'not-started'],
                    'time_spent'    => $lp ? $lp->formatted_time_spent : '0s',
                    'last_accessed' => $lp?->last_accessed_at?->toISOString(),
                ];
            }

            $sectionPercentage = $sectionTotal > 0
                ? round(($sectionCompleted / $sectionTotal) * 100, 1)
                : 0;

            $sectionProgress[] = [
                'section_id'  => $section->id,
                'title'       => $section->title,
                'total'       => $sectionTotal,
                'completed'   => $sectionCompleted,
                'percentage'  => $sectionPercentage,
                'is_complete' => $sectionCompleted === $sectionTotal && $sectionTotal > 0,
                'lessons'     => $sectionLessons,
            ];
        }

        $overallPercentage = $totalLessons > 0
            ? round(($completedLessons / $totalLessons) * 100, 1)
            : 0;

        // Get milestones
        $milestones = ProgressMilestone::forUser($user->id)
            ->forCourse($course->id)
            ->orderBy('milestone')
            ->get()
            ->map(fn($m) => [
                'milestone'  => $m->milestone,
                'reached_at' => $m->reached_at->toISOString(),
            ]);

        return [
            'overall' => [
                'total_lessons'    => $totalLessons,
                'completed'        => $completedLessons,
                'remaining'        => $totalLessons - $completedLessons,
                'percentage'       => $overallPercentage,
                'total_time_spent' => $this->formatSeconds($totalTimeSpent),
                'time_spent_seconds' => $totalTimeSpent,
                'milestones'       => $milestones,
            ],
            'sections' => $sectionProgress,
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // 4. Milestone Tracking
    // ──────────────────────────────────────────────────────────────────

    /**
     * Check and fire milestone events at 25%, 50%, 75%, 100%.
     */
    protected function checkMilestones(User $user, Enrollment $enrollment, float $previousPct, float $currentPct): void
    {
        $milestones = [25, 50, 75, 100];

        foreach ($milestones as $milestone) {
            if ($previousPct < $milestone && $currentPct >= $milestone) {
                // Record milestone
                ProgressMilestone::firstOrCreate(
                    [
                        'user_id'   => $user->id,
                        'course_id' => $enrollment->course_id,
                        'milestone' => $milestone,
                    ],
                    ['reached_at' => now()]
                );

                // Fire events
                event(new ProgressMilestoneReached($user, $enrollment, $milestone, $currentPct));
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // 5. Resume Learning / Course State
    // ──────────────────────────────────────────────────────────────────

    /**
     * Update the user's course state (last lesson, video position, next lesson).
     */
    protected function updateCourseState(User $user, Lesson $lesson, array $data = []): UserCourseState
    {
        $state = UserCourseState::updateOrCreate(
            ['user_id' => $user->id, 'course_id' => $lesson->course_id],
            [
                'last_lesson_id'     => $lesson->id,
                'last_video_position' => $lesson->type === 'video'
                    ? ($data['video_resume_position'] ?? 0) : 0,
                'last_accessed_at'   => now(),
            ]
        );

        // Always update next lesson
        $nextLesson = $this->suggestNextLesson($user, $lesson->course_id);
        $state->update(['next_lesson_id' => $nextLesson?->id]);

        return $state;
    }

    /**
     * Get the resume learning state for a user–course pair.
     */
    public function getResumeState(User $user, Course $course): array
    {
        $state = UserCourseState::with([
            'lastLesson:id,title,slug,type,duration_minutes,section_id',
            'lastLesson.section:id,title',
            'nextLesson:id,title,slug,type,duration_minutes,section_id',
            'nextLesson.section:id,title',
        ])
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if (!$state) {
            // User hasn't started — suggest first lesson
            $firstLesson = Lesson::where('course_id', $course->id)
                ->published()
                ->ordered()
                ->first();

            return [
                'has_started'       => false,
                'last_lesson'       => null,
                'next_lesson'       => $firstLesson ? [
                    'id'       => $firstLesson->id,
                    'title'    => $firstLesson->title,
                    'slug'     => $firstLesson->slug,
                    'type'     => $firstLesson->type,
                    'duration' => $firstLesson->formatted_duration,
                    'section'  => $firstLesson->section?->title,
                ] : null,
                'video_position'    => 0,
                'last_accessed_at'  => null,
            ];
        }

        // Get video progress for last lesson if it's a video
        $videoPosition = $state->last_video_position;
        if ($state->lastLesson && $state->lastLesson->type === 'video') {
            $lp = LessonProgress::where('user_id', $user->id)
                ->where('lesson_id', $state->last_lesson_id)
                ->first();
            if ($lp) {
                $videoPosition = $lp->video_resume_position;
            }
        }

        return [
            'has_started'       => true,
            'last_lesson'       => $state->lastLesson ? [
                'id'       => $state->lastLesson->id,
                'title'    => $state->lastLesson->title,
                'slug'     => $state->lastLesson->slug,
                'type'     => $state->lastLesson->type,
                'duration' => $state->lastLesson->formatted_duration,
                'section'  => $state->lastLesson->section?->title,
            ] : null,
            'next_lesson'       => $state->nextLesson ? [
                'id'       => $state->nextLesson->id,
                'title'    => $state->nextLesson->title,
                'slug'     => $state->nextLesson->slug,
                'type'     => $state->nextLesson->type,
                'duration' => $state->nextLesson->formatted_duration,
                'section'  => $state->nextLesson->section?->title,
            ] : null,
            'video_position'       => $videoPosition,
            'formatted_position'   => $this->formatVideoPosition($videoPosition),
            'last_accessed_at'     => $state->last_accessed_at?->toISOString(),
            'last_accessed_human'  => $state->last_accessed_at?->diffForHumans(),
        ];
    }

    /**
     * Suggest the next lesson the user should take.
     */
    public function suggestNextLesson(User $user, int $courseId): ?Lesson
    {
        // Get all published lessons in order
        $lessons = Lesson::where('course_id', $courseId)
            ->published()
            ->orderBy('section_id')
            ->orderBy('sort_order')
            ->get();

        // Get completed lesson IDs
        $completedIds = LessonProgress::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->where('is_completed', true)
            ->pluck('lesson_id')
            ->toArray();

        // Find the first incomplete lesson
        foreach ($lessons as $lesson) {
            if (!in_array($lesson->id, $completedIds)) {
                return $lesson;
            }
        }

        // All complete — return null
        return null;
    }

    // ──────────────────────────────────────────────────────────────────
    // 6. Activity Logging (for Heatmap)
    // ──────────────────────────────────────────────────────────────────

    /**
     * Log a learning activity for the heatmap.
     */
    protected function logActivity(User $user, Lesson $lesson, array $data = []): void
    {
        $activityType = match ($lesson->type) {
            'video'      => LearningActivityLog::TYPE_VIDEO_WATCH,
            'quiz'       => LearningActivityLog::TYPE_QUIZ_ATTEMPT,
            'assignment' => LearningActivityLog::TYPE_ASSIGNMENT_SUBMIT,
            'pdf'        => LearningActivityLog::TYPE_PDF_READ,
            default      => LearningActivityLog::TYPE_LESSON_VIEW,
        };

        $duration = $data['time_spent_seconds']
            ?? $data['watch_time_seconds']
            ?? $data['view_duration_seconds']
            ?? 0;

        LearningActivityLog::create([
            'user_id'          => $user->id,
            'course_id'        => $lesson->course_id,
            'lesson_id'        => $lesson->id,
            'activity_type'    => $activityType,
            'duration_seconds' => $duration,
            'activity_date'    => now()->toDateString(),
            'metadata'         => [
                'lesson_title' => $lesson->title,
                'lesson_type'  => $lesson->type,
            ],
        ]);
    }

    /**
     * Get activity heatmap for a user (last N days).
     */
    public function getActivityHeatmap(User $user, int $days = 365, ?int $courseId = null): array
    {
        $startDate = now()->subDays($days)->toDateString();

        $query = LearningActivityLog::where('user_id', $user->id)
            ->where('activity_date', '>=', $startDate);

        if ($courseId) {
            $query->where('course_id', $courseId);
        }

        // Group by date, sum duration
        $activities = $query
            ->selectRaw('activity_date, SUM(duration_seconds) as total_seconds, COUNT(*) as activity_count')
            ->groupBy('activity_date')
            ->orderBy('activity_date')
            ->get();

        $heatmapData = [];
        foreach ($activities as $activity) {
            $heatmapData[] = [
                'date'           => $activity->activity_date,
                'total_seconds'  => (int) $activity->total_seconds,
                'total_minutes'  => round($activity->total_seconds / 60, 1),
                'activity_count' => (int) $activity->activity_count,
                'intensity'      => $this->calculateIntensity($activity->total_seconds),
            ];
        }

        // Calculate streak
        $streak = $this->calculateStreak($activities->pluck('activity_date')->toArray());

        // Calculate summary stats
        $totalSeconds = $activities->sum('total_seconds');
        $totalDays = $activities->count();

        return [
            'heatmap'               => $heatmapData,
            'total_active_days'     => $totalDays,
            'total_learning_time'   => $this->formatSeconds((int) $totalSeconds),
            'total_seconds'         => (int) $totalSeconds,
            'current_streak'        => $streak['current'],
            'longest_streak'        => $streak['longest'],
            'average_daily_minutes' => $totalDays > 0
                ? round(($totalSeconds / 60) / $totalDays, 1) : 0,
        ];
    }

    /**
     * Get activity breakdown by type for a user and course.
     */
    public function getActivityBreakdown(User $user, int $courseId): array
    {
        return LearningActivityLog::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->selectRaw('activity_type, SUM(duration_seconds) as total_seconds, COUNT(*) as count')
            ->groupBy('activity_type')
            ->get()
            ->map(fn($row) => [
                'type'          => $row->activity_type,
                'label'         => $this->activityTypeLabel($row->activity_type),
                'total_time'    => $this->formatSeconds((int) $row->total_seconds),
                'total_seconds' => (int) $row->total_seconds,
                'count'         => (int) $row->count,
            ])
            ->toArray();
    }

    // ──────────────────────────────────────────────────────────────────
    // 7. Progress Visualization Data
    // ──────────────────────────────────────────────────────────────────

    /**
     * Get circular progress data for enrollment progress indicators.
     */
    public function getCircularProgressData(User $user, Course $course): array
    {
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        $percentage = $enrollment ? (float) $enrollment->progress_percentage : 0;

        // Calculate the SVG circle values (for a 100-unit circumference circle)
        $circumference = 2 * M_PI * 45; // radius = 45
        $dashOffset = $circumference * (1 - ($percentage / 100));

        return [
            'percentage'     => $percentage,
            'circumference'  => round($circumference, 2),
            'dash_offset'    => round($dashOffset, 2),
            'stroke_color'   => $this->getProgressColor($percentage),
            'status'         => $enrollment?->status ?? 'not-enrolled',
            'label'          => $percentage >= 100 ? 'Complete!' : number_format($percentage, 0) . '%',
        ];
    }

    /**
     * Get the module (section) completion checkmarks data.
     */
    public function getModuleCheckmarks(User $user, Course $course): array
    {
        $sections = $course->sections()->ordered()->get();
        $result = [];

        foreach ($sections as $section) {
            $lessonIds = Lesson::where('section_id', $section->id)
                ->published()
                ->pluck('id');

            $totalLessons = $lessonIds->count();
            $completedLessons = LessonProgress::where('user_id', $user->id)
                ->whereIn('lesson_id', $lessonIds)
                ->where('is_completed', true)
                ->count();

            $result[] = [
                'section_id'  => $section->id,
                'title'       => $section->title,
                'total'       => $totalLessons,
                'completed'   => $completedLessons,
                'percentage'  => $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 1) : 0,
                'is_complete' => $completedLessons === $totalLessons && $totalLessons > 0,
                'checkmark'   => $completedLessons === $totalLessons && $totalLessons > 0 ? '✅' : '⬜',
            ];
        }

        return $result;
    }

    // ──────────────────────────────────────────────────────────────────
    // 8. Progress Export for Certificates
    // ──────────────────────────────────────────────────────────────────

    /**
     * Export comprehensive progress data for certificate generation.
     */
    public function exportForCertificate(User $user, Course $course): array
    {
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if (!$enrollment || $enrollment->status !== Enrollment::STATUS_COMPLETED) {
            return ['eligible' => false, 'reason' => 'Course not yet completed.'];
        }

        $progressData = $this->getCourseProgress($user, $course);
        $activityBreakdown = $this->getActivityBreakdown($user, $course->id);

        // Get quiz scores
        $quizScores = LessonProgress::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereNotNull('quiz_best_score')
            ->with('lesson:id,title')
            ->get()
            ->map(fn($lp) => [
                'lesson'     => $lp->lesson?->title,
                'best_score' => $lp->quiz_best_score,
                'attempts'   => $lp->quiz_attempts_count,
                'passed'     => $lp->quiz_passed,
            ]);

        // Get assignment scores
        $assignmentScores = LessonProgress::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereNotNull('assignment_score')
            ->with('lesson:id,title')
            ->get()
            ->map(fn($lp) => [
                'lesson' => $lp->lesson?->title,
                'score'  => $lp->assignment_score,
                'status' => $lp->assignment_status,
            ]);

        // Average quiz score
        $avgQuizScore = $quizScores->avg('best_score');

        // Milestones
        $milestones = ProgressMilestone::forUser($user->id)
            ->forCourse($course->id)
            ->orderBy('milestone')
            ->get();

        return [
            'eligible'     => true,
            'student'      => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'course'       => [
                'id'         => $course->id,
                'title'      => $course->title,
                'instructor' => $course->instructor?->name,
                'level'      => $course->level,
                'duration'   => $course->formatted_duration,
            ],
            'enrollment'   => [
                'enrolled_at'  => $enrollment->enrolled_at?->format('F j, Y'),
                'completed_at' => $enrollment->completed_at?->format('F j, Y'),
                'days_to_complete' => $enrollment->enrolled_at && $enrollment->completed_at
                    ? $enrollment->enrolled_at->diffInDays($enrollment->completed_at) : null,
            ],
            'progress'     => [
                'total_lessons'    => $progressData['overall']['total_lessons'],
                'completed'        => $progressData['overall']['completed'],
                'total_time_spent' => $progressData['overall']['total_time_spent'],
            ],
            'scores'       => [
                'quiz_scores'        => $quizScores,
                'assignment_scores'  => $assignmentScores,
                'average_quiz_score' => $avgQuizScore !== null ? round($avgQuizScore, 1) : null,
            ],
            'milestones'   => $milestones->map(fn($m) => [
                'milestone'  => $m->milestone . '%',
                'reached_at' => $m->reached_at->format('F j, Y'),
            ]),
            'activity_breakdown' => $activityBreakdown,
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────────────────────────────────────

    protected function updateEnrollmentActivity(User $user, int $courseId): void
    {
        Enrollment::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->whereNotIn('status', [Enrollment::STATUS_CANCELLED, Enrollment::STATUS_EXPIRED])
            ->update(['last_activity_at' => now()]);
    }

    protected function calculateIntensity(int $totalSeconds): int
    {
        $minutes = $totalSeconds / 60;

        if ($minutes >= 120) return 4;
        if ($minutes >= 60) return 3;
        if ($minutes >= 30) return 2;
        if ($minutes > 0) return 1;
        return 0;
    }

    protected function calculateStreak(array $dates): array
    {
        if (empty($dates)) {
            return ['current' => 0, 'longest' => 0];
        }

        // Sort dates ascending
        sort($dates);

        $longestStreak = 1;
        $currentStreak = 1;
        $previousDate = \Carbon\Carbon::parse($dates[0]);
        $today = now()->startOfDay();

        for ($i = 1; $i < count($dates); $i++) {
            $currentDate = \Carbon\Carbon::parse($dates[$i]);
            $diff = $previousDate->diffInDays($currentDate);

            if ($diff === 1) {
                $currentStreak++;
                $longestStreak = max($longestStreak, $currentStreak);
            } elseif ($diff > 1) {
                $currentStreak = 1;
            }

            $previousDate = $currentDate;
        }

        // Check if current streak is alive (last activity today or yesterday)
        $lastDate = \Carbon\Carbon::parse(end($dates));
        if ($lastDate->diffInDays($today) > 1) {
            $currentStreak = 0;
        }

        return [
            'current' => $currentStreak,
            'longest' => $longestStreak,
        ];
    }

    protected function getProgressColor(float $percentage): string
    {
        if ($percentage >= 100) return '#10b981'; // emerald-500
        if ($percentage >= 75) return '#3b82f6';  // blue-500
        if ($percentage >= 50) return '#8b5cf6';  // violet-500
        if ($percentage >= 25) return '#f59e0b';  // amber-500
        return '#6b7280';                          // gray-500
    }

    protected function formatSeconds(int $totalSeconds): string
    {
        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }

    protected function formatVideoPosition(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        if ($h > 0) {
            return sprintf('%d:%02d:%02d', $h, $m, $s);
        }
        return sprintf('%d:%02d', $m, $s);
    }

    protected function activityTypeLabel(string $type): string
    {
        return match ($type) {
            LearningActivityLog::TYPE_LESSON_VIEW       => 'Lesson Views',
            LearningActivityLog::TYPE_VIDEO_WATCH       => 'Video Watching',
            LearningActivityLog::TYPE_QUIZ_ATTEMPT      => 'Quiz Attempts',
            LearningActivityLog::TYPE_ASSIGNMENT_SUBMIT => 'Assignments',
            LearningActivityLog::TYPE_PDF_READ          => 'PDF Reading',
            LearningActivityLog::TYPE_NOTE_ADDED        => 'Note Taking',
            LearningActivityLog::TYPE_DISCUSSION_POST   => 'Discussions',
            default                                     => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
