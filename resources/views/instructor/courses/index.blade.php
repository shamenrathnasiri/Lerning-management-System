<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-900 leading-tight">Instructor Courses</h2>
                <p class="text-sm text-gray-500 mt-1">Create and manage your course drafts, curriculum, and publishing workflow.</p>
            </div>
            <form method="POST" action="{{ route('instructor.courses.create') }}">
                @csrf
                <x-primary-button>Create New Course</x-primary-button>
            </form>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-5">
            @if (session('success'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            @if ($courses->count() === 0)
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center">
                    <h3 class="text-lg font-semibold text-gray-900">No courses yet</h3>
                    <p class="mt-2 text-sm text-gray-500">Start by creating your first draft course from the button above.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    @foreach ($courses as $course)
                        <article class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden flex flex-col">
                            <div class="aspect-video bg-gray-100">
                                @if ($course->thumbnail)
                                    <img src="{{ asset('storage/' . $course->thumbnail) }}" alt="{{ $course->title }}" class="w-full h-full object-cover">
                                @else
                                    <div class="h-full flex items-center justify-center text-gray-400 text-sm">No thumbnail</div>
                                @endif
                            </div>

                            <div class="p-5 flex-1 flex flex-col">
                                <div class="flex items-start justify-between gap-3">
                                    <h3 class="text-base font-semibold text-gray-900 line-clamp-2">{{ $course->title }}</h3>
                                    <span class="text-xs px-2 py-1 rounded-full
                                        @class([
                                            'bg-amber-100 text-amber-700' => $course->status === 'draft',
                                            'bg-blue-100 text-blue-700' => $course->status === 'pending',
                                            'bg-emerald-100 text-emerald-700' => $course->status === 'published',
                                            'bg-gray-100 text-gray-600' => $course->status === 'archived',
                                        ])">
                                        {{ ucfirst($course->status) }}
                                    </span>
                                </div>

                                <p class="text-xs text-gray-500 mt-2">Updated {{ $course->updated_at?->diffForHumans() }}</p>

                                <div class="mt-4 grid grid-cols-2 gap-2 text-xs text-gray-600">
                                    <div class="rounded border border-gray-200 px-2 py-1">Sections: {{ $course->sections_count }}</div>
                                    <div class="rounded border border-gray-200 px-2 py-1">Lessons: {{ $course->lessons_count }}</div>
                                    <div class="rounded border border-gray-200 px-2 py-1">Enrollments: {{ $course->enrollments_count }}</div>
                                    <div class="rounded border border-gray-200 px-2 py-1">Versions: {{ $course->versions_count }}</div>
                                </div>

                                <div class="mt-4">
                                    <div class="flex justify-between text-xs text-gray-600 mb-1">
                                        <span>Wizard step</span>
                                        <span>{{ $course->wizard_step }}/4</span>
                                    </div>
                                    <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                                        <div class="h-full bg-indigo-500" style="width: {{ min(100, (($course->wizard_step ?? 1) / 4) * 100) }}%"></div>
                                    </div>
                                </div>

                                <div class="mt-5 space-y-2">
                                    <a href="{{ route('instructor.courses.wizard', [$course, 'step' => max(1, $course->wizard_step)]) }}"
                                       class="inline-flex justify-center w-full rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                        Continue Wizard
                                    </a>

                                    <div class="grid grid-cols-2 gap-2">
                                        <a href="{{ route('instructor.courses.preview', $course) }}" target="_blank"
                                           class="inline-flex justify-center rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                            Preview
                                        </a>

                                        <form method="POST" action="{{ route('instructor.courses.duplicate', $course) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex justify-center w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                Duplicate
                                            </button>
                                        </form>
                                    </div>

                                    <form method="POST" action="{{ route('instructor.courses.destroy', $course) }}" onsubmit="return confirm('Delete this course draft?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex justify-center w-full rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 hover:bg-red-100">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div>
                    {{ $courses->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
