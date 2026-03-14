<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-900 leading-tight">Course Preview</h2>
                <p class="text-sm text-gray-500 mt-1">Internal instructor preview before publishing.</p>
            </div>
            <a href="{{ route('instructor.courses.wizard', [$course, 'step' => 4]) }}" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                Back To Wizard
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="grid lg:grid-cols-5 gap-0">
                    <div class="lg:col-span-3 p-6 md:p-8">
                        <p class="text-xs uppercase tracking-wider text-gray-500">{{ $course->category?->name ?? 'Uncategorized' }}</p>
                        <h1 class="mt-2 text-3xl font-bold text-gray-900">{{ $course->title }}</h1>
                        @if ($course->subtitle)
                            <p class="mt-2 text-lg text-gray-600">{{ $course->subtitle }}</p>
                        @endif

                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach ($course->tags as $tag)
                                <span class="text-xs rounded-full bg-gray-100 px-3 py-1 text-gray-700">#{{ $tag->name }}</span>
                            @endforeach
                        </div>

                        <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                            <div class="rounded-lg border border-gray-200 px-3 py-2">
                                <p class="text-gray-500">Lessons</p>
                                <p class="font-semibold text-gray-900">{{ $course->lessons_count }}</p>
                            </div>
                            <div class="rounded-lg border border-gray-200 px-3 py-2">
                                <p class="text-gray-500">Sections</p>
                                <p class="font-semibold text-gray-900">{{ $course->sections_count }}</p>
                            </div>
                            <div class="rounded-lg border border-gray-200 px-3 py-2">
                                <p class="text-gray-500">Level</p>
                                <p class="font-semibold text-gray-900">{{ ucfirst(str_replace('_', ' ', $course->level)) }}</p>
                            </div>
                            <div class="rounded-lg border border-gray-200 px-3 py-2">
                                <p class="text-gray-500">Language</p>
                                <p class="font-semibold text-gray-900">{{ strtoupper($course->language) }}</p>
                            </div>
                        </div>

                        @if ($course->description)
                            <div class="prose max-w-none mt-6 text-gray-700">
                                {!! $course->description !!}
                            </div>
                        @endif
                    </div>

                    <div class="lg:col-span-2 bg-gray-50 border-l border-gray-200 p-6">
                        <div class="aspect-video bg-gray-200 rounded-lg overflow-hidden">
                            @if ($course->thumbnail)
                                <img src="{{ asset('storage/' . $course->thumbnail) }}" alt="{{ $course->title }}" class="w-full h-full object-cover">
                            @else
                                <div class="h-full flex items-center justify-center text-gray-500 text-sm">No thumbnail uploaded</div>
                            @endif
                        </div>

                        <div class="mt-4 space-y-2 text-sm text-gray-700">
                            <p><span class="font-semibold">Price:</span> {{ $course->is_free ? 'Free' : '$' . number_format((float) ($course->discount_price ?? $course->price), 2) }}</p>
                            @if (!$course->is_free && $course->discount_price)
                                <p class="text-xs text-gray-500">Regular: ${{ number_format((float) $course->price, 2) }}</p>
                            @endif
                            <p><span class="font-semibold">Certificate:</span> {{ ucfirst($course->certificate_template ?? 'classic') }}</p>
                            <p><span class="font-semibold">Status:</span> {{ ucfirst($course->status) }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid md:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900">Requirements</h3>
                    @php $requirements = $course->requirements ?? []; @endphp
                    <ul class="mt-3 list-disc pl-5 space-y-1 text-sm text-gray-700">
                        @forelse ($requirements as $item)
                            <li>{{ $item }}</li>
                        @empty
                            <li class="list-none text-gray-400">No requirements added yet.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900">What Students Will Learn</h3>
                    @php $objectives = $course->what_you_will_learn ?? []; @endphp
                    <ul class="mt-3 list-disc pl-5 space-y-1 text-sm text-gray-700">
                        @forelse ($objectives as $item)
                            <li>{{ $item }}</li>
                        @empty
                            <li class="list-none text-gray-400">No learning objectives added yet.</li>
                        @endforelse
                    </ul>
                </div>
            </section>

            <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900">Curriculum</h3>
                <div class="mt-4 space-y-4">
                    @forelse ($course->sections as $section)
                        <div class="rounded-lg border border-gray-200 p-4">
                            <h4 class="font-semibold text-gray-900">{{ $section->title }}</h4>
                            @if ($section->description)
                                <p class="text-sm text-gray-500 mt-1">{{ $section->description }}</p>
                            @endif

                            <ul class="mt-3 space-y-2">
                                @foreach ($section->lessons as $lesson)
                                    <li class="flex items-center justify-between gap-3 rounded-md bg-gray-50 px-3 py-2 text-sm">
                                        <div>
                                            <span class="font-medium text-gray-800">{{ $lesson->title }}</span>
                                            <span class="ml-2 text-gray-500">({{ ucfirst($lesson->type) }})</span>
                                            @if ($lesson->is_free_preview)
                                                <span class="ml-2 text-xs rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-700">Preview</span>
                                            @endif
                                        </div>
                                        <span class="text-gray-500">{{ $lesson->duration_minutes }} min</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No curriculum created yet.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
