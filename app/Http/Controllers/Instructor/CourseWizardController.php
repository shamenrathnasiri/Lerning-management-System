<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseVersion;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CourseWizardController extends Controller
{
    public function index(): View
    {
        $courses = Course::where('instructor_id', Auth::id())
            ->withCount(['sections', 'lessons', 'enrollments', 'versions'])
            ->latest()
            ->paginate(12);

        return view('instructor.courses.index', compact('courses'));
    }

    public function create(): RedirectResponse
    {
        $course = Course::create([
            'instructor_id' => Auth::id(),
            'title' => 'Untitled Course',
            'status' => 'draft',
            'wizard_step' => 1,
            'certificate_template' => 'classic',
        ]);

        $this->recordVersion($course, 'course-created');

        return redirect()->route('instructor.courses.wizard', [$course, 'step' => 1]);
    }

    public function wizard(Course $course, Request $request): View
    {
        $this->authorizeInstructor($course);

        $step = (int) $request->get('step', $course->wizard_step ?? 1);
        $step = max(1, min(4, $step));

        $data = [
            'course' => $course,
            'step' => $step,
            'availableLanguages' => [
                'en' => 'English',
                'es' => 'Spanish',
                'fr' => 'French',
                'de' => 'German',
                'ar' => 'Arabic',
                'hi' => 'Hindi',
            ],
            'certificateTemplates' => ['classic', 'modern', 'minimal', 'academic'],
        ];

        switch ($step) {
            case 1:
                $data['categories'] = Category::active()->rootLevel()->ordered()
                    ->with(['children' => fn($q) => $q->active()->ordered()])
                    ->get();
                $data['tags'] = Tag::orderBy('name')->get();
                $data['selectedTags'] = $course->tags()->pluck('tags.id')->toArray();
                break;

            case 2:
                break;

            case 3:
                $course->load([
                    'sections' => fn($q) => $q->ordered()->with(['lessons' => fn($l) => $l->ordered()]),
                ]);
                break;

            case 4:
                $data['versions'] = $course->versions()->with('actor:id,name')->limit(25)->get();
                break;
        }

        return view('instructor.courses.wizard', $data);
    }

    public function saveStep1(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeInstructor($course);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('courses', 'slug')->ignore($course->id)],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
            'level' => ['required', 'in:beginner,intermediate,advanced,all_levels'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'is_free' => ['nullable'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($request->hasFile('thumbnail')) {
            if ($course->thumbnail) {
                Storage::disk('public')->delete($course->thumbnail);
            }
            $validated['thumbnail'] = $request->file('thumbnail')->store('courses/thumbnails', 'public');
        }

        if (empty($validated['slug'])) {
            unset($validated['slug']);
        }

        $validated['is_free'] = $request->boolean('is_free');
        if ($validated['is_free']) {
            $validated['price'] = 0;
            $validated['discount_price'] = null;
        } elseif (
            isset($validated['discount_price'], $validated['price'])
            && $validated['discount_price'] >= $validated['price']
        ) {
            return back()->withErrors(['discount_price' => 'Discount price must be lower than the regular price.'])->withInput();
        }

        $validated['wizard_step'] = max($course->wizard_step, 2);

        $tags = $validated['tags'] ?? [];
        unset($validated['tags']);

        $course->update($validated);
        $course->tags()->sync($tags);
        $this->recordVersion($course, 'step-1-basic-information');

        return redirect()->route('instructor.courses.wizard', [$course, 'step' => 2])
            ->with('success', 'Basic information saved!');
    }

    public function saveStep2(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeInstructor($course);

        $validated = $request->validate([
            'description' => ['nullable', 'string'],
            'requirements' => ['nullable', 'array'],
            'requirements.*' => ['nullable', 'string', 'max:500'],
            'what_you_will_learn' => ['nullable', 'array'],
            'what_you_will_learn.*' => ['nullable', 'string', 'max:500'],
            'target_audience' => ['nullable', 'array'],
            'target_audience.*' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['requirements'] = array_values(array_filter($validated['requirements'] ?? [], fn($item) => filled($item)));
        $validated['what_you_will_learn'] = array_values(array_filter($validated['what_you_will_learn'] ?? [], fn($item) => filled($item)));
        $validated['target_audience'] = array_values(array_filter($validated['target_audience'] ?? [], fn($item) => filled($item)));
        $validated['wizard_step'] = max($course->wizard_step, 3);

        $course->update($validated);
        $this->recordVersion($course, 'step-2-course-content');

        return redirect()->route('instructor.courses.wizard', [$course, 'step' => 3])
            ->with('success', 'Course content saved!');
    }

    public function addSection(Request $request, Course $course): JsonResponse
    {
        $this->authorizeInstructor($course);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['sort_order'] = (($course->sections()->max('sort_order') ?? 0) + 1);
        $section = $course->sections()->create($validated);
        $course->update(['wizard_step' => max($course->wizard_step, 3)]);
        $this->recordVersion($course, 'section-added');

        return response()->json([
            'success' => true,
            'section' => $section->fresh(),
        ], 201);
    }

    public function updateSection(Request $request, Course $course, Section $section): JsonResponse
    {
        $this->authorizeInstructor($course);
        $this->assertCourseSection($course, $section);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $section->update($validated);
        $this->recordVersion($course, 'section-updated');

        return response()->json(['success' => true, 'section' => $section]);
    }

    public function deleteSection(Course $course, Section $section): JsonResponse
    {
        $this->authorizeInstructor($course);
        $this->assertCourseSection($course, $section);

        $section->lessons()->delete();
        $section->delete();
        $this->recordVersion($course, 'section-deleted');

        return response()->json(['success' => true]);
    }

    public function addLesson(Request $request, Course $course, Section $section): JsonResponse
    {
        $this->authorizeInstructor($course);
        $this->assertCourseSection($course, $section);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:video,text,quiz,assignment'],
            'content' => ['nullable', 'string'],
            'video_url' => ['nullable', 'string', 'max:500'],
            'video_provider' => ['nullable', 'in:youtube,vimeo,upload'],
            'video_file' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:204800'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'is_free_preview' => ['nullable'],
        ]);

        $validated['course_id'] = $course->id;
        $validated['is_free_preview'] = $request->boolean('is_free_preview');
        $validated['is_published'] = true;
        $validated['sort_order'] = (($section->lessons()->max('sort_order') ?? 0) + 1);

        if ($request->hasFile('video_file')) {
            $validated['video_provider'] = 'upload';
            $validated['video_url'] = $request->file('video_file')->store('courses/videos', 'public');
        }

        $lesson = $section->lessons()->create($validated);
        $this->recordVersion($course, 'lesson-added');

        return response()->json([
            'success' => true,
            'lesson' => $lesson->fresh(),
        ], 201);
    }

    public function bulkAddLessons(Request $request, Course $course, Section $section): JsonResponse
    {
        $this->authorizeInstructor($course);
        $this->assertCourseSection($course, $section);

        $validated = $request->validate([
            'titles' => ['required', 'string'],
            'type' => ['required', 'in:video,text,quiz,assignment'],
            'is_free_preview' => ['nullable'],
        ]);

        $titles = preg_split('/\r\n|\r|\n/', $validated['titles']) ?: [];
        $titles = array_values(array_filter(array_map('trim', $titles), fn($line) => $line !== ''));

        if (empty($titles)) {
            return response()->json(['success' => false, 'message' => 'No valid lesson titles were provided.'], 422);
        }

        $startOrder = ($section->lessons()->max('sort_order') ?? 0) + 1;
        $lessons = [];

        foreach ($titles as $index => $title) {
            $lessons[] = $section->lessons()->create([
                'course_id' => $course->id,
                'title' => $title,
                'type' => $validated['type'],
                'is_free_preview' => $request->boolean('is_free_preview'),
                'is_published' => true,
                'sort_order' => $startOrder + $index,
            ]);
        }

        $this->recordVersion($course, 'lessons-bulk-created');

        return response()->json([
            'success' => true,
            'count' => count($lessons),
            'lessons' => collect($lessons)->map(fn($lesson) => $lesson->fresh())->values(),
        ], 201);
    }

    public function updateLesson(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $this->authorizeInstructor($course);
        $this->assertCourseLesson($course, $lesson);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'in:video,text,quiz,assignment'],
            'content' => ['nullable', 'string'],
            'video_url' => ['nullable', 'string', 'max:500'],
            'video_provider' => ['nullable', 'in:youtube,vimeo,upload'],
            'video_file' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:204800'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'is_free_preview' => ['nullable'],
        ]);

        $validated['is_free_preview'] = $request->boolean('is_free_preview');

        if ($request->hasFile('video_file')) {
            $validated['video_provider'] = 'upload';
            $validated['video_url'] = $request->file('video_file')->store('courses/videos', 'public');
        }

        $lesson->update($validated);
        $this->recordVersion($course, 'lesson-updated');

        return response()->json(['success' => true, 'lesson' => $lesson->fresh()]);
    }

    public function deleteLesson(Course $course, Lesson $lesson): JsonResponse
    {
        $this->authorizeInstructor($course);
        $this->assertCourseLesson($course, $lesson);

        $lesson->delete();
        $this->recordVersion($course, 'lesson-deleted');

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request, Course $course): JsonResponse
    {
        $this->authorizeInstructor($course);

        $request->validate([
            'sections'                    => ['required', 'array'],
            'sections.*.id'               => ['required', 'exists:sections,id'],
            'sections.*.sort_order' => ['required', 'integer'],
            'sections.*.lessons' => ['nullable', 'array'],
            'sections.*.lessons.*.id' => ['required', 'exists:lessons,id'],
            'sections.*.lessons.*.sort_order' => ['required', 'integer'],
        ]);

        DB::transaction(function () use ($request, $course) {
            foreach ($request->input('sections') as $sectionData) {
                Section::where('id', $sectionData['id'])
                    ->where('course_id', $course->id)
                    ->update(['sort_order' => $sectionData['sort_order']]);

                foreach ($sectionData['lessons'] ?? [] as $lessonData) {
                    Lesson::where('id', $lessonData['id'])
                        ->where('course_id', $course->id)
                        ->update([
                        'section_id' => $sectionData['id'],
                        'sort_order' => $lessonData['sort_order'],
                    ]);
                }
            }
        });

        $this->recordVersion($course, 'curriculum-reordered');

        return response()->json(['success' => true]);
    }

    public function saveStep3(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeInstructor($course);

        $course->update(['wizard_step' => max($course->wizard_step, 4)]);
        $this->recordVersion($course, 'step-3-curriculum');

        return redirect()->route('instructor.courses.wizard', [$course, 'step' => 4])
            ->with('success', 'Curriculum saved!');
    }

    public function saveStep4(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeInstructor($course);

        $validated = $request->validate([
            'language' => ['required', 'string', 'max:10'],
            'difficulty_level' => ['nullable', 'in:beginner,intermediate,advanced,all_levels'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'certificate_template' => ['nullable', 'string', 'max:100'],
            'publish_mode' => ['required', 'in:draft,now,schedule'],
            'scheduled_publish_at' => ['nullable', 'date', 'after:now', 'required_if:publish_mode,schedule'],
        ]);

        if (!empty($validated['difficulty_level'])) {
            $validated['level'] = $validated['difficulty_level'];
        }

        $publishMode = $validated['publish_mode'];
        unset($validated['publish_mode'], $validated['difficulty_level']);

        if ($publishMode === 'now') {
            $validated['status'] = 'published';
            $validated['published_at'] = now();
            $validated['scheduled_publish_at'] = null;
        } elseif ($publishMode === 'schedule') {
            $validated['status'] = 'pending';
            $validated['published_at'] = null;
        } else {
            $validated['status'] = 'draft';
            $validated['published_at'] = null;
            $validated['scheduled_publish_at'] = null;
        }

        $validated['wizard_step'] = 4;
        $course->update($validated);
        $this->recordVersion($course, 'step-4-settings-publishing');

        $msg = match ($publishMode) {
            'now' => 'Course published successfully!',
            'schedule' => 'Course scheduled for publishing.',
            default => 'Course saved as draft.',
        };

        return redirect()->route('instructor.courses.index')
            ->with('success', $msg);
    }

    public function autoSave(Request $request, Course $course): JsonResponse
    {
        $this->authorizeInstructor($course);

        $fillable = array_values(array_diff(
            $course->getFillable(),
            ['instructor_id', 'status', 'published_at', 'scheduled_publish_at', 'wizard_step']
        ));
        $data = $request->only($fillable);

        foreach (['requirements', 'what_you_will_learn', 'target_audience'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = json_decode($data[$field], true);
            }
        }

        if (isset($data['is_free'])) {
            $data['is_free'] = filter_var($data['is_free'], FILTER_VALIDATE_BOOLEAN);
            if ($data['is_free']) {
                $data['price'] = 0;
                $data['discount_price'] = null;
            }
        }

        if (
            isset($data['price'], $data['discount_price'])
            && $data['discount_price'] !== null
            && (float) $data['discount_price'] >= (float) $data['price']
        ) {
            unset($data['discount_price']);
        }

        $course->update($data);

        $tagIds = $request->input('tags');
        if (is_array($tagIds)) {
            $course->tags()->sync($tagIds);
        }

        return response()->json([
            'success' => true,
            'saved_at' => now()->format('H:i:s'),
        ]);
    }

    public function preview(Course $course): View
    {
        $this->authorizeInstructor($course);

        $course->load([
            'category',
            'tags',
            'instructor',
            'sections' => fn($q) => $q->ordered()->with(['lessons' => fn($l) => $l->ordered()]),
        ]);
        $course->loadCount(['lessons', 'sections']);

        return view('instructor.courses.preview', compact('course'));
    }

    public function versionHistory(Course $course): JsonResponse
    {
        $this->authorizeInstructor($course);

        $versions = $course->versions()
            ->with('actor:id,name')
            ->limit(50)
            ->get(['id', 'course_id', 'actor_id', 'action', 'created_at']);

        return response()->json(['success' => true, 'versions' => $versions]);
    }

    public function restoreVersion(Course $course, CourseVersion $version): RedirectResponse
    {
        $this->authorizeInstructor($course);

        if ($version->course_id !== $course->id) {
            abort(404);
        }

        $snapshot = $version->snapshot;
        if (!is_array($snapshot) || empty($snapshot['course'])) {
            return back()->with('error', 'Selected version is invalid and cannot be restored.');
        }

        DB::transaction(function () use ($course, $snapshot) {
            $coursePayload = $snapshot['course'];
            unset($coursePayload['slug']);

            $allowed = array_flip(array_values(array_diff($course->getFillable(), ['instructor_id', 'slug'])));
            $coursePayload = array_intersect_key($coursePayload, $allowed);
            $course->update($coursePayload);

            $course->tags()->sync($snapshot['tags'] ?? []);

            $course->sections()->each(function (Section $section) {
                $section->lessons()->delete();
                $section->delete();
            });

            foreach (($snapshot['sections'] ?? []) as $sectionData) {
                $lessons = $sectionData['lessons'] ?? [];
                unset($sectionData['lessons']);

                $section = $course->sections()->create([
                    'title' => $sectionData['title'] ?? 'Untitled Section',
                    'description' => $sectionData['description'] ?? null,
                    'sort_order' => $sectionData['sort_order'] ?? 0,
                ]);

                foreach ($lessons as $lessonData) {
                    $section->lessons()->create([
                        'course_id' => $course->id,
                        'title' => $lessonData['title'] ?? 'Untitled Lesson',
                        'type' => $lessonData['type'] ?? 'video',
                        'content' => $lessonData['content'] ?? null,
                        'video_url' => $lessonData['video_url'] ?? null,
                        'video_provider' => $lessonData['video_provider'] ?? null,
                        'duration_minutes' => $lessonData['duration_minutes'] ?? 0,
                        'sort_order' => $lessonData['sort_order'] ?? 0,
                        'is_free_preview' => (bool) ($lessonData['is_free_preview'] ?? false),
                        'is_published' => (bool) ($lessonData['is_published'] ?? true),
                    ]);
                }
            }
        });

        $this->recordVersion($course, 'version-restored');

        return back()->with('success', 'Course version restored successfully.');
    }

    public function duplicate(Course $course): RedirectResponse
    {
        $this->authorizeInstructor($course);

        $newCourse = DB::transaction(function () use ($course) {
            $newCourse = $course->replicate([
                'slug', 'status', 'published_at', 'scheduled_publish_at', 'wizard_step',
            ]);
            $newCourse->title = $course->title . ' (Copy)';
            $newCourse->status = 'draft';
            $newCourse->published_at = null;
            $newCourse->scheduled_publish_at = null;
            $newCourse->wizard_step = 1;

            if ($course->thumbnail && Storage::disk('public')->exists($course->thumbnail)) {
                $extension = pathinfo($course->thumbnail, PATHINFO_EXTENSION);
                $newPath = 'courses/thumbnails/' . Str::uuid() . ($extension ? '.' . $extension : '');
                Storage::disk('public')->copy($course->thumbnail, $newPath);
                $newCourse->thumbnail = $newPath;
            }

            $newCourse->save();
            $newCourse->tags()->sync($course->tags->pluck('id'));

            foreach ($course->sections()->ordered()->with(['lessons' => fn($q) => $q->ordered()])->get() as $section) {
                $newSection = $newCourse->sections()->create([
                    'title' => $section->title,
                    'description' => $section->description,
                    'sort_order' => $section->sort_order,
                ]);

                foreach ($section->lessons as $lesson) {
                    $newLesson = $lesson->replicate(['slug', 'course_id', 'section_id']);
                    $newLesson->course_id = $newCourse->id;
                    $newLesson->section_id = $newSection->id;
                    $newLesson->save();
                }
            }

            return $newCourse;
        });

        $this->recordVersion($newCourse, 'course-duplicated-from-' . $course->id);

        return redirect()->route('instructor.courses.wizard', [$newCourse, 'step' => 1])
            ->with('success', 'Course duplicated! You can now edit the copy.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        $this->authorizeInstructor($course);

        if ($course->enrollments()->exists()) {
            return back()->with('error', 'Cannot delete a course with enrolled students.');
        }

        $course->delete();

        return redirect()->route('instructor.courses.index')
            ->with('success', 'Course deleted.');
    }

    private function authorizeInstructor(Course $course): void
    {
        if (!Auth::check() || ($course->instructor_id !== Auth::id() && !Auth::user()->isAdmin())) {
            abort(403, 'You do not own this course.');
        }
    }

    private function assertCourseSection(Course $course, Section $section): void
    {
        if ($section->course_id !== $course->id) {
            abort(404);
        }
    }

    private function assertCourseLesson(Course $course, Lesson $lesson): void
    {
        if ($lesson->course_id !== $course->id) {
            abort(404);
        }
    }

    private function recordVersion(Course $course, string $action): void
    {
        $course->versions()->create([
            'actor_id' => Auth::id(),
            'action' => $action,
            'snapshot' => $this->buildSnapshot($course),
            'created_at' => now(),
        ]);
    }

    private function buildSnapshot(Course $course): array
    {
        $course->load([
            'tags:id',
            'sections' => fn($q) => $q->ordered()->with(['lessons' => fn($l) => $l->ordered()]),
        ]);

        return [
            'course' => $course->only([
                'category_id',
                'title',
                'subtitle',
                'description',
                'requirements',
                'what_you_will_learn',
                'target_audience',
                'thumbnail',
                'intro_video',
                'level',
                'language',
                'price',
                'discount_price',
                'duration_hours',
                'status',
                'is_featured',
                'is_free',
                'published_at',
                'scheduled_publish_at',
                'meta_title',
                'meta_description',
                'meta_keywords',
                'certificate_template',
                'wizard_step',
            ]),
            'tags' => $course->tags->pluck('id')->values()->all(),
            'sections' => $course->sections->map(function (Section $section) {
                return [
                    'title' => $section->title,
                    'description' => $section->description,
                    'sort_order' => $section->sort_order,
                    'lessons' => $section->lessons->map(function (Lesson $lesson) {
                        return [
                            'title' => $lesson->title,
                            'type' => $lesson->type,
                            'content' => $lesson->content,
                            'video_url' => $lesson->video_url,
                            'video_provider' => $lesson->video_provider,
                            'duration_minutes' => $lesson->duration_minutes,
                            'sort_order' => $lesson->sort_order,
                            'is_free_preview' => $lesson->is_free_preview,
                            'is_published' => $lesson->is_published,
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
        ];
    }
}
