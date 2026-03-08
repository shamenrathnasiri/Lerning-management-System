<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\LessonProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LessonController extends Controller
{
    /**
     * Show a lesson to an enrolled (or free-preview eligible) user.
     */
    public function show(Course $course, Lesson $lesson)
    {
        abort_unless($lesson->course_id === $course->id, 404);
        abort_unless($lesson->is_published, 404);

        $user       = Auth::user();
        $enrollment = $user
            ? Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->first()
            : null;

        // Guests and non-enrolled users may only see free preview lessons
        abort_unless($lesson->is_free_preview || $enrollment, 403, 'Enroll in this course to access this lesson.');

        $lesson->load(['section', 'videoProcessingJob']);

        // Build sidepanel course outline (published lessons only)
        $outline = $course->sections()
            ->with(['lessons' => fn ($q) => $q->published()->ordered()])
            ->ordered()
            ->get();

        // Progress record for completion button / watch-time
        $progress = $user
            ? LessonProgress::where('user_id', $user->id)
                            ->where('lesson_id', $lesson->id)
                            ->first()
            : null;

        // All completed lesson IDs (for outline checkmarks)
        $completedIds = $user
            ? LessonProgress::where('user_id', $user->id)
                            ->where('course_id', $course->id)
                            ->where('is_completed', true)
                            ->pluck('lesson_id')
                            ->toArray()
            : [];

        // User's notes for this lesson, sorted by video timestamp
        $notes = $user
            ? LessonNote::where('user_id', $user->id)
                        ->where('lesson_id', $lesson->id)
                        ->orderBy('timestamp_seconds')
                        ->orderBy('created_at')
                        ->get()
            : collect();

        [$prevLesson, $nextLesson] = $this->adjacentLessons($outline, $lesson->id);

        return view('student.lessons.show', compact(
            'course', 'lesson', 'outline', 'progress',
            'completedIds', 'notes', 'prevLesson', 'nextLesson', 'enrollment'
        ));
    }

    /**
     * Toggle lesson completion for the authenticated student.
     */
    public function complete(Request $request, Course $course, Lesson $lesson)
    {
        abort_unless($lesson->course_id === $course->id, 404);

        $user       = Auth::user();
        $enrollment = Enrollment::where('user_id', $user->id)
                                ->where('course_id', $course->id)
                                ->firstOrFail();

        $progress = LessonProgress::firstOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            ['course_id' => $course->id, 'is_completed' => false, 'watch_time_seconds' => 0]
        );

        $nowComplete = ! $progress->is_completed;

        $progress->update([
            'is_completed' => $nowComplete,
            'completed_at' => $nowComplete ? now() : null,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'is_completed'        => $nowComplete,
                'progress_percentage' => $enrollment->fresh()->progress_percentage,
            ]);
        }

        return back()->with(
            'success',
            $nowComplete ? 'Lesson marked as complete!' : 'Lesson marked as incomplete.'
        );
    }

    /**
     * Track video watch time (called by the player every N seconds via AJAX).
     */
    public function trackProgress(Request $request, Course $course, Lesson $lesson)
    {
        abort_unless($lesson->course_id === $course->id, 404);

        $validated = $request->validate([
            'watch_time_seconds' => ['required', 'integer', 'min:0', 'max:864000'],
        ]);

        $progress = LessonProgress::firstOrCreate(
            ['user_id' => Auth::id(), 'lesson_id' => $lesson->id],
            ['course_id' => $course->id, 'is_completed' => false, 'watch_time_seconds' => 0]
        );

        // Only advance — never decrease (prevents rewinds resetting progress)
        if ($validated['watch_time_seconds'] > $progress->watch_time_seconds) {
            $progress->update(['watch_time_seconds' => $validated['watch_time_seconds']]);
        }

        return response()->json([
            'watch_time_seconds' => $progress->watch_time_seconds,
        ]);
    }

    /**
     * Return a short-lived signed URL for an S3-stored video quality.
     * Redirects the browser directly to the signed URL.
     */
    public function stream(Request $request, Course $course, Lesson $lesson)
    {
        abort_unless($lesson->course_id === $course->id, 404);
        abort_unless($lesson->type === 'video', 404);

        $user       = Auth::user();
        $enrollment = $user
            ? Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->first()
            : null;

        abort_unless($lesson->is_free_preview || $enrollment, 403);

        $quality  = $request->query('quality', 'original');
        $urls     = $lesson->video_quality_urls ?? [];
        $s3Key    = $urls[$quality] ?? $urls['original'] ?? $lesson->s3_key;

        abort_unless($s3Key, 404);

        /** @var \Illuminate\Filesystem\FilesystemAdapter $s3 */
        $s3        = \Illuminate\Support\Facades\Storage::disk('s3');
        $signedUrl = $s3->temporaryUrl($s3Key, now()->addHours(4));

        return redirect($signedUrl);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Return [previousLesson, nextLesson] relative to $currentId in the ordered outline.
     *
     * @param  \Illuminate\Support\Collection  $outline  Sections with loaded lessons
     * @param  int                             $currentId
     * @return array{Lesson|null, Lesson|null}
     */
    private function adjacentLessons($outline, int $currentId): array
    {
        $all   = $outline->flatMap(fn ($s) => $s->lessons)->values();
        $index = $all->search(fn ($l) => $l->id === $currentId);

        if ($index === false) {
            return [null, null];
        }

        return [
            $index > 0                   ? $all[$index - 1] : null,
            $index < $all->count() - 1   ? $all[$index + 1] : null,
        ];
    }
}
