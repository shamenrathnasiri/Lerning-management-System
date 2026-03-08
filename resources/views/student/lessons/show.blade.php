<x-app-layout>
    @push('head')
    {{-- Code highlighting (text lessons) --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js" defer></script>
    @endpush

    <div class="flex h-[calc(100vh-4rem)] overflow-hidden bg-gray-50"
         x-data="lessonViewer()"
         x-init="init()">

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{--  LEFT: Course Outline Sidebar                                   --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <aside :class="outlineOpen ? 'w-72' : 'w-0'"
               class="flex-shrink-0 overflow-y-auto bg-white border-r transition-all duration-300 hidden lg:block">
            <div class="sticky top-0 bg-white border-b px-4 py-3 z-10">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Course Content</p>
                <p class="text-sm font-medium text-gray-800 mt-0.5 truncate">{{ $course->title }}</p>
                @if($enrollment)
                <div class="mt-2 flex items-center gap-2">
                    <div class="flex-1 bg-gray-200 rounded-full h-1.5">
                        <div class="bg-indigo-600 h-1.5 rounded-full"
                             style="width: {{ $enrollment->progress_percentage }}%"></div>
                    </div>
                    <span class="text-xs text-gray-500">{{ $enrollment->progress_percentage }}%</span>
                </div>
                @endif
            </div>

            @foreach($outline as $sec)
            <div class="border-b last:border-b-0">
                <div class="px-4 py-2.5 bg-gray-50">
                    <p class="text-xs font-semibold text-gray-600 leading-snug">{{ $sec->title }}</p>
                </div>
                @foreach($sec->lessons as $outLesson)
                <a href="{{ route('student.lessons.show', [$course->slug, $outLesson->slug]) }}"
                   class="flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-indigo-50 transition-colors
                          {{ $outLesson->id === $lesson->id ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-700' }}">
                    {{-- Completion check --}}
                    @if(in_array($outLesson->id, $completedIds))
                        <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                    @else
                        <span class="w-4 h-4 flex-shrink-0 rounded-full border-2
                              {{ $outLesson->id === $lesson->id ? 'border-indigo-500' : 'border-gray-300' }}"></span>
                    @endif
                    <span class="flex-1 truncate text-xs leading-snug">{{ $outLesson->title }}</span>
                    <span class="text-xs text-gray-400 flex-shrink-0">{{ $outLesson->formatted_duration }}</span>
                </a>
                @endforeach
            </div>
            @endforeach
        </aside>

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{--  MAIN: Lesson Content                                           --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <div class="flex-1 flex flex-col overflow-hidden">

            {{-- Top Bar --}}
            <header class="flex items-center gap-3 px-4 py-3 bg-white border-b flex-shrink-0">
                <button @click="outlineOpen = !outlineOpen"
                        class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500 lg:block hidden">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                </button>
                <div class="flex-1 min-w-0">
                    <nav class="flex items-center gap-1 text-xs text-gray-400">
                        <a href="#" class="hover:text-gray-600 truncate">{{ Str::limit($course->title, 30) }}</a>
                        <span>/</span>
                        <span class="text-gray-600 truncate">{{ $lesson->section->title }}</span>
                    </nav>
                    <h1 class="text-sm font-semibold text-gray-800 truncate">{{ $lesson->title }}</h1>
                </div>
                <div class="flex items-center gap-2">
                    @auth
                    <button @click="notesOpen = !notesOpen"
                            :class="notesOpen ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Notes <span class="bg-indigo-200 text-indigo-800 px-1.5 py-0.5 rounded-full text-xs" x-text="notes.length" x-show="notes.length > 0"></span>
                    </button>
                    @endauth
                </div>
            </header>

            {{-- Scrollable content area --}}
            <div class="flex-1 overflow-y-auto">
                <div class="max-w-3xl mx-auto px-4 py-6 pb-24">

                    @if(session('success'))
                    <div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-700">
                        {{ session('success') }}
                    </div>
                    @endif

                    {{-- ───── VIDEO ───── --}}
                    @if($lesson->type === 'video')
                    <div class="mb-6">
                        @php
                            $provider = $lesson->video_provider;
                            $videoId  = null;
                            if ($provider === 'youtube' && $lesson->video_url) {
                                preg_match('/(?:v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $lesson->video_url, $m);
                                $videoId = $m[1] ?? null;
                            } elseif ($provider === 'vimeo' && $lesson->video_url) {
                                preg_match('/vimeo\.com\/(\d+)/', $lesson->video_url, $m);
                                $videoId = $m[1] ?? null;
                            }
                        @endphp

                        @if($provider === 'youtube' && $videoId)
                        <div class="relative w-full rounded-xl overflow-hidden bg-black" style="padding-bottom:56.25%">
                            <iframe class="absolute inset-0 w-full h-full"
                                    src="https://www.youtube-nocookie.com/embed/{{ $videoId }}?rel=0"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen></iframe>
                        </div>

                        @elseif($provider === 'vimeo' && $videoId)
                        <div class="relative w-full rounded-xl overflow-hidden bg-black" style="padding-bottom:56.25%">
                            <iframe class="absolute inset-0 w-full h-full"
                                    src="https://player.vimeo.com/video/{{ $videoId }}?title=0&byline=0&portrait=0"
                                    allow="autoplay; fullscreen; picture-in-picture"
                                    allowfullscreen></iframe>
                        </div>

                        @elseif($provider === 'upload' && $lesson->processing_status === 'completed' && $lesson->video_quality_urls)
                        {{-- HTML5 player with quality selector --}}
                        <div x-data="videoPlayer()" class="space-y-2">
                            <video id="lessonVideo"
                                   controls
                                   class="w-full rounded-xl bg-black"
                                   :src="currentSrc"
                                   @timeupdate="onTimeUpdate"
                                   @ended="onEnded">
                            </video>
                            @if(count($lesson->video_quality_urls) > 1)
                            <div class="flex items-center gap-2 text-xs text-gray-600">
                                <span>Quality:</span>
                                @foreach($lesson->video_quality_urls as $quality => $s3key)
                                <button @click="setQuality('{{ $quality }}', '{{ route('student.lessons.stream', [$course->slug, $lesson->slug, 'quality' => $quality]) }}')"
                                        :class="activeQuality === '{{ $quality }}' ? 'bg-indigo-600 text-white' : 'bg-gray-200'"
                                        class="px-2 py-0.5 rounded font-medium transition-colors">
                                    {{ strtoupper($quality) }}
                                </button>
                                @endforeach
                            </div>
                            @endif
                        </div>

                        @elseif($lesson->is_processing)
                        <div class="flex items-center gap-3 rounded-xl bg-blue-50 border border-blue-200 p-6">
                            <svg class="animate-spin w-6 h-6 text-blue-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            <div>
                                <p class="font-medium text-blue-800">Video is currently being processed.</p>
                                <p class="text-sm text-blue-600">Please check back in a few minutes.</p>
                            </div>
                        </div>
                        @else
                        <div class="rounded-xl bg-gray-100 p-6 text-center text-gray-500 text-sm">Video is not available yet.</div>
                        @endif
                    </div>
                    @endif

                    {{-- ───── TEXT / ARTICLE ───── --}}
                    @if($lesson->type === 'text')
                    <div class="prose prose-sm max-w-none mb-6 bg-white rounded-xl border p-6">
                        {!! $lesson->content !!}
                    </div>
                    @endif

                    {{-- ───── PDF ───── --}}
                    @if($lesson->type === 'pdf')
                    @php $pdfSrc = $lesson->file_path ? Storage::url($lesson->file_path) : $lesson->external_url; @endphp
                    @if($pdfSrc)
                    <div class="mb-6 space-y-3">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-gray-700">PDF Document</h2>
                            @if($lesson->is_downloadable)
                            <a href="{{ $pdfSrc }}" download class="flex items-center gap-1.5 text-xs text-indigo-600 hover:underline">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                Download PDF
                            </a>
                            @endif
                        </div>
                        <iframe src="{{ $pdfSrc }}"
                                class="w-full rounded-xl border"
                                style="height: 75vh;"
                                title="{{ $lesson->title }}">
                            <p class="text-sm text-gray-600">Your browser does not support inline PDF viewing.
                                <a href="{{ $pdfSrc }}" class="text-indigo-600 underline">Download the PDF</a>.
                            </p>
                        </iframe>
                    </div>
                    @endif
                    @endif

                    {{-- ───── AUDIO ───── --}}
                    @if($lesson->type === 'audio')
                    @php $audioSrc = $lesson->file_path ? Storage::url($lesson->file_path) : $lesson->external_url; @endphp
                    @if($audioSrc)
                    <div class="mb-6 bg-gradient-to-br from-indigo-50 to-purple-50 rounded-2xl p-6 border"
                         x-data="audioPlayer()">
                        {{-- Album art / icon --}}
                        <div class="flex items-center gap-4 mb-5">
                            <div class="w-16 h-16 rounded-xl bg-indigo-600 flex items-center justify-center flex-shrink-0">
                                <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m9 9 10.5-3m0 6.553v3.75a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 1 1-.99-3.467l2.31-.66a2.25 2.25 0 0 0 1.632-2.163Zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 0 1-.99-3.467l2.31-.66A2.25 2.25 0 0 0 9 15.553Z"/></svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-800">{{ $lesson->title }}</h3>
                                <p class="text-sm text-gray-500">{{ $course->title }}</p>
                            </div>
                        </div>

                        {{-- Progress bar --}}
                        <div class="mb-3">
                            <div class="flex justify-between text-xs text-gray-500 mb-1">
                                <span x-text="formatTime(currentTime)">0:00</span>
                                <span x-text="formatTime(duration)">0:00</span>
                            </div>
                            <input type="range" min="0" :max="duration" :value="currentTime"
                                   @input="seek($event.target.value)"
                                   class="w-full h-2 rounded-full accent-indigo-600 cursor-pointer">
                        </div>

                        {{-- Controls --}}
                        <div class="flex items-center justify-center gap-4">
                            <button @click="skip(-15)" class="text-gray-600 hover:text-gray-800">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                            </button>
                            <button @click="togglePlay"
                                    class="w-12 h-12 rounded-full bg-indigo-600 text-white flex items-center justify-center hover:bg-indigo-700 transition-colors">
                                <svg x-show="!playing" class="w-6 h-6 ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                <svg x-show="playing" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                            </button>
                            <button @click="skip(15)" class="text-gray-600 hover:text-gray-800">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15V9m-3 6l3-3-3-3M3 9v6l3-3-3-3"/></svg>
                            </button>
                        </div>

                        {{-- Download --}}
                        @if($lesson->is_downloadable)
                        <div class="mt-4 text-center">
                            <a href="{{ $audioSrc }}" download class="text-xs text-indigo-600 hover:underline">⬇ Download Audio</a>
                        </div>
                        @endif

                        <audio id="audioEl" :src="src" @timeupdate="currentTime = $el.currentTime" @loadedmetadata="duration = $el.duration" @ended="playing = false; onEnded()" x-init="src = '{{ $audioSrc }}'"></audio>
                    </div>
                    @endif
                    @endif

                    {{-- ───── PRESENTATION ───── --}}
                    @if($lesson->type === 'presentation')
                    @php $presSrc = $lesson->file_path ? Storage::url($lesson->file_path) : $lesson->external_url; @endphp
                    @if($presSrc)
                    <div class="mb-6 space-y-3">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-gray-700">Presentation</h2>
                            @if($lesson->is_downloadable)
                            <a href="{{ $presSrc }}" download class="text-xs text-indigo-600 hover:underline flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                Download
                            </a>
                            @endif
                        </div>
                        @php $ext = strtolower(pathinfo($presSrc, PATHINFO_EXTENSION)); @endphp
                        @if($ext === 'pdf')
                        <iframe src="{{ $presSrc }}" class="w-full rounded-xl border" style="height:75vh" title="{{ $lesson->title }}"></iframe>
                        @else
                        <div class="rounded-xl border bg-orange-50 p-6 text-center">
                            <p class="text-sm text-orange-700 mb-3">This presentation is in PowerPoint format and cannot be displayed directly in the browser.</p>
                            <a href="{{ $presSrc }}" download
                               class="inline-flex items-center gap-2 px-4 py-2 bg-orange-600 text-white text-sm rounded-lg hover:bg-orange-700">
                                Download to View
                            </a>
                        </div>
                        @endif
                    </div>
                    @endif
                    @endif

                    {{-- ───── EXTERNAL LINK ───── --}}
                    @if($lesson->type === 'external')
                    <div class="mb-6 rounded-xl border bg-gradient-to-br from-gray-50 to-white p-6">
                        @if($lesson->content)
                        <p class="text-sm text-gray-700 mb-4">{{ $lesson->content }}</p>
                        @endif
                        <a href="{{ $lesson->external_url }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white rounded-xl font-medium hover:bg-indigo-700 transition-colors">
                            Open Resource
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                        <p class="mt-2 text-xs text-gray-400 truncate">{{ $lesson->external_url }}</p>
                    </div>
                    @endif

                    {{-- ───── Lesson Description / Resources ───── --}}
                    @if($lesson->resources && count($lesson->resources))
                    <div class="mt-6 bg-white rounded-xl border p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Resources</h3>
                        <ul class="space-y-2">
                            @foreach($lesson->resources as $resource)
                            <li class="flex items-center gap-2 text-sm">
                                <svg class="w-4 h-4 text-indigo-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                @if(is_array($resource) && isset($resource['url']))
                                <a href="{{ $resource['url'] }}" target="_blank" class="text-indigo-600 hover:underline">{{ $resource['name'] ?? basename($resource['url']) }}</a>
                                @else
                                <span>{{ $resource }}</span>
                                @endif
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </div>
            </div>

            {{-- ── Bottom Navigation Bar ────────────────────────────────── --}}
            <div class="flex-shrink-0 border-t bg-white px-4 py-3 flex items-center justify-between">
                @if($prevLesson)
                <a href="{{ route('student.lessons.show', [$course->slug, $prevLesson->slug]) }}"
                   class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    <span class="hidden sm:block truncate max-w-40">{{ $prevLesson->title }}</span>
                </a>
                @else
                <span></span>
                @endif

                @auth
                <form action="{{ route('student.lessons.complete', [$course->slug, $lesson->slug]) }}"
                      method="POST" id="completeForm">
                    @csrf
                    <button type="submit"
                            id="completeBtn"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all
                                   {{ $progress?->is_completed
                                      ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                      : 'bg-indigo-600 text-white hover:bg-indigo-700' }}">
                        @if($progress?->is_completed)
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Completed
                        @else
                            Mark Complete
                        @endif
                    </button>
                </form>
                @endauth

                @if($nextLesson)
                <a href="{{ route('student.lessons.show', [$course->slug, $nextLesson->slug]) }}"
                   class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900">
                    <span class="hidden sm:block truncate max-w-40">{{ $nextLesson->title }}</span>
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
                @else
                <span></span>
                @endif
            </div>
        </div>{{-- end main --}}

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{--  RIGHT: Notes Panel (slide-in drawer)                          --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @auth
        <aside :class="notesOpen ? 'w-80 border-l' : 'w-0'"
               class="flex-shrink-0 overflow-hidden bg-white transition-all duration-300 flex flex-col">

            <div class="flex-shrink-0 px-4 py-3 border-b flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-800">My Notes</h2>
                <button @click="notesOpen = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Add Note --}}
            <div class="flex-shrink-0 px-4 py-3 border-b bg-gray-50">
                <div class="space-y-2">
                    <textarea x-model="newNoteContent"
                              @keydown.ctrl.enter="addNote()"
                              rows="3"
                              placeholder="Write a note… (Ctrl+Enter to save)"
                              class="w-full text-xs rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 resize-none"></textarea>
                    <div class="flex items-center gap-2">
                        {{-- Color picker --}}
                        @foreach(['yellow' => 'bg-yellow-300', 'blue' => 'bg-blue-300', 'green' => 'bg-green-300', 'red' => 'bg-red-300', 'purple' => 'bg-purple-300'] as $col => $cls)
                        <button type="button"
                                @click="newNoteColor = '{{ $col }}'"
                                :class="newNoteColor === '{{ $col }}' ? 'ring-2 ring-offset-1 ring-gray-400' : ''"
                                class="{{ $cls }} w-5 h-5 rounded-full flex-shrink-0"></button>
                        @endforeach
                        <button @click="addNote()"
                                :disabled="!newNoteContent.trim()"
                                class="ml-auto text-xs px-3 py-1 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-40">
                            Save
                        </button>
                    </div>
                </div>
            </div>

            {{-- Notes list --}}
            <div class="flex-1 overflow-y-auto px-4 py-3 space-y-3">
                <template x-if="notes.length === 0">
                    <p class="text-xs text-gray-400 text-center py-6">No notes yet. Start annotating!</p>
                </template>
                <template x-for="note in notes" :key="note.id">
                    <div :class="{
                            'border-yellow-300 bg-yellow-50': note.color === 'yellow',
                            'border-blue-300 bg-blue-50':    note.color === 'blue',
                            'border-green-300 bg-green-50':  note.color === 'green',
                            'border-red-300 bg-red-50':      note.color === 'red',
                            'border-purple-300 bg-purple-50':note.color === 'purple',
                         }"
                         class="rounded-lg border p-3 text-xs space-y-1 group relative">

                        <div x-show="!note._editing" class="text-gray-800 whitespace-pre-wrap" x-text="note.content"></div>

                        <textarea x-show="note._editing"
                                  x-model="note._draft"
                                  rows="3"
                                  class="w-full text-xs rounded border-gray-300 resize-none bg-white"></textarea>

                        <div class="flex items-center justify-between">
                            <span class="text-gray-400" x-text="note.timestamp_seconds !== null ? formatTimestamp(note.timestamp_seconds) : ''"></span>
                            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <template x-if="!note._editing">
                                    <button @click="startEdit(note)" class="text-gray-400 hover:text-indigo-600">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                </template>
                                <template x-if="note._editing">
                                    <button @click="saveEdit(note)" class="text-green-600 hover:text-green-700">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                </template>
                                <button @click="deleteNote(note)" class="text-gray-400 hover:text-red-600">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </aside>
        @endauth

    </div>{{-- end flex container --}}

    @push('scripts')
    <script>
    function lessonViewer() {
        return {
            outlineOpen: true,
            notesOpen: false,
            notes: @json($notes),
            newNoteContent: '',
            newNoteColor: 'yellow',

            init() {
                // Apply highlight.js once DOM is settled
                this.$nextTick(() => {
                    if (typeof hljs !== 'undefined') hljs.highlightAll();
                });
            },

            async addNote() {
                if (!this.newNoteContent.trim()) return;

                const timestamp = this.getCurrentVideoTimestamp();

                const res = await fetch('{{ route('student.lessons.notes.store', [$course->slug, $lesson->slug]) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        content: this.newNoteContent,
                        color: this.newNoteColor,
                        timestamp_seconds: timestamp,
                    }),
                });

                if (res.ok) {
                    const note = await res.json();
                    note._editing = false;
                    note._draft   = note.content;
                    this.notes.push(note);
                    this.notes.sort((a,b) => (a.timestamp_seconds ?? Infinity) - (b.timestamp_seconds ?? Infinity));
                    this.newNoteContent = '';
                }
            },

            startEdit(note) {
                note._draft   = note.content;
                note._editing = true;
            },

            async saveEdit(note) {
                const res = await fetch('{{ route('student.lessons.notes.update', [$course->slug, $lesson->slug, '__NOTE__']) }}'.replace('__NOTE__', note.id), {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ content: note._draft, color: note.color }),
                });
                if (res.ok) {
                    const updated  = await res.json();
                    note.content   = updated.content;
                    note._editing  = false;
                }
            },

            async deleteNote(note) {
                if (!confirm('Delete this note?')) return;
                const res = await fetch('{{ route('student.lessons.notes.destroy', [$course->slug, $lesson->slug, '__NOTE__']) }}'.replace('__NOTE__', note.id), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });
                if (res.ok) this.notes = this.notes.filter(n => n.id !== note.id);
            },

            formatTimestamp(seconds) {
                if (seconds == null) return '';
                const h = Math.floor(seconds / 3600);
                const m = Math.floor((seconds % 3600) / 60);
                const s = seconds % 60;
                return h > 0
                    ? `${h}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`
                    : `${m}:${String(s).padStart(2,'0')}`;
            },

            getCurrentVideoTimestamp() {
                const vid = document.getElementById('lessonVideo') || document.getElementById('audioEl');
                return vid && !isNaN(vid.currentTime) ? Math.floor(vid.currentTime) : null;
            },
        };
    }

    function videoPlayer() {
        return {
            currentSrc: '',
            activeQuality: '',

            init() {
                // Default to best quality available
                const qualities = @json(array_keys($lesson->video_quality_urls ?? []));
                if (qualities.length) {
                    this.setQuality(qualities[qualities.length - 1], null);
                }
            },

            setQuality(quality, url) {
                this.activeQuality = quality;
                // url is a stream route; fall back to s3 signed link via page
                this.currentSrc = url ?? '';
            },

            onTimeUpdate(e) {
                // Throttled progress sync every 10 seconds
                const t = Math.floor(e.target.currentTime);
                if (t > 0 && t % 10 === 0) this.syncProgress(t);
            },

            onEnded() {
                // Auto-mark complete when video finishes
                document.getElementById('completeForm')?.submit();
            },

            syncProgress(seconds) {
                navigator.sendBeacon(
                    '{{ route('student.lessons.progress', [$course->slug, $lesson->slug]) }}',
                    new Blob([JSON.stringify({
                        watch_time_seconds: seconds,
                        _token: document.querySelector('meta[name="csrf-token"]').content,
                    })], { type: 'application/json' })
                );
            },
        };
    }

    function audioPlayer() {
        return {
            src: '',
            playing: false,
            currentTime: 0,
            duration: 0,

            get audioEl() { return document.getElementById('audioEl'); },

            togglePlay() {
                if (this.playing) { this.audioEl.pause(); }
                else              { this.audioEl.play(); }
                this.playing = !this.playing;
            },

            seek(seconds) {
                this.audioEl.currentTime = seconds;
            },

            skip(delta) {
                this.audioEl.currentTime = Math.max(0, Math.min(this.duration, this.audioEl.currentTime + delta));
            },

            onEnded() {
                document.getElementById('completeForm')?.submit();
            },

            formatTime(s) {
                s = Math.floor(s || 0);
                const m = Math.floor(s / 60);
                const sec = s % 60;
                return `${m}:${String(sec).padStart(2,'0')}`;
            },
        };
    }
    </script>
    @endpush
</x-app-layout>
