<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    public function index(Course $course, Section $section)
    {
        return response()->json(
            $section->lessons()->orderBy('sort_order')->get()
        );
    }

    public function store(Request $request, Course $course, Section $section)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'in:video,text,pdf,quiz,assignment'],
            'content' => ['nullable', 'string'],
            'video_url' => ['nullable', 'string', 'max:255'],
            'video_provider' => ['nullable', 'string', 'max:50'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer'],
            'is_free_preview' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'resources' => ['nullable', 'array'],
        ]);

        $validated['course_id'] = $course->id;
        $validated['sort_order'] = $validated['sort_order']
            ?? ($section->lessons()->max('sort_order') + 1);

        $lesson = $section->lessons()->create($validated);

        return response()->json($lesson, 201);
    }

    public function show(Course $course, Section $section, Lesson $lesson)
    {
        return response()->json($lesson);
    }

    public function update(Request $request, Course $course, Section $section, Lesson $lesson)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'type' => ['nullable', 'in:video,text,pdf,quiz,assignment'],
            'content' => ['nullable', 'string'],
            'video_url' => ['nullable', 'string', 'max:255'],
            'video_provider' => ['nullable', 'string', 'max:50'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer'],
            'is_free_preview' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'resources' => ['nullable', 'array'],
        ]);

        $lesson->update($validated);

        return response()->json($lesson);
    }

    public function destroy(Course $course, Section $section, Lesson $lesson)
    {
        $lesson->delete();

        return response()->json(['message' => 'Lesson deleted.']);
    }
}
