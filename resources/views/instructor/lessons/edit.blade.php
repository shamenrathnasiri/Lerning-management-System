<x-app-layout>
    <x-slot name="header">
        <nav class="flex items-center gap-2 text-sm text-gray-500">
            <a href="{{ route('instructor.courses.index') }}" class="hover:text-gray-700">My Courses</a>
            <span>/</span>
            <a href="{{ route('instructor.courses.wizard', [$course->slug, 'step' => 3]) }}"
               class="hover:text-gray-700">{{ Str::limit($course->title, 40) }}</a>
            <span>/</span>
            <span class="text-gray-700">{{ Str::limit($lesson->title, 40) }}</span>
        </nav>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8"
         x-data="lessonEditApp()"
         x-init="init()">

        {{-- ── Lesson header ─────────────────────────────────────────────── --}}
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold
                    {{ match($lesson->type) {
                        'video'        => 'bg-blue-100 text-blue-700',
                        'text'         => 'bg-green-100 text-green-700',
                        'pdf'          => 'bg-red-100 text-red-700',
                        'audio'        => 'bg-purple-100 text-purple-700',
                        'presentation' => 'bg-orange-100 text-orange-700',
                        'external'     => 'bg-gray-100 text-gray-700',
                        default        => 'bg-gray-100 text-gray-700',
                    } }}">
                    {{ $lesson->type_label }}
                </span>
                @if($lesson->is_published)
                    <span class="text-xs text-green-600 font-medium">● Published</span>
                @else
                    <span class="text-xs text-yellow-600 font-medium">● Draft</span>
                @endif
            </div>
            <div class="flex gap-2">
                <form action="{{ route('instructor.lessons.duplicate', $lesson) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="px-3 py-1.5 text-sm border rounded-lg hover:bg-gray-50 text-gray-600">Duplicate</button>
                </form>
                <form action="{{ route('instructor.lessons.destroy', $lesson) }}" method="POST"
                      onsubmit="return confirm('Delete this lesson?')">
                    @csrf @method('DELETE')
                    <button class="px-3 py-1.5 text-sm border border-red-200 rounded-lg hover:bg-red-50 text-red-600">Delete</button>
                </form>
            </div>
        </div>

        {{-- ── Video processing status banner ────────────────────────────── --}}
        @if($lesson->type === 'video' && $lesson->video_provider === 'upload')
        <div x-data="videoStatusPoller()"
             x-init="startPolling()"
             class="mb-6">
            <div x-show="status === 'pending' || status === 'processing'"
                 class="flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-xl p-4">
                <svg class="animate-spin w-5 h-5 text-blue-500 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <div>
                    <p class="text-sm font-medium text-blue-800">Video is being processed…</p>
                    <p class="text-xs text-blue-600" x-text="statusMessage"></p>
                </div>
            </div>
            <div x-show="status === 'completed'"
                 class="flex items-center gap-3 bg-green-50 border border-green-200 rounded-xl p-4">
                <svg class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <p class="text-sm font-medium text-green-800">Video processing complete.</p>
            </div>
            <div x-show="status === 'failed'"
                 class="flex items-center gap-3 bg-red-50 border border-red-200 rounded-xl p-4">
                <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                <div>
                    <p class="text-sm font-medium text-red-800">Processing failed.</p>
                    <p class="text-xs text-red-600" x-text="errorMessage"></p>
                </div>
            </div>
        </div>
        @endif

        {{-- ── Edit Form ──────────────────────────────────────────────────── --}}
                <form action="{{ route('instructor.lessons.update', $lesson) }}"
              method="POST"
              enctype="multipart/form-data"
              class="space-y-6">
            @csrf @method('PATCH')

            @if ($errors->any())
            <div class="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
            @endif

            @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-700">
                {{ session('success') }}
            </div>
            @endif

            {{-- Common Fields --}}
            <div class="bg-white rounded-xl shadow-sm border p-6 space-y-5">
                <h3 class="font-semibold text-gray-700">Lesson Details</h3>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" value="{{ old('title', $lesson->title) }}"
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" required>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Duration (minutes)</label>
                        <input type="number" name="duration_minutes"
                               value="{{ old('duration_minutes', $lesson->duration_minutes) }}"
                               min="0" max="14400"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    </div>
                    <div class="flex flex-col gap-3 pt-2">
                        <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                            <input type="checkbox" name="is_free_preview" value="1"
                                   {{ old('is_free_preview', $lesson->is_free_preview) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600">
                            Free preview
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                            <input type="checkbox" name="is_published" value="1"
                                   {{ old('is_published', $lesson->is_published) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600">
                            Published
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                            <input type="checkbox" name="is_downloadable" value="1"
                                   {{ old('is_downloadable', $lesson->is_downloadable) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600">
                            Allow download
                        </label>
                    </div>
                </div>
            </div>

            {{-- ── Type-specific content ─────────────────────────────────── --}}

            {{-- VIDEO --}}
            @if($lesson->type === 'video')
            <div x-data="{ videoProvider: '{{ old('video_provider', $lesson->video_provider ?? 'youtube') }}' }"
                 class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
                <h3 class="font-semibold text-gray-700">Video Content</h3>

                <div class="flex gap-4">
                    @foreach (['youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'upload' => 'Direct Upload'] as $val => $lbl)
                    <label class="flex items-center gap-2 cursor-pointer text-sm">
                        <input type="radio" name="video_provider" value="{{ $val }}"
                               x-model="videoProvider"
                               {{ old('video_provider', $lesson->video_provider) === $val ? 'checked' : '' }}
                               class="text-indigo-600">
                        {{ $lbl }}
                    </label>
                    @endforeach
                </div>

                <div x-show="videoProvider !== 'upload'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Video URL</label>
                    @if($lesson->video_url)
                    <p class="text-xs text-gray-500 mb-1">Current: <a href="{{ $lesson->video_url }}" target="_blank" class="text-indigo-500 underline">{{ Str::limit($lesson->video_url, 60) }}</a></p>
                    @endif
                    <input type="url" name="video_url"
                           value="{{ old('video_url', $lesson->video_url) }}"
                           placeholder="https://www.youtube.com/watch?v=..."
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                </div>

                <div x-show="videoProvider === 'upload'" class="space-y-3">
                    @if($lesson->s3_key)
                    <div class="flex items-center gap-2 text-sm text-green-700 bg-green-50 rounded-lg p-3">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Video file already uploaded. Upload a new file below to replace it.
                    </div>
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Replace Video File <span class="text-gray-400">(optional)</span></label>
                        <input type="file" name="video_file" accept="video/*"
                               class="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700">
                        <p class="text-xs text-gray-400 mt-1">MP4, WebM, MKV, MOV — max 2 GB</p>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                        <input type="checkbox" name="video_watermark" value="1"
                               {{ old('video_watermark', $lesson->video_watermark) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600">
                        Apply watermark during processing
                    </label>
                </div>

                {{-- Thumbnail preview --}}
                @if($lesson->thumbnail_path)
                <div>
                    <p class="text-sm font-medium text-gray-700 mb-1">Auto-generated Thumbnail</p>
                    <img src="{{ $lesson->thumbnail_path }}" alt="Thumbnail" class="h-24 rounded-lg object-cover border">
                </div>
                @endif

                {{-- Quality variants --}}
                @if($lesson->video_quality_urls)
                <div class="bg-gray-50 rounded-lg p-3 text-xs text-gray-600">
                    <p class="font-medium mb-1">Available qualities:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($lesson->video_quality_urls as $quality => $key)
                            <li>{{ strtoupper($quality) }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>
            @endif

            {{-- TEXT --}}
            @if($lesson->type === 'text')
            <div class="bg-white rounded-xl shadow-sm border p-6 space-y-3">
                <h3 class="font-semibold text-gray-700">Article Content</h3>
                <div class="flex flex-wrap gap-1 border border-gray-200 rounded-t-lg bg-gray-50 px-2 py-1">
                    @foreach ([['bold','B','font-bold'],['italic','I','italic'],['insertOrderedList','OL',''],['insertUnorderedList','UL','']] as [$cmd,$lbl,$cls])
                    <button type="button" class="px-2 py-0.5 text-sm rounded hover:bg-gray-200 {{ $cls }}"
                            onclick="document.execCommand('{{ $cmd }}')">{{ $lbl }}</button>
                    @endforeach
                    <button type="button" class="px-2 py-0.5 text-sm rounded hover:bg-gray-200 font-mono"
                            onclick="wrapCode()">{'{ }'}</button>
                    <select class="ml-auto text-xs border-0 bg-transparent" onchange="document.execCommand('formatBlock', false, this.value)">
                        <option value="p">Paragraph</option>
                        <option value="h2">Heading 2</option>
                        <option value="h3">Heading 3</option>
                        <option value="h4">Heading 4</option>
                        <option value="blockquote">Blockquote</option>
                    </select>
                </div>
                <div id="articleEditor"
                     contenteditable="true"
                     class="min-h-64 border border-gray-200 rounded-b-lg p-4 text-sm focus:outline-none prose max-w-none">
                    {!! old('content', $lesson->content) !!}
                </div>
                <textarea name="content" id="articleContent" class="hidden">{{ old('content', $lesson->content) }}</textarea>
            </div>
            @endif

            {{-- PDF --}}
            @if($lesson->type === 'pdf')
            <div class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
                <h3 class="font-semibold text-gray-700">PDF Document</h3>
                @if($lesson->file_path)
                <div class="flex items-center gap-2 text-sm text-blue-700 bg-blue-50 rounded-lg p-3">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    {{ basename($lesson->file_path) }}
                    <a href="{{ Storage::url($lesson->file_path) }}" target="_blank" class="ml-auto underline text-xs">View</a>
                </div>
                @endif
                <input type="file" name="pdf_file" accept=".pdf"
                       class="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700">
                <div class="relative flex items-center"><div class="flex-grow border-t border-gray-200"></div><span class="mx-3 text-xs text-gray-400">OR</span><div class="flex-grow border-t border-gray-200"></div></div>
                <input type="url" name="external_url" value="{{ old('external_url', $lesson->external_url) }}"
                       placeholder="https://example.com/document.pdf"
                       class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
            </div>
            @endif

            {{-- AUDIO --}}
            @if($lesson->type === 'audio')
            <div class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
                <h3 class="font-semibold text-gray-700">Audio Content</h3>
                @if($lesson->file_path)
                <audio controls class="w-full">
                    <source src="{{ Storage::url($lesson->file_path) }}">
                </audio>
                @elseif($lesson->external_url)
                <audio controls class="w-full">
                    <source src="{{ $lesson->external_url }}">
                </audio>
                @endif
                <input type="file" name="audio_file" accept=".mp3,.aac,.ogg,.wav,.m4a"
                       class="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700">
                <div class="relative flex items-center"><div class="flex-grow border-t border-gray-200"></div><span class="mx-3 text-xs text-gray-400">OR</span><div class="flex-grow border-t border-gray-200"></div></div>
                <input type="url" name="external_url" value="{{ old('external_url', $lesson->external_url) }}"
                       placeholder="https://example.com/podcast.mp3"
                       class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
            </div>
            @endif

            {{-- PRESENTATION --}}
            @if($lesson->type === 'presentation')
            <div class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
                <h3 class="font-semibold text-gray-700">Presentation / Slides</h3>
                @if($lesson->file_path)
                <div class="text-sm text-blue-700 bg-blue-50 rounded-lg p-3">
                    Current file: {{ basename($lesson->file_path) }}
                    <a href="{{ Storage::url($lesson->file_path) }}" target="_blank" class="ml-2 underline">View</a>
                </div>
                @endif
                <input type="file" name="presentation_file" accept=".pdf,.ppt,.pptx"
                       class="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700">
                <div class="relative flex items-center"><div class="flex-grow border-t border-gray-200"></div><span class="mx-3 text-xs text-gray-400">OR</span><div class="flex-grow border-t border-gray-200"></div></div>
                <input type="url" name="external_url" value="{{ old('external_url', $lesson->external_url) }}"
                       placeholder="https://docs.google.com/presentation/..."
                       class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
            </div>
            @endif

            {{-- EXTERNAL --}}
            @if($lesson->type === 'external')
            <div class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
                <h3 class="font-semibold text-gray-700">External Resource</h3>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">URL <span class="text-red-500">*</span></label>
                    <input type="url" name="external_url" value="{{ old('external_url', $lesson->external_url) }}"
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="content" rows="3"
                              class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old('content', $lesson->content) }}</textarea>
                </div>
            </div>
            @endif

            {{-- Actions --}}
            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('instructor.courses.wizard', [$course->slug, 'step' => 3]) }}"
                   class="text-sm text-gray-500 hover:text-gray-700">← Back to Curriculum</a>
                <button type="submit"
                        class="px-6 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                    Save Changes
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script>
    function wrapCode() {
        const sel = window.getSelection();
        if (!sel.rangeCount) return;
        const range = sel.getRangeAt(0);
        const code  = document.createElement('code');
        code.className = 'bg-gray-100 px-1 rounded font-mono text-sm';
        range.surroundContents(code);
    }

    // Sync hidden textarea from editor on submit
    document.querySelector('form')?.addEventListener('submit', () => {
        const editor = document.getElementById('articleEditor');
        const hidden = document.getElementById('articleContent');
        if (editor && hidden) hidden.value = editor.innerHTML;
    });

    // Video processing status poller
    function videoStatusPoller() {
        return {
            status: '{{ $lesson->processing_status ?? 'none' }}',
            errorMessage: '',
            statusMessage: 'Uploading and processing…',
            pollInterval: null,

            startPolling() {
                if (!['pending','processing'].includes(this.status)) return;
                this.pollInterval = setInterval(() => this.poll(), 4000);
            },

            async poll() {
                try {
                    const res  = await fetch('{{ route('instructor.lessons.video-status', $lesson) }}');
                    const data = await res.json();
                    this.status       = data.processing_status ?? 'none';
                    this.errorMessage = data.job?.error_message ?? '';
                    if (data.job?.elapsed_seconds) {
                        this.statusMessage = `Processing… (${data.job.elapsed_seconds}s elapsed)`;
                    }
                    if (!['pending','processing'].includes(this.status)) {
                        clearInterval(this.pollInterval);
                    }
                } catch (e) { /* silent */ }
            },
        };
    }

    // Apply highlight.js to pre>code blocks when on text lessons
    document.addEventListener('DOMContentLoaded', () => hljs?.highlightAll?.());
    </script>
    @endpush
</x-app-layout>
