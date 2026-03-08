<x-app-layout>
    <x-slot name="header">
        <nav class="flex items-center gap-2 text-sm text-gray-500">
            <a href="{{ route('instructor.courses.index') }}" class="hover:text-gray-700">My Courses</a>
            <span>/</span>
            <a href="{{ route('instructor.courses.wizard', [$course->slug, 'step' => 3]) }}"
               class="hover:text-gray-700">{{ Str::limit($course->title, 40) }}</a>
            <span>/</span>
            <span class="text-gray-700">New Lesson — {{ $section->title }}</span>
        </nav>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8"
         x-data="lessonCreateApp()"
         x-init="init()">

        {{-- ── Type Selector ────────────────────────────────────────────────── --}}
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Choose Lesson Type</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @foreach ([
                    ['video',        'play-circle',           'Video',        'YouTube, Vimeo, or direct upload'],
                    ['text',         'document-text',         'Article',      'Rich text with code highlighting'],
                    ['pdf',          'document',              'PDF',          'Upload a PDF document'],
                    ['audio',        'musical-note',          'Audio',        'Podcast-style audio lesson'],
                    ['presentation', 'presentation-chart-bar','Presentation', 'Slides / PPTX / PDF deck'],
                    ['external',     'arrow-top-right-on-square','External Link','Link to an external resource'],
                ] as [$typeVal, $icon, $label, $desc])
                <button type="button"
                        @click="selectedType = '{{ $typeVal }}'"
                        :class="selectedType === '{{ $typeVal }}'
                            ? 'ring-2 ring-indigo-500 border-indigo-400 bg-indigo-50'
                            : 'border-gray-200 bg-white hover:border-indigo-300'"
                        class="flex flex-col items-start gap-1 p-4 rounded-xl border cursor-pointer text-left transition-all">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            @switch($icon)
                                @case('play-circle')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 0 1 0 .656l-5.603 3.113a.375.375 0 0 1-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112Z"/>
                                    @break
                                @case('document-text')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                                    @break
                                @case('document')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                                    @break
                                @case('musical-note')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 9 10.5-3m0 6.553v3.75a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 1 1-.99-3.467l2.31-.66a2.25 2.25 0 0 0 1.632-2.163Zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 0 1-.99-3.467l2.31-.66A2.25 2.25 0 0 0 9 15.553Z"/>
                                    @break
                                @case('presentation-chart-bar')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605"/>
                                    @break
                                @case('arrow-top-right-on-square')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                                    @break
                            @endswitch
                        </svg>
                        <span class="font-medium text-gray-800">{{ $label }}</span>
                    </div>
                    <p class="text-xs text-gray-500 leading-snug">{{ $desc }}</p>
                </button>
                @endforeach
            </div>
        </div>

        {{-- ── Lesson Form ──────────────────────────────────────────────────── --}}
        <form x-show="selectedType"
              x-cloak
              :action="formAction"
              method="POST"
              enctype="multipart/form-data"
              class="space-y-6">
            @csrf
            <input type="hidden" name="_method" value="POST">
            <input type="hidden" name="type" :value="selectedType">

            @if ($errors->any())
            <div class="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            {{-- Common Fields --}}
            <div class="bg-white rounded-xl shadow-sm border p-6 space-y-5">
                <h3 class="font-semibold text-gray-700">Lesson Details</h3>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" value="{{ old('title') }}"
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                           placeholder="e.g. Introduction to Variables" required>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Duration (minutes)</label>
                        <input type="number" name="duration_minutes" value="{{ old('duration_minutes', 0) }}"
                               min="0" max="14400"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    </div>
                    <div class="flex flex-col gap-3 pt-2">
                        <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                            <input type="checkbox" name="is_free_preview" value="1" {{ old('is_free_preview') ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600">
                            Free preview
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                            <input type="checkbox" name="is_published" value="1" {{ old('is_published') ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600">
                            Publish immediately
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                            <input type="checkbox" name="is_downloadable" value="1" {{ old('is_downloadable') ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600">
                            Allow download
                        </label>
                    </div>
                </div>
            </div>

            {{-- ── Type-specific fields ──────────────────────────────────── --}}

            {{-- VIDEO --}}
            <div x-show="selectedType === 'video'"
                 class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
                <h3 class="font-semibold text-gray-700">Video Content</h3>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Source</label>
                    <div class="flex gap-4">
                        @foreach (['youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'upload' => 'Direct Upload'] as $val => $label)
                        <label class="flex items-center gap-2 cursor-pointer text-sm">
                            <input type="radio" name="video_provider" value="{{ $val }}"
                                   x-model="videoProvider"
                                   {{ old('video_provider', 'youtube') === $val ? 'checked' : '' }}
                                   class="text-indigo-600">
                            {{ $label }}
                        </label>
                        @endforeach
                    </div>
                </div>

                <div x-show="videoProvider !== 'upload'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Video URL</label>
                    <input type="url" name="video_url" value="{{ old('video_url') }}"
                           placeholder="https://www.youtube.com/watch?v=..."
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                </div>

                <div x-show="videoProvider === 'upload'" class="space-y-3">
                    <label class="block text-sm font-medium text-gray-700">Upload Video File</label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center"
                         @dragover.prevent @drop.prevent="handleDrop($event, 'video_file')">
                        <svg class="mx-auto w-10 h-10 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                        </svg>
                        <p class="text-sm text-gray-600 mb-2">Drag & drop video or <label class="text-indigo-600 cursor-pointer hover:underline">browse<input type="file" name="video_file" id="videoFileInput" accept="video/*" class="hidden" @change="fileSelected($event, 'videoFileName')"></label></p>
                        <p class="text-xs text-gray-400">MP4, WebM, MKV, MOV — max 2 GB</p>
                        <p x-text="videoFileName" class="mt-2 text-sm font-medium text-indigo-600" x-show="videoFileName"></p>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                        <input type="checkbox" name="video_watermark" value="1" {{ old('video_watermark') ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600">
                        Apply watermark during processing
                    </label>
                </div>
            </div>

            {{-- TEXT / ARTICLE --}}
            <div x-show="selectedType === 'text'"
                 class="bg-white rounded-xl shadow-sm border p-6 space-y-3">
                <h3 class="font-semibold text-gray-700">Article Content</h3>
                <p class="text-xs text-gray-500">Supports HTML. Use the toolbar for formatting.</p>

                {{-- Mini toolbar --}}
                <div class="flex flex-wrap gap-1 border border-gray-200 rounded-t-lg bg-gray-50 px-2 py-1">
                    @foreach ([
                        ['bold',          'B',  'font-bold'],
                        ['italic',        'I',  'italic'],
                        ['insertOrderedList',  'OL', ''],
                        ['insertUnorderedList','UL', ''],
                    ] as [$cmd, $lbl, $cls])
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
                    {!! old('content') !!}
                </div>
                <textarea name="content" id="articleContent" class="hidden" required>{{ old('content') }}</textarea>
            </div>

            {{-- PDF --}}
            <div x-show="selectedType === 'pdf'"
                 class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
                <h3 class="font-semibold text-gray-700">PDF Document</h3>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload PDF <span class="text-gray-400">(or use URL below)</span></label>
                    <input type="file" name="pdf_file" accept=".pdf"
                           class="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    <p class="text-xs text-gray-400 mt-1">Max 50 MB</p>
                </div>
                <div class="relative flex items-center">
                    <div class="flex-grow border-t border-gray-200"></div>
                    <span class="mx-3 text-xs text-gray-400">OR</span>
                    <div class="flex-grow border-t border-gray-200"></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">External PDF URL</label>
                    <input type="url" name="external_url" value="{{ old('external_url') }}"
                           placeholder="https://example.com/document.pdf"
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                </div>
            </div>

            {{-- AUDIO --}}
            <div x-show="selectedType === 'audio'"
                 class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
                <h3 class="font-semibold text-gray-700">Audio Content</h3>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload Audio File <span class="text-gray-400">(or use URL below)</span></label>
                    <input type="file" name="audio_file" accept=".mp3,.aac,.ogg,.wav,.m4a"
                           class="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    <p class="text-xs text-gray-400 mt-1">MP3, AAC, OGG, WAV, M4A — max 200 MB</p>
                </div>
                <div class="relative flex items-center">
                    <div class="flex-grow border-t border-gray-200"></div>
                    <span class="mx-3 text-xs text-gray-400">OR</span>
                    <div class="flex-grow border-t border-gray-200"></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">External Audio URL</label>
                    <input type="url" name="external_url" value="{{ old('external_url') }}"
                           placeholder="https://example.com/podcast.mp3"
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                </div>
            </div>

            {{-- PRESENTATION --}}
            <div x-show="selectedType === 'presentation'"
                 class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
                <h3 class="font-semibold text-gray-700">Presentation / Slides</h3>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload File <span class="text-gray-400">(PDF or PPTX)</span></label>
                    <input type="file" name="presentation_file" accept=".pdf,.ppt,.pptx"
                           class="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    <p class="text-xs text-gray-400 mt-1">Max 100 MB. PDF slides are rendered in-browser; PPTX requires download.</p>
                </div>
                <div class="relative flex items-center">
                    <div class="flex-grow border-t border-gray-200"></div>
                    <span class="mx-3 text-xs text-gray-400">OR</span>
                    <div class="flex-grow border-t border-gray-200"></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">External Slides URL</label>
                    <input type="url" name="external_url" value="{{ old('external_url') }}"
                           placeholder="https://docs.google.com/presentation/..."
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                </div>
            </div>

            {{-- EXTERNAL --}}
            <div x-show="selectedType === 'external'"
                 class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
                <h3 class="font-semibold text-gray-700">External Resource</h3>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">URL <span class="text-red-500">*</span></label>
                    <input type="url" name="external_url" value="{{ old('external_url') }}"
                           placeholder="https://..."
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                           required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description <span class="text-gray-400">(optional)</span></label>
                    <textarea name="content" rows="3"
                              class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                              placeholder="What will students find at this link?">{{ old('content') }}</textarea>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('instructor.courses.wizard', [$course->slug, 'step' => 3]) }}"
                   class="text-sm text-gray-500 hover:text-gray-700">← Back to Curriculum</a>

                <div class="flex gap-3">
                    <button type="submit" name="is_published" value="0"
                            class="px-5 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">
                        Save as Draft
                    </button>
                    <button type="submit" name="is_published" value="1"
                            class="px-5 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                        Save & Publish
                    </button>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
    function lessonCreateApp() {
        return {
            selectedType: null,
            videoProvider: '{{ old('video_provider', 'youtube') }}',
            videoFileName: '',

            get formAction() {
                return '{{ route('instructor.lessons.store', [$course->slug, $section->id]) }}';
            },

            init() {
                @if(old('type'))
                this.selectedType = '{{ old('type') }}';
                @endif

                // Sync hidden textarea from contenteditable on submit
                const form = document.querySelector('form');
                form?.addEventListener('submit', () => {
                    const editor  = document.getElementById('articleEditor');
                    const hidden  = document.getElementById('articleContent');
                    if (editor && hidden) hidden.value = editor.innerHTML;
                });
            },

            fileSelected(event, varName) {
                this[varName] = event.target.files[0]?.name || '';
            },

            handleDrop(event, inputName) {
                const input = document.querySelector(`input[name="${inputName}"]`);
                if (input && event.dataTransfer.files.length) {
                    const dt = new DataTransfer();
                    dt.items.add(event.dataTransfer.files[0]);
                    input.files = dt.files;
                    this.videoFileName = event.dataTransfer.files[0].name;
                }
            },
        };
    }

    function wrapCode() {
        const sel = window.getSelection();
        if (sel.rangeCount) {
            const range   = sel.getRangeAt(0);
            const code    = document.createElement('code');
            code.className = 'bg-gray-100 px-1 rounded font-mono text-sm';
            range.surroundContents(code);
        }
    }
    </script>
    @endpush
</x-app-layout>
