<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LessonNoteController extends Controller
{
    /**
     * List the authenticated user's notes for a lesson.
     */
    public function index(Course $course, Lesson $lesson)
    {
        abort_unless($lesson->course_id === $course->id, 404);

        $notes = LessonNote::where('user_id', Auth::id())
            ->where('lesson_id', $lesson->id)
            ->orderBy('timestamp_seconds')
            ->orderBy('created_at')
            ->get();

        return response()->json($notes);
    }

    /**
     * Create a new note for the lesson.
     */
    public function store(Request $request, Course $course, Lesson $lesson)
    {
        abort_unless($lesson->course_id === $course->id, 404);

        $validated = $request->validate([
            'content'           => ['required', 'string', 'max:5000'],
            'timestamp_seconds' => ['nullable', 'integer', 'min:0'],
            'color'             => ['nullable', Rule::in(['yellow', 'blue', 'green', 'red', 'purple'])],
        ]);

        $note = LessonNote::create([
            'user_id'           => Auth::id(),
            'lesson_id'         => $lesson->id,
            'course_id'         => $course->id,
            'content'           => $validated['content'],
            'timestamp_seconds' => $validated['timestamp_seconds'] ?? null,
            'color'             => $validated['color'] ?? 'yellow',
        ]);

        return response()->json($note->fresh(), 201);
    }

    /**
     * Update an existing note.  Only the owner may edit.
     */
    public function update(Request $request, Course $course, Lesson $lesson, LessonNote $note)
    {
        abort_unless($lesson->course_id === $course->id, 404);
        abort_unless($note->user_id === Auth::id() && $note->lesson_id === $lesson->id, 403);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
            'color'   => ['nullable', Rule::in(['yellow', 'blue', 'green', 'red', 'purple'])],
        ]);

        $note->update([
            'content' => $validated['content'],
            'color'   => $validated['color'] ?? $note->color,
        ]);

        return response()->json($note->fresh());
    }

    /**
     * Delete a note.  Only the owner may delete.
     */
    public function destroy(Course $course, Lesson $lesson, LessonNote $note)
    {
        abort_unless($lesson->course_id === $course->id, 404);
        abort_unless($note->user_id === Auth::id() && $note->lesson_id === $lesson->id, 403);

        $note->delete();

        return response()->json(['deleted' => true]);
    }
}
