<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CurriculumController extends Controller
{
    private const LESSON_TYPES = [
        'video',
        'text',
        'pdf',
        'presentation',
        'audio',
        'external',
        'quiz',
        'assignment',
    ];

    public function addSection(Request $request, Course $course): JsonResponse
    {
        $this->authorizeInstructor($course);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $section = $course->sections()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'sort_order' => (($course->sections()->max('sort_order') ?? 0) + 1),
        ]);

        $course->update(['wizard_step' => max((int) $course->wizard_step, 3)]);

        return response()->json([
            'success' => true,
            'section' => $this->serializeSection($section->fresh()->load(['lessons' => fn ($q) => $q->ordered()])),
        ], 201);
    }

    public function addLessonToSection(Request $request, Course $course, Section $section): JsonResponse
    {
        $this->authorizeInstructor($course);
        $this->assertCourseSection($course, $section);

        $validated = $request->validate([
            'lesson_id' => [
                'nullable',
                Rule::exists('lessons', 'id')->where(fn ($q) => $q->where('course_id', $course->id)),
            ],
            'title' => ['required_without:lesson_id', 'nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(self::LESSON_TYPES)],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:14400'],
            'is_free_preview' => ['nullable', 'boolean'],
        ]);

        $lesson = DB::transaction(function () use ($validated, $course, $section) {
            $sortOrder = (($section->lessons()->max('sort_order') ?? 0) + 1);

            if (! empty($validated['lesson_id'])) {
                $lesson = Lesson::where('course_id', $course->id)->findOrFail($validated['lesson_id']);
                $lesson->update([
                    'section_id' => $section->id,
                    'sort_order' => $sortOrder,
                    'type' => $validated['type'] ?? $lesson->type,
                ]);

                return $lesson->fresh();
            }

            return $section->lessons()->create([
                'course_id' => $course->id,
                'title' => $validated['title'] ?? 'Untitled Lesson',
                'type' => $validated['type'] ?? 'video',
                'duration_minutes' => (int) ($validated['duration_minutes'] ?? 0),
                'is_free_preview' => (bool) ($validated['is_free_preview'] ?? false),
                'is_published' => true,
                'sort_order' => $sortOrder,
            ]);
        });

        return response()->json([
            'success' => true,
            'lesson' => $this->serializeLesson($lesson),
        ], 201);
    }

    public function reorderSections(Request $request, Course $course): JsonResponse
    {
        $this->authorizeInstructor($course);

        $validated = $request->validate([
            'section_ids' => ['required', 'array'],
            'section_ids.*' => [
                'required',
                Rule::exists('sections', 'id')->where(fn ($q) => $q->where('course_id', $course->id)),
            ],
        ]);

        if (empty($validated['section_ids'])) {
            return response()->json(['success' => true]);
        }

        DB::transaction(function () use ($validated, $course) {
            foreach ($validated['section_ids'] as $index => $sectionId) {
                Section::where('course_id', $course->id)
                    ->where('id', $sectionId)
                    ->update(['sort_order' => $index + 1]);
            }
        });

        return response()->json(['success' => true]);
    }

    public function reorderLessons(Request $request, Course $course): JsonResponse
    {
        $this->authorizeInstructor($course);

        $validated = $request->validate([
            'sections' => ['required', 'array'],
            'sections.*.id' => [
                'required',
                Rule::exists('sections', 'id')->where(fn ($q) => $q->where('course_id', $course->id)),
            ],
            'sections.*.lesson_ids' => ['nullable', 'array'],
            'sections.*.lesson_ids.*' => [
                'required',
                Rule::exists('lessons', 'id')->where(fn ($q) => $q->where('course_id', $course->id)),
            ],
        ]);

        if (empty($validated['sections'])) {
            return response()->json(['success' => true]);
        }

        DB::transaction(function () use ($validated, $course) {
            foreach ($validated['sections'] as $sectionData) {
                $sectionId = (int) $sectionData['id'];
                foreach (($sectionData['lesson_ids'] ?? []) as $index => $lessonId) {
                    Lesson::where('course_id', $course->id)
                        ->where('id', $lessonId)
                        ->update([
                            'section_id' => $sectionId,
                            'sort_order' => $index + 1,
                        ]);
                }
            }
        });

        return response()->json(['success' => true]);
    }

    public function deleteSection(Request $request, Course $course, Section $section): JsonResponse
    {
        $this->authorizeInstructor($course);
        $this->assertCourseSection($course, $section);

        $validated = $request->validate([
            'cascade' => ['required', Rule::in(['delete_lessons', 'move_lessons'])],
            'target_section_id' => [
                'nullable',
                Rule::requiredIf(fn () => $request->input('cascade') === 'move_lessons'),
                Rule::exists('sections', 'id')->where(fn ($q) => $q->where('course_id', $course->id)),
            ],
        ]);

        if (($validated['target_section_id'] ?? null) && (int) $validated['target_section_id'] === $section->id) {
            return response()->json([
                'message' => 'Target section must be different from the section being deleted.',
            ], 422);
        }

        $movedCount = 0;
        $deletedLessonsCount = 0;

        DB::transaction(function () use ($validated, $section, $course, &$movedCount, &$deletedLessonsCount) {
            if ($validated['cascade'] === 'move_lessons') {
                $targetSection = Section::where('course_id', $course->id)->findOrFail((int) $validated['target_section_id']);
                $startOrder = (($targetSection->lessons()->max('sort_order') ?? 0) + 1);

                $lessons = $section->lessons()->ordered()->get();
                foreach ($lessons as $offset => $lesson) {
                    $lesson->update([
                        'section_id' => $targetSection->id,
                        'sort_order' => $startOrder + $offset,
                    ]);
                }

                $movedCount = $lessons->count();
            } else {
                $deletedLessonsCount = $section->lessons()->count();
                $section->lessons()->delete();
            }

            $section->delete();
        });

        return response()->json([
            'success' => true,
            'moved_lessons' => $movedCount,
            'deleted_lessons' => $deletedLessonsCount,
        ]);
    }

    public function autoSave(Request $request, Course $course): JsonResponse
    {
        $this->authorizeInstructor($course);

        $validated = $request->validate([
            'sections' => ['required', 'array'],
            'sections.*.id' => [
                'required',
                Rule::exists('sections', 'id')->where(fn ($q) => $q->where('course_id', $course->id)),
            ],
            'sections.*.title' => ['required', 'string', 'max:255'],
            'sections.*.description' => ['nullable', 'string'],
            'sections.*.lessons' => ['nullable', 'array'],
            'sections.*.lessons.*.id' => [
                'required',
                Rule::exists('lessons', 'id')->where(fn ($q) => $q->where('course_id', $course->id)),
            ],
            'sections.*.lessons.*.title' => ['required', 'string', 'max:255'],
            'sections.*.lessons.*.type' => ['required', Rule::in(self::LESSON_TYPES)],
            'sections.*.lessons.*.duration_minutes' => ['nullable', 'integer', 'min:0', 'max:14400'],
            'sections.*.lessons.*.is_free_preview' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($validated, $course) {
            foreach ($validated['sections'] as $sectionIndex => $sectionData) {
                Section::where('course_id', $course->id)
                    ->where('id', $sectionData['id'])
                    ->update([
                        'title' => $sectionData['title'],
                        'description' => $sectionData['description'] ?? null,
                        'sort_order' => $sectionIndex + 1,
                    ]);

                foreach (($sectionData['lessons'] ?? []) as $lessonIndex => $lessonData) {
                    Lesson::where('course_id', $course->id)
                        ->where('id', $lessonData['id'])
                        ->update([
                            'section_id' => $sectionData['id'],
                            'title' => $lessonData['title'],
                            'type' => $lessonData['type'],
                            'duration_minutes' => (int) ($lessonData['duration_minutes'] ?? 0),
                            'is_free_preview' => (bool) ($lessonData['is_free_preview'] ?? false),
                            'sort_order' => $lessonIndex + 1,
                        ]);
                }
            }
        });

        $course->update(['wizard_step' => max((int) $course->wizard_step, 3)]);

        return response()->json([
            'success' => true,
            'saved_at' => now()->format('H:i:s'),
        ]);
    }

    public function export(Course $course): JsonResponse
    {
        $this->authorizeInstructor($course);

        $course->load([
            'sections' => fn ($q) => $q->ordered()->with(['lessons' => fn ($l) => $l->ordered()]),
        ]);

        $payload = [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
            ],
            'exported_at' => now()->toIso8601String(),
            'sections' => $course->sections->map(fn ($section) => $this->serializeSection($section))->values()->all(),
        ];

        return response()->json($payload);
    }

    public function import(Request $request, Course $course): JsonResponse
    {
        $this->authorizeInstructor($course);

        $validated = $request->validate([
            'mode' => ['required', Rule::in(['append', 'replace'])],
            'structure' => ['required', 'array'],
            'structure.sections' => ['required', 'array', 'min:1'],
            'structure.sections.*.title' => ['required', 'string', 'max:255'],
            'structure.sections.*.description' => ['nullable', 'string'],
            'structure.sections.*.lessons' => ['nullable', 'array'],
            'structure.sections.*.lessons.*.title' => ['required', 'string', 'max:255'],
            'structure.sections.*.lessons.*.type' => ['required', Rule::in(self::LESSON_TYPES)],
            'structure.sections.*.lessons.*.duration_minutes' => ['nullable', 'integer', 'min:0', 'max:14400'],
            'structure.sections.*.lessons.*.is_free_preview' => ['nullable', 'boolean'],
        ]);

        $sectionsData = $validated['structure']['sections'];

        DB::transaction(function () use ($validated, $course, $sectionsData) {
            if ($validated['mode'] === 'replace') {
                $course->sections()->with('lessons')->get()->each(function (Section $section) {
                    $section->lessons()->delete();
                    $section->delete();
                });
            }

            $sectionStartOrder = ($course->sections()->max('sort_order') ?? 0) + 1;

            foreach ($sectionsData as $sectionOffset => $sectionData) {
                $section = $course->sections()->create([
                    'title' => $sectionData['title'],
                    'description' => $sectionData['description'] ?? null,
                    'sort_order' => $sectionStartOrder + $sectionOffset,
                ]);

                foreach (($sectionData['lessons'] ?? []) as $lessonOffset => $lessonData) {
                    $section->lessons()->create([
                        'course_id' => $course->id,
                        'title' => $lessonData['title'],
                        'type' => $lessonData['type'],
                        'duration_minutes' => (int) ($lessonData['duration_minutes'] ?? 0),
                        'is_free_preview' => (bool) ($lessonData['is_free_preview'] ?? false),
                        'is_published' => true,
                        'sort_order' => $lessonOffset + 1,
                    ]);
                }
            }
        });

        $course->update(['wizard_step' => max((int) $course->wizard_step, 3)]);

        return response()->json([
            'success' => true,
            'message' => 'Curriculum imported successfully.',
            'sections' => $course->fresh()->sections()->ordered()->with(['lessons' => fn ($q) => $q->ordered()])->get()->map(
                fn (Section $section) => $this->serializeSection($section)
            )->values()->all(),
        ]);
    }

    private function serializeSection(Section $section): array
    {
        $section->loadMissing(['lessons' => fn ($q) => $q->ordered()]);

        return [
            'id' => $section->id,
            'title' => $section->title,
            'description' => $section->description,
            'sort_order' => $section->sort_order,
            'collapsed' => false,
            'lessons' => $section->lessons->map(fn (Lesson $lesson) => $this->serializeLesson($lesson))->values()->all(),
        ];
    }

    private function serializeLesson(Lesson $lesson): array
    {
        return [
            'id' => $lesson->id,
            'slug' => $lesson->slug,
            'title' => $lesson->title,
            'type' => $lesson->type,
            'duration_minutes' => (int) $lesson->duration_minutes,
            'is_free_preview' => (bool) $lesson->is_free_preview,
        ];
    }

    private function authorizeInstructor(Course $course): void
    {
        abort_unless(Auth::check() && $course->instructor_id === Auth::id(), 403);
    }

    private function assertCourseSection(Course $course, Section $section): void
    {
        abort_unless($section->course_id === $course->id, 404);
    }
}
