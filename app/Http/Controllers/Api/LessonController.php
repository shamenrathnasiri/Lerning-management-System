<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LessonController extends Controller
{
    private const TYPES = ['video', 'text', 'pdf', 'audio', 'presentation', 'external', 'quiz', 'assignment'];

    private function baseRules(): array
    {
        return [
            'title'            => ['required', 'string', 'max:255'],
            'type'             => ['required', Rule::in(self::TYPES)],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:14400'],
            'sort_order'       => ['nullable', 'integer', 'min:0'],
            'is_free_preview'  => ['nullable', 'boolean'],
            'is_published'     => ['nullable', 'boolean'],
            'is_downloadable'  => ['nullable', 'boolean'],
            'resources'        => ['nullable', 'array'],
        ];
    }

    private function typeRules(string $type, ?Lesson $existing = null): array
    {
        $requiresVideoUpload = ! $existing || (! $existing->file_path && ! $existing->video_url && ! $existing->s3_key);
        $requiresPdfSource = ! $existing || (! $existing->file_path && ! $existing->external_url);
        $requiresAudioSource = ! $existing || (! $existing->file_path && ! $existing->external_url);
        $requiresPresentationSource = ! $existing || (! $existing->file_path && ! $existing->external_url);

        return match ($type) {
            'video' => [
                'video_provider' => ['required', 'in:youtube,vimeo,upload'],
                'video_url' => [
                    'nullable',
                    'url',
                    'max:500',
                    Rule::requiredIf(fn () => in_array(request()->input('video_provider'), ['youtube', 'vimeo'], true)),
                ],
                'video_file' => [
                    'nullable',
                    'file',
                    'mimetypes:video/mp4,video/webm,video/x-matroska,video/quicktime',
                    'max:2048000',
                    Rule::requiredIf(fn () => request()->input('video_provider') === 'upload' && $requiresVideoUpload),
                ],
            ],
            'text' => [
                'content' => ['required', 'string'],
            ],
            'pdf' => [
                'pdf_file' => [
                    'nullable',
                    'file',
                    'mimes:pdf',
                    'max:51200',
                    Rule::requiredIf(fn () => $requiresPdfSource && ! request()->filled('external_url')),
                ],
                'external_url' => [
                    'nullable',
                    'url',
                    'max:1000',
                    Rule::requiredIf(fn () => $requiresPdfSource && ! request()->hasFile('pdf_file')),
                ],
            ],
            'audio' => [
                'audio_file' => [
                    'nullable',
                    'file',
                    'mimes:mp3,aac,ogg,wav,m4a',
                    'max:204800',
                    Rule::requiredIf(fn () => $requiresAudioSource && ! request()->filled('external_url')),
                ],
                'external_url' => [
                    'nullable',
                    'url',
                    'max:1000',
                    Rule::requiredIf(fn () => $requiresAudioSource && ! request()->hasFile('audio_file')),
                ],
            ],
            'presentation' => [
                'presentation_file' => [
                    'nullable',
                    'file',
                    'mimes:pdf,pptx,ppt',
                    'max:102400',
                    Rule::requiredIf(fn () => $requiresPresentationSource && ! request()->filled('external_url')),
                ],
                'external_url' => [
                    'nullable',
                    'url',
                    'max:1000',
                    Rule::requiredIf(fn () => $requiresPresentationSource && ! request()->hasFile('presentation_file')),
                ],
                'slides_data' => ['nullable', 'json'],
            ],
            'external' => [
                'external_url' => ['required', 'url', 'max:1000'],
                'content' => ['nullable', 'string', 'max:2000'],
            ],
            default => [],
        };
    }

    private function ensureScoped(Course $course, Section $section, ?Lesson $lesson = null): void
    {
        abort_unless($section->course_id === $course->id, 404);
        if ($lesson) {
            abort_unless($lesson->course_id === $course->id && $lesson->section_id === $section->id, 404);
        }
    }

    public function index(Course $course, Section $section)
    {
        $this->ensureScoped($course, $section);

        return response()->json(
            $section->lessons()->orderBy('sort_order')->get()
        );
    }

    public function store(Request $request, Course $course, Section $section)
    {
        $this->ensureScoped($course, $section);

        $type = $request->input('type', 'video');
        $validated = $request->validate(array_merge($this->baseRules(), $this->typeRules($type)));

        $payload = [
            'course_id'        => $course->id,
            'title'            => $validated['title'],
            'type'             => $type,
            'content'          => $validated['content'] ?? null,
            'video_url'        => $validated['video_url'] ?? null,
            'video_provider'   => $validated['video_provider'] ?? null,
            'duration_minutes' => (int) ($validated['duration_minutes'] ?? 0),
            'sort_order'       => $validated['sort_order'] ?? (($section->lessons()->max('sort_order') ?? 0) + 1),
            'is_free_preview'  => (bool) ($validated['is_free_preview'] ?? false),
            'is_published'     => (bool) ($validated['is_published'] ?? false),
            'resources'        => $validated['resources'] ?? null,
            'external_url'     => $validated['external_url'] ?? null,
            'is_downloadable'  => (bool) ($validated['is_downloadable'] ?? false),
        ];

        if ($request->hasFile('video_file')) {
            $payload['video_provider'] = 'upload';
            $payload['file_path'] = $request->file('video_file')->store('lessons/videos', 'public');
        }

        if ($request->hasFile('pdf_file')) {
            $payload['file_path'] = $request->file('pdf_file')->store('lessons/pdfs', 'public');
        }

        if ($request->hasFile('audio_file')) {
            $payload['file_path'] = $request->file('audio_file')->store('lessons/audio', 'public');
        }

        if ($request->hasFile('presentation_file')) {
            $payload['file_path'] = $request->file('presentation_file')->store('lessons/presentations', 'public');
        }

        if (! empty($validated['slides_data'])) {
            $payload['slides_data'] = json_decode($validated['slides_data'], true);
        }

        $lesson = $section->lessons()->create($payload);

        return response()->json($lesson, 201);
    }

    public function show(Course $course, Section $section, Lesson $lesson)
    {
        $this->ensureScoped($course, $section, $lesson);

        return response()->json($lesson);
    }

    public function update(Request $request, Course $course, Section $section, Lesson $lesson)
    {
        $this->ensureScoped($course, $section, $lesson);

        $type = $request->input('type', $lesson->type);
        $rules = array_merge($this->baseRules(), $this->typeRules($type, $lesson));
        $rules['title'][0] = 'sometimes';
        $rules['type'][0] = 'sometimes';

        $validated = $request->validate($rules);

        $payload = [
            'title'            => $validated['title'] ?? $lesson->title,
            'type'             => $type,
            'content'          => $validated['content'] ?? $lesson->content,
            'video_url'        => $validated['video_url'] ?? $lesson->video_url,
            'video_provider'   => $validated['video_provider'] ?? $lesson->video_provider,
            'duration_minutes' => (int) ($validated['duration_minutes'] ?? $lesson->duration_minutes),
            'sort_order'       => $validated['sort_order'] ?? $lesson->sort_order,
            'is_free_preview'  => (bool) ($validated['is_free_preview'] ?? $lesson->is_free_preview),
            'is_published'     => (bool) ($validated['is_published'] ?? $lesson->is_published),
            'resources'        => $validated['resources'] ?? $lesson->resources,
            'external_url'     => $validated['external_url'] ?? $lesson->external_url,
            'is_downloadable'  => (bool) ($validated['is_downloadable'] ?? $lesson->is_downloadable),
        ];

        if ($request->hasFile('video_file')) {
            if ($lesson->file_path) {
                Storage::disk('public')->delete($lesson->file_path);
            }
            $payload['video_provider'] = 'upload';
            $payload['file_path'] = $request->file('video_file')->store('lessons/videos', 'public');
        }

        if ($request->hasFile('pdf_file')) {
            if ($lesson->file_path) {
                Storage::disk('public')->delete($lesson->file_path);
            }
            $payload['file_path'] = $request->file('pdf_file')->store('lessons/pdfs', 'public');
        }

        if ($request->hasFile('audio_file')) {
            if ($lesson->file_path) {
                Storage::disk('public')->delete($lesson->file_path);
            }
            $payload['file_path'] = $request->file('audio_file')->store('lessons/audio', 'public');
        }

        if ($request->hasFile('presentation_file')) {
            if ($lesson->file_path) {
                Storage::disk('public')->delete($lesson->file_path);
            }
            $payload['file_path'] = $request->file('presentation_file')->store('lessons/presentations', 'public');
        }

        if (! empty($validated['slides_data'])) {
            $payload['slides_data'] = json_decode($validated['slides_data'], true);
        }

        $lesson->update($payload);

        return response()->json($lesson);
    }

    public function destroy(Course $course, Section $section, Lesson $lesson)
    {
        $this->ensureScoped($course, $section, $lesson);

        $lesson->delete();

        return response()->json(['message' => 'Lesson deleted.']);
    }
}
