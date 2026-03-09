<x-app-layout>
    @php
        $curriculumData = [];
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-900 leading-tight">Course Wizard: {{ $course->title }}</h2>
                <p class="text-sm text-gray-500 mt-1">Step {{ $step }} of 4</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('instructor.courses.preview', $course) }}" target="_blank" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    Preview Mode
                </a>
                <form method="POST" action="{{ route('instructor.courses.duplicate', $course) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        Duplicate
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm">
                    <p class="font-semibold">Please fix the following issues:</p>
                    <ul class="list-disc pl-5 mt-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    @for ($s = 1; $s <= 4; $s++)
                        <a href="{{ route('instructor.courses.wizard', [$course, 'step' => $s]) }}"
                           class="rounded-lg border px-3 py-3 text-sm transition
                               {{ $step === $s ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-semibold' : 'border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                            <p class="text-xs uppercase tracking-wide">Step {{ $s }}</p>
                            <p>
                                @if ($s === 1)
                                    Basic Information
                                @elseif ($s === 2)
                                    Course Content
                                @elseif ($s === 3)
                                    Curriculum Builder
                                @else
                                    Settings and Publish
                                @endif
                            </p>
                        </a>
                    @endfor
                </div>
            </div>

            @if ($step === 1)
                @php
                    $currentCategory = old('category_id', $course->category_id);
                    $selectedTagsSet = collect(old('tags', $selectedTags ?? []))->map(fn ($v) => (int) $v)->all();
                @endphp

                <form id="step1-form" class="autosave-form bg-white border border-gray-200 rounded-xl shadow-sm p-6 space-y-6" method="POST" action="{{ route('instructor.courses.save-step1', $course) }}" enctype="multipart/form-data" data-autosave="1">
                    @csrf

                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Course title</label>
                            <input id="title-input" type="text" name="title" value="{{ old('title', $course->title) }}" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Slug</label>
                            <input id="slug-input" type="text" name="slug" value="{{ old('slug', $course->slug) }}" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="mt-1 text-xs text-gray-500">Auto-generated from title, but editable.</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Subtitle</label>
                        <input type="text" name="subtitle" value="{{ old('subtitle', $course->subtitle) }}" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Category</label>
                            <select name="category_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Select a category</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" {{ (string) $currentCategory === (string) $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                    @foreach ($category->children as $child)
                                        <option value="{{ $child->id }}" {{ (string) $currentCategory === (string) $child->id ? 'selected' : '' }}>
                                            - {{ $child->name }}
                                        </option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Level</label>
                            <select name="level" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @php $level = old('level', $course->level); @endphp
                                <option value="beginner" {{ $level === 'beginner' ? 'selected' : '' }}>Beginner</option>
                                <option value="intermediate" {{ $level === 'intermediate' ? 'selected' : '' }}>Intermediate</option>
                                <option value="advanced" {{ $level === 'advanced' ? 'selected' : '' }}>Advanced</option>
                                <option value="all_levels" {{ $level === 'all_levels' ? 'selected' : '' }}>All Levels</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tags</label>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($tags as $tag)
                                <label class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-3 py-1 text-sm text-gray-700">
                                    <input type="checkbox" name="tags[]" value="{{ $tag->id }}" {{ in_array($tag->id, $selectedTagsSet, true) ? 'checked' : '' }}>
                                    <span>{{ $tag->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Thumbnail</label>
                            <input id="thumbnail-input" type="file" name="thumbnail" accept="image/*" class="mt-1 block w-full text-sm text-gray-700">
                            <p class="mt-1 text-xs text-gray-500">Upload image then adjust crop before saving.</p>

                            @if ($course->thumbnail)
                                <img src="{{ asset('storage/' . $course->thumbnail) }}" alt="Current thumbnail" class="mt-3 h-32 w-full object-cover rounded-lg border border-gray-200">
                            @endif
                        </div>

                        <div class="rounded-lg border border-gray-200 p-4 space-y-3">
                            <p class="text-sm font-medium text-gray-700">Crop Preview (16:9)</p>
                            <canvas id="thumbnail-canvas" width="640" height="360" class="w-full rounded-md border border-gray-200 bg-gray-50"></canvas>
                            <div class="grid grid-cols-3 gap-2 text-xs">
                                <label class="text-gray-600">Zoom
                                    <input id="crop-zoom" type="range" min="1" max="3" step="0.05" value="1" class="w-full">
                                </label>
                                <label class="text-gray-600">X Offset
                                    <input id="crop-x" type="range" min="-100" max="100" step="1" value="0" class="w-full">
                                </label>
                                <label class="text-gray-600">Y Offset
                                    <input id="crop-y" type="range" min="-100" max="100" step="1" value="0" class="w-full">
                                </label>
                            </div>
                            <button id="apply-crop" type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Apply Crop To Upload</button>
                        </div>
                    </div>

                    @php
                        $isFree = old('is_free', $course->is_free);
                    @endphp
                    <div class="rounded-lg border border-gray-200 p-4">
                        <p class="text-sm font-medium text-gray-700">Pricing</p>
                        <label class="mt-3 inline-flex items-center gap-2 text-sm text-gray-700">
                            <input id="is-free-checkbox" type="checkbox" name="is_free" value="1" {{ $isFree ? 'checked' : '' }}>
                            This is a free course
                        </label>

                        <div id="paid-pricing-fields" class="mt-3 grid md:grid-cols-2 gap-4 {{ $isFree ? 'hidden' : '' }}">
                            <div>
                                <label class="block text-sm text-gray-700">Price</label>
                                <input type="number" name="price" min="0" step="0.01" value="{{ old('price', $course->price) }}" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-700">Discount price</label>
                                <input type="number" name="discount_price" min="0" step="0.01" value="{{ old('discount_price', $course->discount_price) }}" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <a href="{{ route('instructor.courses.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Back to courses</a>
                        <x-primary-button>Save and continue</x-primary-button>
                    </div>
                </form>
            @endif

            @if ($step === 2)
                @php
                    $requirements = old('requirements', $course->requirements ?? ['']);
                    $objectives = old('what_you_will_learn', $course->what_you_will_learn ?? ['']);
                    $audience = old('target_audience', $course->target_audience ?? ['']);
                @endphp

                <form id="step2-form" class="autosave-form bg-white border border-gray-200 rounded-xl shadow-sm p-6 space-y-6" method="POST" action="{{ route('instructor.courses.save-step2', $course) }}" data-autosave="1">
                    @csrf

                    <div>
                        <div class="flex items-center justify-between">
                            <label class="block text-sm font-medium text-gray-700">Description (WYSIWYG)</label>
                            <div class="flex gap-1 text-xs">
                                <button type="button" class="wysiwyg-btn rounded border px-2 py-1" data-cmd="bold">Bold</button>
                                <button type="button" class="wysiwyg-btn rounded border px-2 py-1" data-cmd="italic">Italic</button>
                                <button type="button" class="wysiwyg-btn rounded border px-2 py-1" data-cmd="insertUnorderedList">List</button>
                            </div>
                        </div>
                        <div id="description-editor" contenteditable="true" class="mt-2 min-h-48 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">{!! old('description', $course->description) !!}</div>
                        <textarea id="description-field" name="description" class="hidden">{{ old('description', $course->description) }}</textarea>
                    </div>

                    <div class="grid lg:grid-cols-3 gap-6">
                        <div>
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-800">Requirements</h3>
                                <button type="button" class="list-add-btn text-xs text-indigo-600" data-target="requirements-list">+ Add</button>
                            </div>
                            <div id="requirements-list" class="mt-2 space-y-2">
                                @foreach ($requirements as $item)
                                    <input type="text" name="requirements[]" value="{{ $item }}" class="w-full rounded-md border-gray-300 text-sm" placeholder="Requirement item">
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-800">What Students Will Learn</h3>
                                <button type="button" class="list-add-btn text-xs text-indigo-600" data-target="objectives-list">+ Add</button>
                            </div>
                            <div id="objectives-list" class="mt-2 space-y-2">
                                @foreach ($objectives as $item)
                                    <input type="text" name="what_you_will_learn[]" value="{{ $item }}" class="w-full rounded-md border-gray-300 text-sm" placeholder="Learning objective">
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-800">Target Audience</h3>
                                <button type="button" class="list-add-btn text-xs text-indigo-600" data-target="audience-list">+ Add</button>
                            </div>
                            <div id="audience-list" class="mt-2 space-y-2">
                                @foreach ($audience as $item)
                                    <input type="text" name="target_audience[]" value="{{ $item }}" class="w-full rounded-md border-gray-300 text-sm" placeholder="Audience item">
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <a href="{{ route('instructor.courses.wizard', [$course, 'step' => 1]) }}" class="text-sm text-gray-600 hover:text-gray-900">Back</a>
                        <x-primary-button>Save and continue</x-primary-button>
                    </div>
                </form>
            @endif

            @if ($step === 3)
                @php
                    $curriculumData = ($course->relationLoaded('sections') ? $course->sections : collect())->map(function ($section) {
                        return [
                            'id' => $section->id,
                            'title' => $section->title,
                            'description' => $section->description,
                            'sort_order' => $section->sort_order,
                            'collapsed' => false,
                            'lessons' => $section->lessons->map(function ($lesson) {
                                return [
                                    'id' => $lesson->id,
                                    'slug' => $lesson->slug,
                                    'title' => $lesson->title,
                                    'type' => $lesson->type,
                                    'duration_minutes' => $lesson->duration_minutes,
                                    'is_free_preview' => (bool) $lesson->is_free_preview,
                                ];
                            })->values()->all(),
                        ];
                    })->values()->all();

                    $curriculumConfig = [
                        'sections' => $curriculumData,
                        'previewUrl' => route('instructor.courses.preview', $course),
                        'continueUrl' => route('instructor.courses.save-step3', $course),
                        'endpoints' => [
                            'addSection' => route('instructor.curriculum.sections.add', $course),
                            'addLesson' => url('/instructor/courses/' . $course->slug . '/curriculum/sections/__SECTION__/lessons'),
                            'reorderSections' => route('instructor.curriculum.sections.reorder', $course),
                            'reorderLessons' => route('instructor.curriculum.lessons.reorder', $course),
                            'deleteSection' => url('/instructor/courses/' . $course->slug . '/curriculum/sections/__SECTION__'),
                            'autosave' => route('instructor.curriculum.autosave', $course),
                            'export' => route('instructor.curriculum.export', $course),
                            'import' => route('instructor.curriculum.import', $course),
                        ],
                    ];
                @endphp

                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div id="curriculum-builder-root"></div>
                    <script id="curriculum-builder-config" type="application/json">@json($curriculumConfig)</script>
                </div>
            @endif

            @if ($step === 4)
                <div class="grid lg:grid-cols-3 gap-6">
                    <form id="step4-form" class="autosave-form lg:col-span-2 bg-white border border-gray-200 rounded-xl shadow-sm p-6 space-y-6" method="POST" action="{{ route('instructor.courses.save-step4', $course) }}" data-autosave="1">
                        @csrf

                        <h3 class="text-lg font-semibold text-gray-900">Settings and Publishing</h3>

                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Course language</label>
                                <select name="language" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                    @foreach ($availableLanguages as $code => $name)
                                        <option value="{{ $code }}" {{ old('language', $course->language) === $code ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Difficulty level</label>
                                <select name="difficulty_level" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                    @php $difficulty = old('difficulty_level', $course->level); @endphp
                                    <option value="beginner" {{ $difficulty === 'beginner' ? 'selected' : '' }}>Beginner</option>
                                    <option value="intermediate" {{ $difficulty === 'intermediate' ? 'selected' : '' }}>Intermediate</option>
                                    <option value="advanced" {{ $difficulty === 'advanced' ? 'selected' : '' }}>Advanced</option>
                                    <option value="all_levels" {{ $difficulty === 'all_levels' ? 'selected' : '' }}>All Levels</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Certificate template</label>
                            <select name="certificate_template" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                @foreach ($certificateTemplates as $template)
                                    <option value="{{ $template }}" {{ old('certificate_template', $course->certificate_template ?? 'classic') === $template ? 'selected' : '' }}>
                                        {{ ucfirst($template) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="rounded-lg border border-gray-200 p-4 space-y-3">
                            <h4 class="text-sm font-semibold text-gray-900">SEO Meta Tags</h4>
                            <div>
                                <label class="block text-sm text-gray-700">Meta title</label>
                                <input type="text" name="meta_title" value="{{ old('meta_title', $course->meta_title) }}" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-700">Meta description</label>
                                <textarea name="meta_description" rows="3" class="mt-1 w-full rounded-md border-gray-300 text-sm">{{ old('meta_description', $course->meta_description) }}</textarea>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-700">Meta keywords</label>
                                <input type="text" name="meta_keywords" value="{{ old('meta_keywords', $course->meta_keywords) }}" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="laravel, programming, web development">
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 p-4 space-y-3">
                            <h4 class="text-sm font-semibold text-gray-900">Publish options</h4>
                            @php
                                $publishMode = old('publish_mode', $course->status === 'published' ? 'now' : ($course->scheduled_publish_at ? 'schedule' : 'draft'));
                            @endphp
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="radio" name="publish_mode" value="draft" {{ $publishMode === 'draft' ? 'checked' : '' }}>
                                Save as draft
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="radio" name="publish_mode" value="now" {{ $publishMode === 'now' ? 'checked' : '' }}>
                                Publish now
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="radio" name="publish_mode" value="schedule" {{ $publishMode === 'schedule' ? 'checked' : '' }}>
                                Schedule publishing
                            </label>

                            <div id="schedule-wrapper" class="{{ $publishMode === 'schedule' ? '' : 'hidden' }}">
                                <label class="block text-sm text-gray-700">Schedule date and time</label>
                                <input type="datetime-local" name="scheduled_publish_at" value="{{ old('scheduled_publish_at', optional($course->scheduled_publish_at)->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <a href="{{ route('instructor.courses.wizard', [$course, 'step' => 3]) }}" class="text-sm text-gray-600 hover:text-gray-900">Back</a>
                            <x-primary-button>Save course</x-primary-button>
                        </div>
                    </form>

                    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
                        <h4 class="text-sm font-semibold text-gray-900">Version History</h4>
                        <p class="text-xs text-gray-500 mt-1">Snapshots are created on major wizard and curriculum actions.</p>

                        <div class="mt-4 space-y-2 max-h-[34rem] overflow-auto pr-1">
                            @forelse (($versions ?? collect()) as $version)
                                <div class="rounded-lg border border-gray-200 px-3 py-2">
                                    <p class="text-sm font-medium text-gray-800">{{ str_replace('-', ' ', ucfirst($version->action)) }}</p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ $version->created_at?->format('M d, Y H:i') }}
                                        @if ($version->actor)
                                            by {{ $version->actor->name }}
                                        @endif
                                    </p>
                                    <form method="POST" action="{{ route('instructor.courses.versions.restore', [$course, $version]) }}" class="mt-2" onsubmit="return confirm('Restore this version? Current draft changes will be replaced.');">
                                        @csrf
                                        <button type="submit" class="rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">Restore</button>
                                    </form>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No versions yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif

            <div class="text-xs text-gray-500">Auto-save status: <span id="autosave-status">Idle</span></div>
        </div>
    </div>

    <script>
        (() => {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const autosaveUrl = @json(route('instructor.courses.auto-save', $course));
            const autosaveStatus = document.getElementById('autosave-status');

            const updateAutosaveStatus = (message, isError = false) => {
                if (!autosaveStatus) {
                    return;
                }
                autosaveStatus.textContent = message;
                autosaveStatus.className = isError ? 'text-red-600' : 'text-gray-500';
            };

            let autosaveTimer = null;
            const queueAutosave = (form) => {
                clearTimeout(autosaveTimer);
                autosaveTimer = setTimeout(() => runAutosave(form), 1000);
            };

            const runAutosave = async (form) => {
                try {
                    updateAutosaveStatus('Saving...');
                    const formData = new FormData();

                    for (const element of form.querySelectorAll('input, textarea, select')) {
                        if (!element.name || element.disabled) {
                            continue;
                        }
                        if (element.type === 'file') {
                            continue;
                        }
                        if ((element.type === 'checkbox' || element.type === 'radio') && !element.checked) {
                            continue;
                        }
                        formData.append(element.name, element.value);
                    }

                    const response = await fetch(autosaveUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                    });

                    if (!response.ok) {
                        throw new Error('Auto-save failed');
                    }

                    const payload = await response.json();
                    updateAutosaveStatus(`Saved at ${payload.saved_at}`);
                } catch (error) {
                    updateAutosaveStatus('Auto-save failed', true);
                }
            };

            document.querySelectorAll('.autosave-form[data-autosave="1"]').forEach((form) => {
                form.addEventListener('input', () => queueAutosave(form));
                form.addEventListener('change', () => queueAutosave(form));
            });

            const titleInput = document.getElementById('title-input');
            const slugInput = document.getElementById('slug-input');
            if (titleInput && slugInput) {
                let slugManuallyEdited = false;
                slugInput.addEventListener('input', () => {
                    slugManuallyEdited = true;
                });

                titleInput.addEventListener('input', () => {
                    if (slugManuallyEdited) {
                        return;
                    }
                    slugInput.value = titleInput.value
                        .toLowerCase()
                        .trim()
                        .replace(/[^a-z0-9\s-]/g, '')
                        .replace(/\s+/g, '-')
                        .replace(/-+/g, '-');
                });
            }

            const isFreeCheckbox = document.getElementById('is-free-checkbox');
            const paidPricingFields = document.getElementById('paid-pricing-fields');
            if (isFreeCheckbox && paidPricingFields) {
                isFreeCheckbox.addEventListener('change', () => {
                    paidPricingFields.classList.toggle('hidden', isFreeCheckbox.checked);
                });
            }

            const editor = document.getElementById('description-editor');
            const descriptionField = document.getElementById('description-field');
            if (editor && descriptionField) {
                editor.addEventListener('input', () => {
                    descriptionField.value = editor.innerHTML;
                });

                document.querySelectorAll('.wysiwyg-btn').forEach((button) => {
                    button.addEventListener('click', () => {
                        document.execCommand(button.dataset.cmd, false, null);
                        descriptionField.value = editor.innerHTML;
                        editor.focus();
                    });
                });
            }

            document.querySelectorAll('.list-add-btn').forEach((button) => {
                button.addEventListener('click', () => {
                    const container = document.getElementById(button.dataset.target);
                    if (!container) {
                        return;
                    }

                    const firstInput = container.querySelector('input');
                    if (!firstInput) {
                        return;
                    }

                    const clone = firstInput.cloneNode(true);
                    clone.value = '';
                    container.appendChild(clone);
                });
            });

            const scheduleWrapper = document.getElementById('schedule-wrapper');
            document.querySelectorAll('input[name="publish_mode"]').forEach((radio) => {
                radio.addEventListener('change', () => {
                    if (!scheduleWrapper) {
                        return;
                    }
                    scheduleWrapper.classList.toggle('hidden', radio.value !== 'schedule' || !radio.checked);
                });
            });

            const thumbnailInput = document.getElementById('thumbnail-input');
            const canvas = document.getElementById('thumbnail-canvas');
            if (thumbnailInput && canvas) {
                const ctx = canvas.getContext('2d');
                const zoom = document.getElementById('crop-zoom');
                const offsetX = document.getElementById('crop-x');
                const offsetY = document.getElementById('crop-y');
                const applyCropBtn = document.getElementById('apply-crop');
                const image = new Image();
                let hasImage = false;

                const renderCanvas = () => {
                    if (!hasImage) {
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        return;
                    }

                    const zoomValue = parseFloat(zoom.value || '1');
                    const xValue = parseInt(offsetX.value || '0', 10);
                    const yValue = parseInt(offsetY.value || '0', 10);

                    ctx.clearRect(0, 0, canvas.width, canvas.height);

                    const sw = image.width / zoomValue;
                    const sh = image.height / zoomValue;
                    const sx = Math.max(0, Math.min(image.width - sw, (image.width - sw) / 2 + xValue));
                    const sy = Math.max(0, Math.min(image.height - sh, (image.height - sh) / 2 + yValue));

                    ctx.drawImage(image, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);
                };

                thumbnailInput.addEventListener('change', () => {
                    const file = thumbnailInput.files?.[0];
                    if (!file) {
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = (event) => {
                        image.onload = () => {
                            hasImage = true;
                            renderCanvas();
                        };
                        image.src = event.target.result;
                    };
                    reader.readAsDataURL(file);
                });

                [zoom, offsetX, offsetY].forEach((control) => {
                    control?.addEventListener('input', renderCanvas);
                });

                applyCropBtn?.addEventListener('click', () => {
                    if (!hasImage) {
                        return;
                    }

                    canvas.toBlob((blob) => {
                        if (!blob) {
                            return;
                        }

                        const fileName = `thumb-${Date.now()}.jpg`;
                        const file = new File([blob], fileName, { type: 'image/jpeg' });
                        const transfer = new DataTransfer();
                        transfer.items.add(file);
                        thumbnailInput.files = transfer.files;
                        updateAutosaveStatus('Crop applied. Submit or continue editing.');
                    }, 'image/jpeg', 0.92);
                });
            }

            const board = document.getElementById('curriculum-board');
            if (!board) {
                return;
            }

            const routes = {
                addSection: @json(route('instructor.courses.sections.add', $course)),
                updateSectionBase: @json(url('/instructor/courses/' . $course->slug . '/sections')),
                addLessonBase: @json(url('/instructor/courses/' . $course->slug . '/sections')),
                bulkLessonBase: @json(url('/instructor/courses/' . $course->slug . '/sections')),
                lessonBase: @json(url('/instructor/courses/' . $course->slug . '/lessons')),
                reorder: @json(route('instructor.courses.reorder', $course)),
            };

            const state = {
                sections: @json($curriculumData),
                activeLesson: null,
            };

            const requestJson = async (url, method, payload) => {
                const response = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(payload),
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                return response.json();
            };

            const requestForm = async (url, method, formData) => {
                const response = await fetch(url, {
                    method,
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                return response.json();
            };

            const sectionById = (id) => state.sections.find((section) => Number(section.id) === Number(id));
            const lessonBySlug = (slug) => {
                for (const section of state.sections) {
                    const lesson = (section.lessons || []).find((item) => item.slug === slug);
                    if (lesson) {
                        return { section, lesson };
                    }
                }
                return null;
            };

            const refreshBulkSectionSelect = () => {
                const select = document.getElementById('bulk-section');
                if (!select) {
                    return;
                }

                select.innerHTML = state.sections
                    .map((section) => `<option value="${section.id}">${section.title}</option>`)
                    .join('');
            };

            const persistOrder = async () => {
                const payload = {
                    sections: state.sections.map((section, sectionIndex) => ({
                        id: section.id,
                        sort_order: sectionIndex + 1,
                        lessons: (section.lessons || []).map((lesson, lessonIndex) => ({
                            id: lesson.id,
                            sort_order: lessonIndex + 1,
                        })),
                    })),
                };

                await requestJson(routes.reorder, 'POST', payload);
                updateAutosaveStatus('Curriculum order updated');
            };

            const sectionCardTemplate = (section) => {
                const lessons = (section.lessons || []).map((lesson) => `
                    <div class="lesson-item flex items-center justify-between rounded-md border border-gray-200 bg-white px-3 py-2" draggable="true" data-lesson-slug="${lesson.slug}">
                        <div>
                            <p class="text-sm font-medium text-gray-800">${lesson.title}</p>
                            <p class="text-xs text-gray-500">${lesson.type} • ${lesson.duration_minutes || 0} min ${lesson.is_free_preview ? '• free preview' : ''}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="lesson-edit-btn rounded border border-gray-300 px-2 py-1 text-xs text-gray-700" data-lesson-slug="${lesson.slug}">Edit</button>
                        </div>
                    </div>
                `).join('');

                return `
                    <div class="section-card rounded-lg border border-gray-200 p-4 bg-gray-50" draggable="true" data-section-id="${section.id}">
                        <div class="flex items-start gap-3">
                            <input type="text" class="section-title-input flex-1 rounded-md border-gray-300 text-sm" data-section-id="${section.id}" value="${section.title}">
                            <button type="button" class="section-delete-btn rounded border border-red-200 bg-red-50 px-2 py-1 text-xs text-red-700" data-section-id="${section.id}">Delete</button>
                        </div>
                        <textarea class="section-description-input mt-2 w-full rounded-md border-gray-300 text-xs" rows="2" data-section-id="${section.id}" placeholder="Section description">${section.description || ''}</textarea>

                        <div class="mt-3 mb-2 flex items-center justify-between">
                            <p class="text-xs font-semibold text-gray-600">Lessons</p>
                            <button type="button" class="lesson-add-btn rounded border border-gray-300 px-2 py-1 text-xs text-gray-700" data-section-id="${section.id}">+ Lesson</button>
                        </div>

                        <div class="lesson-list space-y-2" data-section-id="${section.id}">
                            ${lessons || '<p class="text-xs text-gray-500">No lessons yet.</p>'}
                        </div>
                    </div>
                `;
            };

            const renderBoard = () => {
                board.innerHTML = state.sections.map(sectionCardTemplate).join('');
                refreshBulkSectionSelect();
                bindDragAndDrop();
            };

            const addSectionBtn = document.getElementById('add-section-btn');
            addSectionBtn?.addEventListener('click', async () => {
                const title = prompt('Section title:');
                if (!title) {
                    return;
                }

                try {
                    const payload = await requestJson(routes.addSection, 'POST', { title });
                    state.sections.push({ ...payload.section, lessons: [] });
                    renderBoard();
                    updateAutosaveStatus('Section added');
                } catch (error) {
                    updateAutosaveStatus('Failed to add section', true);
                }
            });

            board.addEventListener('blur', async (event) => {
                if (!event.target.classList.contains('section-title-input') && !event.target.classList.contains('section-description-input')) {
                    return;
                }

                const sectionId = event.target.dataset.sectionId;
                const section = sectionById(sectionId);
                if (!section) {
                    return;
                }

                const titleInput = board.querySelector(`.section-title-input[data-section-id="${sectionId}"]`);
                const descriptionInput = board.querySelector(`.section-description-input[data-section-id="${sectionId}"]`);

                try {
                    const payload = await requestJson(`${routes.updateSectionBase}/${sectionId}`, 'PUT', {
                        title: titleInput.value,
                        description: descriptionInput.value,
                    });
                    section.title = payload.section.title;
                    section.description = payload.section.description;
                    refreshBulkSectionSelect();
                    updateAutosaveStatus('Section updated');
                } catch (error) {
                    updateAutosaveStatus('Failed to update section', true);
                }
            }, true);

            board.addEventListener('click', async (event) => {
                const deleteButton = event.target.closest('.section-delete-btn');
                if (deleteButton) {
                    const sectionId = deleteButton.dataset.sectionId;
                    if (!confirm('Delete this section and all of its lessons?')) {
                        return;
                    }

                    try {
                        await requestJson(`${routes.updateSectionBase}/${sectionId}`, 'DELETE', {});
                        state.sections = state.sections.filter((section) => Number(section.id) !== Number(sectionId));
                        renderBoard();
                        updateAutosaveStatus('Section deleted');
                    } catch (error) {
                        updateAutosaveStatus('Failed to delete section', true);
                    }
                    return;
                }

                const addLessonButton = event.target.closest('.lesson-add-btn');
                if (addLessonButton) {
                    openLessonModal({ sectionId: Number(addLessonButton.dataset.sectionId), lessonSlug: null });
                    return;
                }

                const editLessonButton = event.target.closest('.lesson-edit-btn');
                if (editLessonButton) {
                    openLessonModal({ sectionId: null, lessonSlug: editLessonButton.dataset.lessonSlug });
                }
            });

            const bulkCreateBtn = document.getElementById('bulk-create-btn');
            bulkCreateBtn?.addEventListener('click', async () => {
                const sectionId = document.getElementById('bulk-section')?.value;
                const titles = document.getElementById('bulk-titles')?.value || '';
                const type = document.getElementById('bulk-type')?.value || 'video';
                const isPreview = document.getElementById('bulk-preview')?.checked;

                if (!sectionId || !titles.trim()) {
                    alert('Select a section and provide at least one lesson title.');
                    return;
                }

                try {
                    const payload = await requestJson(`${routes.bulkLessonBase}/${sectionId}/lessons/bulk`, 'POST', {
                        titles,
                        type,
                        is_free_preview: isPreview ? 1 : 0,
                    });

                    const section = sectionById(sectionId);
                    if (section) {
                        section.lessons = [...(section.lessons || []), ...payload.lessons];
                    }

                    document.getElementById('bulk-titles').value = '';
                    renderBoard();
                    updateAutosaveStatus(`${payload.count} lessons created`);
                } catch (error) {
                    updateAutosaveStatus('Bulk lesson creation failed', true);
                }
            });

            const lessonModal = document.getElementById('lesson-modal');
            const lessonModalClose = document.getElementById('lesson-modal-close');
            const lessonCancelBtn = document.getElementById('lesson-cancel-btn');
            const lessonDeleteBtn = document.getElementById('lesson-delete-btn');
            const lessonForm = document.getElementById('lesson-form');

            const closeLessonModal = () => {
                lessonModal?.classList.add('hidden');
                lessonModal?.classList.remove('flex');
                state.activeLesson = null;
                lessonForm?.reset();
            };

            const openLessonModal = ({ sectionId, lessonSlug }) => {
                const title = document.getElementById('lesson-modal-title');
                const lessonSectionId = document.getElementById('lesson-section-id');
                const lessonSlugField = document.getElementById('lesson-slug');

                if (!lessonForm || !lessonModal || !lessonSectionId || !lessonSlugField || !title) {
                    return;
                }

                lessonForm.reset();

                if (lessonSlug) {
                    const found = lessonBySlug(lessonSlug);
                    if (!found) {
                        return;
                    }

                    state.activeLesson = found.lesson;
                    title.textContent = 'Edit Lesson';
                    lessonSectionId.value = String(found.section.id);
                    lessonSlugField.value = found.lesson.slug;
                    lessonDeleteBtn.classList.remove('hidden');

                    document.getElementById('lesson-title').value = found.lesson.title || '';
                    document.getElementById('lesson-type').value = found.lesson.type || 'video';
                    document.getElementById('lesson-duration').value = found.lesson.duration_minutes || 0;
                    document.getElementById('lesson-video-provider').value = found.lesson.video_provider || '';
                    document.getElementById('lesson-video-url').value = found.lesson.video_url || '';
                    document.getElementById('lesson-content').value = found.lesson.content || '';
                    document.getElementById('lesson-preview').checked = !!found.lesson.is_free_preview;
                } else {
                    state.activeLesson = null;
                    title.textContent = 'Add Lesson';
                    lessonSectionId.value = String(sectionId);
                    lessonSlugField.value = '';
                    lessonDeleteBtn.classList.add('hidden');
                }

                lessonModal.classList.remove('hidden');
                lessonModal.classList.add('flex');
            };

            lessonModalClose?.addEventListener('click', closeLessonModal);
            lessonCancelBtn?.addEventListener('click', closeLessonModal);
            lessonModal?.addEventListener('click', (event) => {
                if (event.target === lessonModal) {
                    closeLessonModal();
                }
            });

            lessonDeleteBtn?.addEventListener('click', async () => {
                const slug = document.getElementById('lesson-slug').value;
                if (!slug || !confirm('Delete this lesson?')) {
                    return;
                }

                try {
                    await requestJson(`${routes.lessonBase}/${slug}`, 'DELETE', {});
                    for (const section of state.sections) {
                        section.lessons = (section.lessons || []).filter((lesson) => lesson.slug !== slug);
                    }
                    renderBoard();
                    closeLessonModal();
                    updateAutosaveStatus('Lesson deleted');
                } catch (error) {
                    updateAutosaveStatus('Failed to delete lesson', true);
                }
            });

            lessonForm?.addEventListener('submit', async (event) => {
                event.preventDefault();

                const sectionId = document.getElementById('lesson-section-id').value;
                const slug = document.getElementById('lesson-slug').value;
                const formData = new FormData();
                formData.append('title', document.getElementById('lesson-title').value);
                formData.append('type', document.getElementById('lesson-type').value);
                formData.append('duration_minutes', document.getElementById('lesson-duration').value || 0);
                formData.append('video_provider', document.getElementById('lesson-video-provider').value);
                formData.append('video_url', document.getElementById('lesson-video-url').value);
                formData.append('content', document.getElementById('lesson-content').value);
                formData.append('is_free_preview', document.getElementById('lesson-preview').checked ? '1' : '0');

                const fileInput = document.getElementById('lesson-video-file');
                if (fileInput?.files?.[0]) {
                    formData.append('video_file', fileInput.files[0]);
                }

                try {
                    if (slug) {
                        formData.append('_method', 'PUT');
                        const payload = await requestForm(`${routes.lessonBase}/${slug}`, 'POST', formData);
                        for (const section of state.sections) {
                            section.lessons = (section.lessons || []).map((lesson) => lesson.slug === slug ? payload.lesson : lesson);
                        }
                        updateAutosaveStatus('Lesson updated');
                    } else {
                        const payload = await requestForm(`${routes.addLessonBase}/${sectionId}/lessons`, 'POST', formData);
                        const section = sectionById(sectionId);
                        if (section) {
                            section.lessons = [...(section.lessons || []), payload.lesson];
                        }
                        updateAutosaveStatus('Lesson added');
                    }

                    renderBoard();
                    closeLessonModal();
                } catch (error) {
                    updateAutosaveStatus('Failed to save lesson', true);
                }
            });

            const bindDragAndDrop = () => {
                let dragSectionId = null;
                let dragLessonSlug = null;
                let dragLessonOriginSectionId = null;

                board.querySelectorAll('.section-card').forEach((card) => {
                    card.addEventListener('dragstart', () => {
                        dragSectionId = Number(card.dataset.sectionId);
                        dragLessonSlug = null;
                        dragLessonOriginSectionId = null;
                    });

                    card.addEventListener('dragover', (event) => {
                        event.preventDefault();
                    });

                    card.addEventListener('drop', async (event) => {
                        event.preventDefault();
                        const targetSectionId = Number(card.dataset.sectionId);

                        if (dragSectionId && dragSectionId !== targetSectionId) {
                            const dragIndex = state.sections.findIndex((section) => Number(section.id) === dragSectionId);
                            const targetIndex = state.sections.findIndex((section) => Number(section.id) === targetSectionId);
                            const [moved] = state.sections.splice(dragIndex, 1);
                            state.sections.splice(targetIndex, 0, moved);
                            renderBoard();
                            await persistOrder();
                        }
                    });
                });

                board.querySelectorAll('.lesson-item').forEach((item) => {
                    item.addEventListener('dragstart', () => {
                        dragLessonSlug = item.dataset.lessonSlug;
                        dragSectionId = null;
                        dragLessonOriginSectionId = Number(item.closest('.lesson-list')?.dataset.sectionId);
                    });
                });

                board.querySelectorAll('.lesson-list').forEach((list) => {
                    list.addEventListener('dragover', (event) => {
                        event.preventDefault();
                    });

                    list.addEventListener('drop', async (event) => {
                        event.preventDefault();
                        if (!dragLessonSlug) {
                            return;
                        }

                        const destinationSectionId = Number(list.dataset.sectionId);
                        if (!dragLessonOriginSectionId || !destinationSectionId) {
                            return;
                        }

                        const originSection = sectionById(dragLessonOriginSectionId);
                        const destinationSection = sectionById(destinationSectionId);
                        if (!originSection || !destinationSection) {
                            return;
                        }

                        const lessonIndex = (originSection.lessons || []).findIndex((lesson) => lesson.slug === dragLessonSlug);
                        if (lessonIndex === -1) {
                            return;
                        }

                        const [lesson] = originSection.lessons.splice(lessonIndex, 1);
                        destinationSection.lessons = destinationSection.lessons || [];
                        destinationSection.lessons.push(lesson);

                        renderBoard();
                        await persistOrder();
                    });
                });
            };

            renderBoard();
        })();
    </script>
</x-app-layout>
