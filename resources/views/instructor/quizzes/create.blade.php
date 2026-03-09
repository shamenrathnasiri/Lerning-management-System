<x-app-layout>
    @php
        $lessonsByCourse = $courses->mapWithKeys(function ($course) {
            $lessons = $course->sections
                ->flatMap(function ($section) {
                    return $section->lessons->map(function ($lesson) use ($section) {
                        return [
                            'id' => $lesson->id,
                            'title' => $lesson->title,
                            'section' => $section->title,
                        ];
                    });
                })
                ->values()
                ->all();

            return [$course->id => $lessons];
        });
    @endphp

    <x-slot name="header">
        <nav class="flex items-center gap-2 text-sm text-gray-500">
            <a href="{{ route('instructor.courses.index') }}" class="hover:text-gray-700">My Courses</a>
            <span>/</span>
            <span class="text-gray-700">Create Quiz</span>
        </nav>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8" x-data="createQuizApp({
            lessonsByCourse: @js($lessonsByCourse),
            defaultCourseId: @js(old('course_id', $defaultCourseId)),
            defaultLessonId: @js(old('lesson_id', $defaultLessonId)),
            defaultQuizType: @js(old('quiz_type', $quizTypes[0] ?? 'lesson_quiz')),
            defaultTimeLimitMode: @js(old('time_limit_mode', $timeLimitModes[0] ?? 'per_quiz')),
            defaultAnswerVisibility: @js(old('answer_visibility', $answerVisibilityModes[1] ?? 'after_attempts'))
        })" x-init="init()">

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <p class="font-semibold">Please fix the following issues:</p>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('instructor.quizzes.store') }}" class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                @csrf

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Course</label>
                        <select
                            name="course_id"
                            x-model="courseId"
                            @change="syncLessons()"
                            required
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Select a course</option>
                            @foreach ($courses as $course)
                                <option value="{{ $course->id }}">{{ $course->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Quiz Type</label>
                        <select
                            name="quiz_type"
                            x-model="quizType"
                            @change="syncLessons()"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            @foreach ($quizTypes as $type)
                                <option value="{{ $type }}">{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div x-show="quizType === 'lesson_quiz'" x-cloak>
                    <label class="block text-sm font-medium text-gray-700">Lesson</label>
                    <select
                        name="lesson_id"
                        x-model="lessonId"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Select lesson</option>
                        <template x-for="lesson in availableLessons" :key="lesson.id">
                            <option :value="lesson.id" x-text="`${lesson.section} - ${lesson.title}`"></option>
                        </template>
                    </select>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Title</label>
                        <input
                            type="text"
                            name="title"
                            value="{{ old('title') }}"
                            required
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea
                            name="description"
                            rows="3"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >{{ old('description') }}</textarea>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Instructions</label>
                        <textarea
                            name="instructions"
                            rows="4"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Add quiz instructions, grading notes, or formatting guidance for students."
                        >{{ old('instructions') }}</textarea>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 p-4">
                    <p class="text-sm font-semibold text-gray-800">Timing and Attempts</p>
                    <div class="mt-3 grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Time Mode</label>
                            <select
                                name="time_limit_mode"
                                x-model="timeLimitMode"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                @foreach ($timeLimitModes as $mode)
                                    <option value="{{ $mode }}">{{ ucfirst(str_replace('_', ' ', $mode)) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div x-show="timeLimitMode === 'per_quiz'" x-cloak>
                            <label class="block text-sm font-medium text-gray-700">Time Limit (minutes)</label>
                            <input
                                type="number"
                                name="time_limit_minutes"
                                min="1"
                                max="1440"
                                value="{{ old('time_limit_minutes', 30) }}"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                        </div>

                        <div x-show="timeLimitMode === 'per_question'" x-cloak>
                            <label class="block text-sm font-medium text-gray-700">Per Question (seconds)</label>
                            <input
                                type="number"
                                name="per_question_time_seconds"
                                min="5"
                                max="3600"
                                value="{{ old('per_question_time_seconds', 60) }}"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Pass Percentage</label>
                            <input
                                type="number"
                                name="pass_percentage"
                                step="0.01"
                                min="0"
                                max="100"
                                value="{{ old('pass_percentage', 70) }}"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Attempts Allowed</label>
                            <input
                                type="number"
                                name="attempts_allowed"
                                min="1"
                                max="100"
                                value="{{ old('attempts_allowed', 1) }}"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 p-4">
                    <p class="text-sm font-semibold text-gray-800">Behavior</p>
                    <div class="mt-3 grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="randomize_questions" value="1" {{ old('randomize_questions') ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                Randomize question order
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="randomize_options" value="1" {{ old('randomize_options') ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                Randomize options inside question
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Navigation</label>
                            <select name="navigation_mode" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($navigationModes as $mode)
                                    <option value="{{ $mode }}" {{ old('navigation_mode', 'free') === $mode ? 'selected' : '' }}>{{ ucfirst($mode) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mt-3 grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Answer Visibility</label>
                            <select
                                name="answer_visibility"
                                x-model="answerVisibility"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                @foreach ($answerVisibilityModes as $mode)
                                    <option value="{{ $mode }}">{{ ucfirst(str_replace('_', ' ', $mode)) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div x-show="answerVisibility === 'after_attempts'" x-cloak>
                            <label class="block text-sm font-medium text-gray-700">Show Answers After Attempts</label>
                            <input
                                type="number"
                                name="show_answers_after_attempts"
                                min="1"
                                max="100"
                                value="{{ old('show_answers_after_attempts', 1) }}"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                    <a href="{{ route('instructor.courses.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Back</a>
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Create Quiz
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function createQuizApp(config) {
            return {
                lessonsByCourse: config.lessonsByCourse || {},
                courseId: String(config.defaultCourseId || ''),
                lessonId: String(config.defaultLessonId || ''),
                quizType: config.defaultQuizType || 'lesson_quiz',
                timeLimitMode: config.defaultTimeLimitMode || 'per_quiz',
                answerVisibility: config.defaultAnswerVisibility || 'after_attempts',
                availableLessons: [],

                init() {
                    this.syncLessons();
                },

                syncLessons() {
                    const lessons = this.lessonsByCourse[this.courseId] || [];
                    this.availableLessons = lessons;

                    if (this.quizType !== 'lesson_quiz') {
                        this.lessonId = '';
                        return;
                    }

                    const exists = this.availableLessons.some((lesson) => String(lesson.id) === String(this.lessonId));
                    if (!exists) {
                        this.lessonId = '';
                    }
                },
            };
        }
    </script>
</x-app-layout>
