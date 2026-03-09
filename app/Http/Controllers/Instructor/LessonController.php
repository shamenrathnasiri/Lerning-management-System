<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Jobs\Video\ProcessVideoUpload;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\VideoProcessingJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LessonController extends Controller
{
    // ── Allowed lesson types ─────────────────────────────────────────────────

    private const TYPES = ['video', 'text', 'pdf', 'audio', 'presentation', 'external', 'quiz', 'assignment'];

    // ── Shared validation rules ──────────────────────────────────────────────

    private array $commonRules = [
        'title'            => ['required', 'string', 'max:255'],
        'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:14400'],
        'is_free_preview'  => ['nullable', 'boolean'],
        'is_published'     => ['nullable', 'boolean'],
        'is_downloadable'  => ['nullable', 'boolean'],
    ];

    private function typeRules(string $type, ?Lesson $existing = null): array
    {
        $requiresVideoUpload = ! $existing || (! $existing->s3_key && ! $existing->video_url);
        $requiresPdfSource = ! $existing || (! $existing->file_path && ! $existing->external_url);
        $requiresAudioSource = ! $existing || (! $existing->file_path && ! $existing->external_url);
        $requiresPresentationSource = ! $existing || (! $existing->file_path && ! $existing->external_url);

        return match ($type) {
            'video' => [
                'video_provider' => ['required', 'in:youtube,vimeo,upload'],
                'video_url'      => [
                    'nullable',
                    'url',
                    'max:500',
                    Rule::requiredIf(fn () => in_array(request()->input('video_provider'), ['youtube', 'vimeo'], true)),
                ],
                'video_file'     => ['nullable', 'file',
                                     'mimetypes:video/mp4,video/webm,video/x-matroska,video/quicktime',
                                     'max:2048000',
                                     Rule::requiredIf(fn () => request()->input('video_provider') === 'upload' && $requiresVideoUpload),
                                    ],
                'video_watermark'=> ['nullable', 'boolean'],
            ],
            'text' => [
                'content' => ['required', 'string'],
            ],
            'pdf' => [
                'pdf_file'    => [
                    'nullable',
                    'file',
                    'mimes:pdf',
                    'max:51200',
                    Rule::requiredIf(fn () => $requiresPdfSource && ! request()->filled('external_url')),
                ],
                'external_url'=> [
                    'nullable',
                    'url',
                    'max:1000',
                    Rule::requiredIf(fn () => $requiresPdfSource && ! request()->hasFile('pdf_file')),
                ],
            ],
            'audio' => [
                'audio_file'  => [
                    'nullable',
                    'file',
                    'mimes:mp3,aac,ogg,wav,m4a',
                    'max:204800',
                    Rule::requiredIf(fn () => $requiresAudioSource && ! request()->filled('external_url')),
                ],
                'external_url'=> [
                    'nullable',
                    'url',
                    'max:1000',
                    Rule::requiredIf(fn () => $requiresAudioSource && ! request()->hasFile('audio_file')),
                ],
            ],
            'presentation' => [
                'presentation_file'=> [
                    'nullable',
                    'file',
                    'mimes:pdf,pptx,ppt',
                    'max:102400',
                    Rule::requiredIf(fn () => $requiresPresentationSource && ! request()->filled('external_url')),
                ],
                'external_url'     => [
                    'nullable',
                    'url',
                    'max:1000',
                    Rule::requiredIf(fn () => $requiresPresentationSource && ! request()->hasFile('presentation_file')),
                ],
                'slides_data'      => ['nullable', 'json'],
            ],
            'external' => [
                'external_url' => ['required', 'url', 'max:1000'],
                'content'      => ['nullable', 'string', 'max:2000'],
            ],
            default => [],
        };
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create(Course $course, Section $section)
    {
        $this->authorizeInstructor($course);

        return view('instructor.lessons.create', compact('course', 'section'));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(Request $request, Course $course, Section $section)
    {
        $this->authorizeInstructor($course);

        $type  = $request->input('type', 'video');
        $rules = array_merge(
            $this->commonRules,
            ['type' => ['required', Rule::in(self::TYPES)]],
            $this->typeRules($type)
        );

        $validated = $request->validate($rules);
        $data      = $this->buildLessonData($request, $validated, $type);

        $data['section_id'] = $section->id;
        $data['course_id']  = $course->id;
        $data['sort_order'] = (int) $section->lessons()->max('sort_order') + 1;

        $lesson = DB::transaction(function () use ($data, $request, $type) {
            $lesson = Lesson::create($data);

            if ($type === 'video'
                && ($data['video_provider'] ?? '') === 'upload'
                && $request->hasFile('video_file')
            ) {
                $this->dispatchVideoUpload($lesson, $request);
            }

            return $lesson;
        });

        return redirect()
            ->route('instructor.lessons.edit', $lesson)
            ->with('success', 'Lesson created. Fill in the content below.');
    }

    // ── Edit ──────────────────────────────────────────────────────────────────

    public function edit(Lesson $lesson)
    {
        $lesson->load(['section', 'course', 'videoProcessingJob']);
        $this->authorizeInstructor($lesson->course);

        return view('instructor.lessons.edit', [
            'lesson'  => $lesson,
            'course'  => $lesson->course,
            'section' => $lesson->section,
        ]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(Request $request, Lesson $lesson)
    {
        $lesson->load('course');
        $this->authorizeInstructor($lesson->course);

        $type  = $lesson->type; // type is immutable after creation
        $rules = array_merge($this->commonRules, $this->typeRules($type, $lesson));

        $validated = $request->validate($rules);
        $data      = $this->buildLessonData($request, $validated, $type, $lesson);

        DB::transaction(function () use ($lesson, $data, $request, $type) {
            $lesson->update($data);

            if ($type === 'video'
                && ($data['video_provider'] ?? $lesson->video_provider) === 'upload'
                && $request->hasFile('video_file')
            ) {
                $this->dispatchVideoUpload($lesson, $request);
            }
        });

        return redirect()
            ->route('instructor.lessons.edit', $lesson)
            ->with('success', 'Lesson updated successfully.');
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(Lesson $lesson)
    {
        $lesson->load('course');
        $this->authorizeInstructor($lesson->course);

        $course = $lesson->course;
        $lesson->delete();

        return redirect()
            ->route('instructor.courses.wizard', [$course->slug, 'step' => 3])
            ->with('success', 'Lesson deleted.');
    }

    // ── Duplicate ─────────────────────────────────────────────────────────────

    public function duplicate(Lesson $lesson)
    {
        $lesson->load(['course', 'section']);
        $this->authorizeInstructor($lesson->course);

        $newLesson = DB::transaction(function () use ($lesson) {
            $data = $lesson->toArray();

            unset($data['id'], $data['slug'], $data['created_at'], $data['updated_at'], $data['deleted_at']);

            $data['title']             = $lesson->title . ' (Copy)';
            $data['sort_order']        = (int) $lesson->section->lessons()->max('sort_order') + 1;
            $data['is_published']      = false;
            $data['processing_status'] = null;
            $data['s3_key']            = null;
            $data['video_quality_urls']= null;

            // Copy local files if present
            if ($lesson->file_path && Storage::disk('public')->exists($lesson->file_path)) {
                $ext  = pathinfo($lesson->file_path, PATHINFO_EXTENSION);
                $dir  = dirname($lesson->file_path);
                $copy = $dir . '/copy_' . uniqid() . '.' . $ext;
                Storage::disk('public')->copy($lesson->file_path, $copy);
                $data['file_path'] = $copy;
            }

            return Lesson::create($data);
        });

        return redirect()
            ->route('instructor.lessons.edit', $newLesson)
            ->with('success', 'Lesson duplicated.');
    }

    // ── Reorder (AJAX drag-drop) ────────────────────────────────────────────

    public function reorder(Request $request, Course $course): JsonResponse
    {
        $this->authorizeInstructor($course);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => [
                'required',
                Rule::exists('lessons', 'id')->where(fn ($q) => $q->where('course_id', $course->id)),
            ],
            'items.*.section_id' => [
                'required',
                Rule::exists('sections', 'id')->where(fn ($q) => $q->where('course_id', $course->id)),
            ],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($validated, $course) {
            foreach ($validated['items'] as $item) {
                Lesson::where('course_id', $course->id)
                    ->where('id', $item['id'])
                    ->update([
                        'section_id' => $item['section_id'],
                        'sort_order' => $item['sort_order'],
                    ]);
            }
        });

        return response()->json(['success' => true]);
    }

    // ── Video Processing Status (JSON polling) ────────────────────────────────

    public function videoStatus(Lesson $lesson)
    {
        $lesson->load('course');
        $this->authorizeInstructor($lesson->course);

        $job = $lesson->videoProcessingJob;

        return response()->json([
            'processing_status' => $lesson->processing_status,
            'job' => $job ? [
                'status'           => $job->status,
                'thumbnails'       => $job->thumbnails,
                'qualities'        => $job->qualities,
                'watermark_applied'=> $job->watermark_applied,
                'error_message'    => $job->error_message,
                'elapsed_seconds'  => $job->elapsed_seconds,
                'started_at'       => $job->started_at?->toIso8601String(),
                'completed_at'     => $job->completed_at?->toIso8601String(),
            ] : null,
        ]);
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    /**
     * Build the array of DB-ready lesson field values from validated request data.
     *
     * @param  Request       $request
     * @param  array         $validated
     * @param  string        $type
     * @param  Lesson|null   $existing  Existing model (for update — old files are replaced)
     */
    private function buildLessonData(
        Request $request,
        array   $validated,
        string  $type,
        ?Lesson $existing = null,
    ): array {
        $data = [
            'title'            => $validated['title'],
            'type'             => $validated['type'] ?? $type,
            'duration_minutes' => (int) ($validated['duration_minutes'] ?? 0),
            'is_free_preview'  => (bool) ($validated['is_free_preview'] ?? false),
            'is_published'     => (bool) ($validated['is_published'] ?? false),
            'is_downloadable'  => (bool) ($validated['is_downloadable'] ?? false),
        ];

        switch ($type) {
            case 'video':
                $data['video_provider'] = $validated['video_provider'];
                if (in_array($validated['video_provider'], ['youtube', 'vimeo'])) {
                    $data['video_url']      = $validated['video_url'];
                    $data['processing_status'] = null;
                }
                $data['video_watermark'] = (bool) ($validated['video_watermark'] ?? false);
                break;

            case 'text':
                $data['content'] = $validated['content'];
                break;

            case 'pdf':
                if ($request->hasFile('pdf_file')) {
                    if ($existing?->file_path) {
                        Storage::disk('public')->delete($existing->file_path);
                    }
                    $data['file_path'] = $request->file('pdf_file')->store('lessons/pdfs', 'public');
                } elseif (! empty($validated['external_url'])) {
                    $data['external_url'] = $validated['external_url'];
                }
                break;

            case 'audio':
                if ($request->hasFile('audio_file')) {
                    if ($existing?->file_path) {
                        Storage::disk('public')->delete($existing->file_path);
                    }
                    $data['file_path'] = $request->file('audio_file')->store('lessons/audio', 'public');
                } elseif (! empty($validated['external_url'])) {
                    $data['external_url'] = $validated['external_url'];
                }
                break;

            case 'presentation':
                if ($request->hasFile('presentation_file')) {
                    if ($existing?->file_path) {
                        Storage::disk('public')->delete($existing->file_path);
                    }
                    $data['file_path'] = $request->file('presentation_file')
                                                  ->store('lessons/presentations', 'public');
                } elseif (! empty($validated['external_url'])) {
                    $data['external_url'] = $validated['external_url'];
                }
                if (! empty($validated['slides_data'])) {
                    $data['slides_data'] = json_decode($validated['slides_data'], true);
                }
                break;

            case 'external':
                $data['external_url'] = $validated['external_url'];
                $data['content']      = $validated['content'] ?? null;
                break;
        }

        return $data;
    }

    /**
     * Store the uploaded video to a tmp location and dispatch the processing chain.
     */
    private function dispatchVideoUpload(Lesson $lesson, Request $request): void
    {
        $file     = $request->file('video_file');
        $tempPath = $file->store('temp/videos', 'local');

        $lesson->update(['processing_status' => 'pending']);

        VideoProcessingJob::updateOrCreate(
            ['lesson_id' => $lesson->id],
            [
                'original_filename' => $file->getClientOriginalName(),
                'original_size'     => $file->getSize(),
                'status'            => VideoProcessingJob::STATUS_PENDING,
            ]
        );

        ProcessVideoUpload::dispatch(
            $lesson,
            $tempPath,
            $file->getClientOriginalName(),
            (bool) $request->input('video_watermark', false)
        )->onQueue('video-processing');
    }

    /**
     * Abort with 403 unless the authenticated user owns the course.
     */
    private function authorizeInstructor(Course $course): void
    {
        abort_unless(
            Auth::check() && $course->instructor_id === Auth::id(),
            403,
            'You do not own this course.'
        );
    }
}
