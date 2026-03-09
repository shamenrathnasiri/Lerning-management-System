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

        $initialQuestions = $questionPayload;
        $oldQuestionJson = old('questions_json');
        if (is_string($oldQuestionJson) && trim($oldQuestionJson) !== '') {
            $decoded = json_decode($oldQuestionJson, true);
            if (is_array($decoded)) {
                $initialQuestions = $decoded;
            }
        }

        $defaultCourseId = old('course_id', $quiz->course_id);
        $defaultLessonId = old('lesson_id', $quiz->lesson_id);
        $defaultQuizType = old('quiz_type', $quiz->quiz_type ?? 'lesson_quiz');
        $defaultTimeLimitMode = old('time_limit_mode', $quiz->time_limit_mode ?? 'per_quiz');
        $defaultAnswerVisibility = old('answer_visibility', $quiz->answer_visibility ?? 'after_attempts');
    @endphp

    <x-slot name="header">
        <nav class="flex items-center gap-2 text-sm text-gray-500">
            <a href="{{ route('instructor.courses.index') }}" class="hover:text-gray-700">My Courses</a>
            <span>/</span>
            <a href="{{ route('instructor.courses.wizard', [$quiz->course, 'step' => 3]) }}" class="hover:text-gray-700">{{ $quiz->course->title }}</a>
            <span>/</span>
            <span class="text-gray-700">{{ $quiz->title }}</span>
        </nav>
    </x-slot>

    <div class="py-8">
        <div
            class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8"
            x-data="quizBuilderApp({
                questionTypes: @js($questionTypes),
                lessonsByCourse: @js($lessonsByCourse),
                initialQuestions: @js($initialQuestions),
                defaultCourseId: @js($defaultCourseId),
                defaultLessonId: @js($defaultLessonId),
                defaultQuizType: @js($defaultQuizType),
                defaultTimeLimitMode: @js($defaultTimeLimitMode),
                defaultAnswerVisibility: @js($defaultAnswerVisibility)
            })"
            x-init="init()"
        >
            @if (session('success'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

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

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div>
                    <p class="text-sm text-gray-500">Quiz Status</p>
                    <p class="text-base font-semibold text-gray-900">
                        {{ $quiz->is_published ? 'Published' : 'Draft' }}
                        @if ($quiz->published_at)
                            <span class="ml-2 text-xs font-normal text-gray-500">{{ $quiz->published_at->format('M d, Y H:i') }}</span>
                        @endif
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('instructor.courses.wizard', [$quiz->course, 'step' => 3]) }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        Back To Curriculum
                    </a>

                    <form method="POST" action="{{ route('instructor.quizzes.duplicate', $quiz) }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            Duplicate
                        </button>
                    </form>

                    @if ($quiz->is_published)
                        <form method="POST" action="{{ route('instructor.quizzes.unpublish', $quiz) }}">
                            @csrf
                            <button type="submit" class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-700 hover:bg-amber-100">
                                Unpublish
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('instructor.quizzes.publish', $quiz) }}">
                            @csrf
                            <button type="submit" class="rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 hover:bg-emerald-100">
                                Publish
                            </button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('instructor.quizzes.destroy', $quiz) }}" onsubmit="return confirm('Delete this quiz?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700 hover:bg-red-100">
                            Delete
                        </button>
                    </form>
                </div>
            </div>

            <form method="POST" action="{{ route('instructor.quizzes.update', $quiz) }}" @submit="prepareSubmit($event)" class="space-y-6">
                @csrf
                @method('PUT')
                <input type="hidden" name="questions_json" x-ref="questionsJson">

                <div class="grid gap-6 lg:grid-cols-3">
                    <div class="space-y-6 lg:col-span-1">
                        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                            <h3 class="text-sm font-semibold text-gray-900">Quiz Settings</h3>

                            <div class="mt-4 space-y-4">
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

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Title</label>
                                    <input
                                        type="text"
                                        name="title"
                                        value="{{ old('title', $quiz->title) }}"
                                        required
                                        class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Description</label>
                                    <textarea
                                        name="description"
                                        rows="3"
                                        class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >{{ old('description', $quiz->description) }}</textarea>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Instructions</label>
                                    <textarea
                                        name="instructions"
                                        rows="4"
                                        class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >{{ old('instructions', $quiz->instructions) }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                            <h3 class="text-sm font-semibold text-gray-900">Timing and Visibility</h3>

                            <div class="mt-4 space-y-4">
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
                                        value="{{ old('time_limit_minutes', $quiz->time_limit_minutes) }}"
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
                                        value="{{ old('per_question_time_seconds', $quiz->per_question_time_seconds) }}"
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
                                        value="{{ old('pass_percentage', $quiz->pass_percentage) }}"
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
                                        value="{{ old('attempts_allowed', $quiz->max_attempts) }}"
                                        class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Navigation</label>
                                    <select name="navigation_mode" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @foreach ($navigationModes as $mode)
                                            <option value="{{ $mode }}" {{ old('navigation_mode', $quiz->navigation_mode) === $mode ? 'selected' : '' }}>{{ ucfirst($mode) }}</option>
                                        @endforeach
                                    </select>
                                </div>

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
                                        value="{{ old('show_answers_after_attempts', $quiz->show_answers_after_attempts) }}"
                                        class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                </div>

                                <div class="space-y-2 border-t border-gray-100 pt-3">
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="randomize_questions" value="1" {{ old('randomize_questions', $quiz->shuffle_questions) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                        Randomize question order
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="randomize_options" value="1" {{ old('randomize_options', $quiz->randomize_options) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                        Randomize options
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 lg:col-span-2">
                        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="text-sm font-semibold text-gray-900">Question Builder</h3>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="type in questionTypes" :key="type">
                                        <button
                                            type="button"
                                            @click="addQuestion(type)"
                                            class="rounded-md border border-gray-300 px-2.5 py-1.5 text-xs text-gray-700 hover:bg-gray-50"
                                            x-text="`+ ${labelize(type)}`"
                                        ></button>
                                    </template>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-gray-500">
                                Rich content supports HTML, code blocks, media URLs, and LaTeX snippets (for example: \(x^2 + y^2 = z^2\) or $$\\int_0^1 x^2 dx$$).
                            </p>
                        </div>

                        <template x-if="questions.length === 0">
                            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">
                                No questions yet. Add your first question type above.
                            </div>
                        </template>

                        <template x-for="(question, index) in questions" :key="question.local_id">
                            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-gray-900" x-text="`Question ${index + 1}`"></p>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" @click="moveQuestion(index, -1)" class="rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">Up</button>
                                        <button type="button" @click="moveQuestion(index, 1)" class="rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">Down</button>
                                        <button type="button" @click="duplicateQuestion(index)" class="rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">Duplicate</button>
                                        <button type="button" @click="removeQuestion(index)" class="rounded-md border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50">Delete</button>
                                    </div>
                                </div>

                                <div class="mt-3 grid gap-4 md:grid-cols-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600">Type</label>
                                        <select x-model="question.type" @change="normalizeByType(question)" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <template x-for="type in questionTypes" :key="type">
                                                <option :value="type" x-text="labelize(type)"></option>
                                            </template>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-medium text-gray-600">Points</label>
                                        <input type="number" min="1" x-model.number="question.points" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>

                                    <div class="flex items-end">
                                        <label class="flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" x-model="question.allow_partial_credit" class="rounded border-gray-300 text-indigo-600">
                                            Partial credit
                                        </label>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <label class="block text-xs font-medium text-gray-600">Question Prompt</label>
                                    <input type="text" x-model="question.question_text" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Quick title for this question">
                                </div>

                                <div class="mt-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <label class="block text-xs font-medium text-gray-600">Rich Content (HTML/Markdown-like)</label>
                                        <div class="flex flex-wrap gap-1">
                                            <button type="button" @click="appendSnippet(question, 'question_content', '<strong></strong>')" class="rounded border border-gray-300 px-2 py-0.5 text-xs text-gray-700">Bold</button>
                                            <button type="button" @click="appendSnippet(question, 'question_content', '<pre><code class=\"language-javascript\"></code></pre>')" class="rounded border border-gray-300 px-2 py-0.5 text-xs text-gray-700">Code</button>
                                            <button type="button" @click="appendSnippet(question, 'question_content', '<img src=\"https://\" alt=\"image\" />')" class="rounded border border-gray-300 px-2 py-0.5 text-xs text-gray-700">Image</button>
                                            <button type="button" @click="appendSnippet(question, 'question_content', '<video controls src=\"https://\"></video>')" class="rounded border border-gray-300 px-2 py-0.5 text-xs text-gray-700">Video</button>
                                            <button type="button" @click="appendSnippet(question, 'question_content', '<audio controls src=\"https://\"></audio>')" class="rounded border border-gray-300 px-2 py-0.5 text-xs text-gray-700">Audio</button>
                                            <button type="button" @click="appendSnippet(question, 'question_content', '\\(x^2+y^2=z^2\\)')" class="rounded border border-gray-300 px-2 py-0.5 text-xs text-gray-700">LaTeX</button>
                                        </div>
                                    </div>
                                    <textarea x-model="question.question_content" rows="5" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                </div>

                                <div class="mt-4 grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600">Image URL</label>
                                        <input type="url" x-model="question.media_embed.image_url" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="https://...">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600">Video URL</label>
                                        <input type="url" x-model="question.media_embed.video_url" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="https://...">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600">Audio URL</label>
                                        <input type="url" x-model="question.media_embed.audio_url" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="https://...">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600">LaTeX Formula</label>
                                        <input type="text" x-model="question.media_embed.latex" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="$$\\frac{a}{b}$$">
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <label class="block text-xs font-medium text-gray-600">Explanation</label>
                                    <textarea x-model="question.explanation" rows="3" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                </div>

                                <template x-if="isChoiceType(question.type)">
                                    <div class="mt-4 rounded-lg border border-gray-200 p-3">
                                        <div class="mb-2 flex items-center justify-between">
                                            <p class="text-xs font-semibold text-gray-700">Options</p>
                                            <button type="button" @click="addOption(question)" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">Add Option</button>
                                        </div>
                                        <div class="space-y-2">
                                            <template x-for="(option, optionIndex) in question.options" :key="option.local_id">
                                                <div class="grid gap-2 rounded-md border border-gray-200 p-2 md:grid-cols-12">
                                                    <div class="md:col-span-1">
                                                        <template x-if="question.type === 'multiple_select'">
                                                            <input type="checkbox" x-model="option.is_correct" class="mt-2 rounded border-gray-300 text-indigo-600">
                                                        </template>
                                                        <template x-if="question.type !== 'multiple_select'">
                                                            <input type="radio" :name="`correct-${question.local_id}`" :checked="option.is_correct" @change="setSingleCorrect(question, optionIndex)" class="mt-2 border-gray-300 text-indigo-600">
                                                        </template>
                                                    </div>
                                                    <div class="md:col-span-4">
                                                        <input type="text" x-model="option.option_text" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Option text">
                                                    </div>
                                                    <div class="md:col-span-5">
                                                        <input type="text" x-model="option.content" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Rich option content">
                                                    </div>
                                                    <div class="md:col-span-1">
                                                        <input type="text" x-model="option.match_key" x-show="question.type === 'matching_pairs'" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Key">
                                                    </div>
                                                    <div class="md:col-span-1">
                                                        <button type="button" @click="removeOption(question, optionIndex)" class="w-full rounded border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50">X</button>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="question.type === 'fill_blank'">
                                    <div class="mt-4 rounded-lg border border-gray-200 p-3">
                                        <div class="mb-2 flex items-center justify-between">
                                            <p class="text-xs font-semibold text-gray-700">Blank Answers</p>
                                            <button type="button" @click="addBlank(question)" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">Add Blank</button>
                                        </div>
                                        <div class="space-y-2">
                                            <template x-for="(blank, blankIndex) in question.answer_payload.blanks" :key="blank.local_id">
                                                <div class="grid gap-2 md:grid-cols-12">
                                                    <input type="text" x-model="blank.answer" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 md:col-span-10" placeholder="Accepted answer">
                                                    <button type="button" @click="removeBlank(question, blankIndex)" class="rounded border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50 md:col-span-2">Remove</button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="question.type === 'matching_pairs'">
                                    <div class="mt-4 rounded-lg border border-gray-200 p-3">
                                        <div class="mb-2 flex items-center justify-between">
                                            <p class="text-xs font-semibold text-gray-700">Matching Pairs</p>
                                            <button type="button" @click="addPair(question)" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">Add Pair</button>
                                        </div>
                                        <div class="space-y-2">
                                            <template x-for="(pair, pairIndex) in question.answer_payload.pairs" :key="pair.local_id">
                                                <div class="grid gap-2 md:grid-cols-12">
                                                    <input type="text" x-model="pair.left" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 md:col-span-5" placeholder="Left term">
                                                    <input type="text" x-model="pair.right" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 md:col-span-5" placeholder="Right term">
                                                    <button type="button" @click="removePair(question, pairIndex)" class="rounded border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50 md:col-span-2">Remove</button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="question.type === 'ordering'">
                                    <div class="mt-4 rounded-lg border border-gray-200 p-3">
                                        <div class="mb-2 flex items-center justify-between">
                                            <p class="text-xs font-semibold text-gray-700">Correct Order</p>
                                            <button type="button" @click="addOrderingItem(question)" class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">Add Item</button>
                                        </div>
                                        <div class="space-y-2">
                                            <template x-for="(item, itemIndex) in question.answer_payload.items" :key="item.local_id">
                                                <div class="grid gap-2 md:grid-cols-12">
                                                    <input type="text" x-model="item.value" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 md:col-span-10" placeholder="Step or sequence item">
                                                    <button type="button" @click="removeOrderingItem(question, itemIndex)" class="rounded border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50 md:col-span-2">Remove</button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="question.type === 'code_challenge'">
                                    <div class="mt-4 rounded-lg border border-gray-200 p-3">
                                        <p class="text-xs font-semibold text-gray-700">Code Challenge Settings</p>
                                        <div class="mt-2 grid gap-3 md:grid-cols-2">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600">Language</label>
                                                <input type="text" x-model="question.code_language" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="python, javascript, php">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600">Execution Timeout (sec)</label>
                                                <input type="number" min="1" x-model.number="question.execution_timeout_seconds" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="2">
                                            </div>
                                        </div>

                                        <div class="mt-3">
                                            <label class="block text-xs font-medium text-gray-600">Starter Code</label>
                                            <textarea x-model="question.code_starter" rows="4" class="mt-1 w-full rounded-md border-gray-300 text-sm font-mono shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                        </div>

                                        <div class="mt-3">
                                            <label class="block text-xs font-medium text-gray-600">Reference Solution</label>
                                            <textarea x-model="question.code_solution" rows="4" class="mt-1 w-full rounded-md border-gray-300 text-sm font-mono shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                        </div>

                                        <div class="mt-3">
                                            <label class="block text-xs font-medium text-gray-600">Test Cases (JSON array)</label>
                                            <textarea x-model="question.code_test_cases_text" rows="6" class="mt-1 w-full rounded-md border-gray-300 text-xs font-mono shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder='[{"input":"2","expected":"4"}]'></textarea>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                            <p class="text-sm text-gray-600" x-text="`${questions.length} question(s) configured`"></p>
                            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                Save Quiz Builder
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function quizBuilderApp(config) {
            return {
                questionTypes: Array.isArray(config.questionTypes) ? config.questionTypes : [],
                lessonsByCourse: config.lessonsByCourse || {},
                questions: [],
                availableLessons: [],
                courseId: String(config.defaultCourseId || ''),
                lessonId: String(config.defaultLessonId || ''),
                quizType: config.defaultQuizType || 'lesson_quiz',
                timeLimitMode: config.defaultTimeLimitMode || 'per_quiz',
                answerVisibility: config.defaultAnswerVisibility || 'after_attempts',

                init() {
                    const initial = Array.isArray(config.initialQuestions) ? config.initialQuestions : [];
                    this.questions = initial.map((question) => this.normalizeQuestion(question));
                    this.syncLessons();
                },

                syncLessons() {
                    this.availableLessons = this.lessonsByCourse[this.courseId] || [];

                    if (this.quizType !== 'lesson_quiz') {
                        this.lessonId = '';
                        return;
                    }

                    const exists = this.availableLessons.some((lesson) => String(lesson.id) === String(this.lessonId));
                    if (!exists) {
                        this.lessonId = '';
                    }
                },

                labelize(value) {
                    return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
                },

                uid() {
                    return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
                },

                defaultOption(text = '') {
                    return {
                        local_id: this.uid(),
                        id: null,
                        option_text: text,
                        content: '',
                        is_correct: false,
                        match_key: '',
                        option_payload: null,
                    };
                },

                defaultQuestion(type = 'multiple_choice') {
                    return {
                        local_id: this.uid(),
                        id: null,
                        type,
                        question_text: '',
                        question_content: '',
                        explanation: '',
                        points: 1,
                        options: [this.defaultOption('Option 1'), this.defaultOption('Option 2')],
                        answer_payload: {
                            blanks: [],
                            pairs: [],
                            items: [],
                        },
                        media_embed: {
                            image_url: '',
                            video_url: '',
                            audio_url: '',
                            latex: '',
                        },
                        allow_partial_credit: false,
                        code_language: '',
                        code_starter: '',
                        code_solution: '',
                        code_test_cases_text: '[]',
                        execution_timeout_seconds: null,
                        metadata: null,
                    };
                },

                normalizeQuestion(question) {
                    const normalized = this.defaultQuestion(question.type || 'multiple_choice');

                    normalized.id = question.id || null;
                    normalized.type = question.type || 'multiple_choice';
                    normalized.question_text = question.question_text || '';
                    normalized.question_content = question.question_content || '';
                    normalized.explanation = question.explanation || '';
                    normalized.points = Number(question.points || 1);
                    normalized.allow_partial_credit = Boolean(question.allow_partial_credit);
                    normalized.code_language = question.code_language || '';
                    normalized.code_starter = question.code_starter || '';
                    normalized.code_solution = question.code_solution || '';
                    normalized.execution_timeout_seconds = question.execution_timeout_seconds || null;
                    normalized.metadata = question.metadata || null;

                    const embed = question.media_embed && typeof question.media_embed === 'object' ? question.media_embed : {};
                    normalized.media_embed = {
                        image_url: embed.image_url || '',
                        video_url: embed.video_url || '',
                        audio_url: embed.audio_url || '',
                        latex: embed.latex || '',
                    };

                    const answerPayload = question.answer_payload && typeof question.answer_payload === 'object'
                        ? question.answer_payload
                        : {};
                    normalized.answer_payload = {
                        blanks: Array.isArray(answerPayload.blanks)
                            ? answerPayload.blanks.map((blank) => ({ local_id: this.uid(), answer: blank.answer || blank || '' }))
                            : [],
                        pairs: Array.isArray(answerPayload.pairs)
                            ? answerPayload.pairs.map((pair) => ({ local_id: this.uid(), left: pair.left || '', right: pair.right || '' }))
                            : [],
                        items: Array.isArray(answerPayload.items)
                            ? answerPayload.items.map((item) => ({ local_id: this.uid(), value: item.value || item || '' }))
                            : [],
                    };

                    normalized.options = Array.isArray(question.options)
                        ? question.options.map((option) => ({
                            local_id: this.uid(),
                            id: option.id || null,
                            option_text: option.option_text || '',
                            content: option.content || '',
                            is_correct: Boolean(option.is_correct),
                            match_key: option.match_key || '',
                            option_payload: option.option_payload || null,
                        }))
                        : [];

                    if (Array.isArray(question.code_test_cases)) {
                        normalized.code_test_cases_text = JSON.stringify(question.code_test_cases, null, 2);
                    } else {
                        normalized.code_test_cases_text = '[]';
                    }

                    this.normalizeByType(normalized);

                    return normalized;
                },

                addQuestion(type) {
                    this.questions.push(this.defaultQuestion(type));
                    this.normalizeByType(this.questions[this.questions.length - 1]);
                },

                moveQuestion(index, direction) {
                    const target = index + direction;
                    if (target < 0 || target >= this.questions.length) {
                        return;
                    }

                    const copy = [...this.questions];
                    const item = copy[index];
                    copy[index] = copy[target];
                    copy[target] = item;
                    this.questions = copy;
                },

                duplicateQuestion(index) {
                    const clone = JSON.parse(JSON.stringify(this.questions[index]));
                    clone.id = null;
                    clone.local_id = this.uid();

                    if (Array.isArray(clone.options)) {
                        clone.options = clone.options.map((option) => ({ ...option, id: null, local_id: this.uid() }));
                    }

                    if (clone.answer_payload?.blanks) {
                        clone.answer_payload.blanks = clone.answer_payload.blanks.map((blank) => ({ ...blank, local_id: this.uid() }));
                    }
                    if (clone.answer_payload?.pairs) {
                        clone.answer_payload.pairs = clone.answer_payload.pairs.map((pair) => ({ ...pair, local_id: this.uid() }));
                    }
                    if (clone.answer_payload?.items) {
                        clone.answer_payload.items = clone.answer_payload.items.map((item) => ({ ...item, local_id: this.uid() }));
                    }

                    this.questions.splice(index + 1, 0, clone);
                },

                removeQuestion(index) {
                    this.questions.splice(index, 1);
                },

                isChoiceType(type) {
                    return ['multiple_choice', 'multiple_select', 'true_false'].includes(type);
                },

                normalizeByType(question) {
                    if (question.type === 'true_false') {
                        question.options = [
                            {
                                local_id: this.uid(),
                                id: question.options[0]?.id || null,
                                option_text: 'True',
                                content: question.options[0]?.content || 'True',
                                is_correct: question.options[0]?.is_correct || false,
                                match_key: '',
                                option_payload: null,
                            },
                            {
                                local_id: this.uid(),
                                id: question.options[1]?.id || null,
                                option_text: 'False',
                                content: question.options[1]?.content || 'False',
                                is_correct: question.options[1]?.is_correct || false,
                                match_key: '',
                                option_payload: null,
                            },
                        ];

                        if (!question.options.some((option) => option.is_correct)) {
                            question.options[0].is_correct = true;
                        }
                    }

                    if (this.isChoiceType(question.type) && question.options.length < 2) {
                        question.options.push(this.defaultOption('Option 1'), this.defaultOption('Option 2'));
                    }

                    if (question.type !== 'fill_blank') {
                        question.answer_payload.blanks = [];
                    }

                    if (question.type !== 'matching_pairs') {
                        question.answer_payload.pairs = [];
                    }

                    if (question.type !== 'ordering') {
                        question.answer_payload.items = [];
                    }

                    if (question.type !== 'code_challenge') {
                        question.code_language = '';
                        question.code_starter = '';
                        question.code_solution = '';
                        question.code_test_cases_text = '[]';
                        question.execution_timeout_seconds = null;
                    }
                },

                appendSnippet(question, field, snippet) {
                    const current = question[field] || '';
                    question[field] = current.length ? `${current}\n${snippet}` : snippet;
                },

                addOption(question) {
                    question.options.push(this.defaultOption(`Option ${question.options.length + 1}`));
                },

                removeOption(question, optionIndex) {
                    question.options.splice(optionIndex, 1);
                },

                setSingleCorrect(question, selectedIndex) {
                    question.options = question.options.map((option, index) => ({
                        ...option,
                        is_correct: index === selectedIndex,
                    }));
                },

                addBlank(question) {
                    question.answer_payload.blanks.push({ local_id: this.uid(), answer: '' });
                },

                removeBlank(question, blankIndex) {
                    question.answer_payload.blanks.splice(blankIndex, 1);
                },

                addPair(question) {
                    question.answer_payload.pairs.push({ local_id: this.uid(), left: '', right: '' });
                },

                removePair(question, pairIndex) {
                    question.answer_payload.pairs.splice(pairIndex, 1);
                },

                addOrderingItem(question) {
                    question.answer_payload.items.push({ local_id: this.uid(), value: '' });
                },

                removeOrderingItem(question, itemIndex) {
                    question.answer_payload.items.splice(itemIndex, 1);
                },

                toPayload() {
                    return this.questions.map((question, index) => {
                        let codeTests = [];

                        if (question.type === 'code_challenge') {
                            try {
                                codeTests = JSON.parse(question.code_test_cases_text || '[]');
                                if (!Array.isArray(codeTests)) {
                                    throw new Error('Code test cases must be an array.');
                                }
                            } catch (error) {
                                throw new Error(`Question ${index + 1}: invalid code test case JSON.`);
                            }
                        }

                        return {
                            id: question.id || null,
                            type: question.type,
                            question_text: question.question_text || '',
                            question_content: question.question_content || '',
                            explanation: question.explanation || '',
                            points: Number(question.points || 1),
                            options: this.isChoiceType(question.type)
                                ? question.options.map((option) => ({
                                    id: option.id || null,
                                    option_text: option.option_text || '',
                                    content: option.content || '',
                                    is_correct: Boolean(option.is_correct),
                                    match_key: option.match_key || null,
                                    option_payload: option.option_payload || null,
                                }))
                                : [],
                            answer_payload: {
                                blanks: question.type === 'fill_blank'
                                    ? question.answer_payload.blanks.map((blank) => ({ answer: blank.answer || '' }))
                                    : [],
                                pairs: question.type === 'matching_pairs'
                                    ? question.answer_payload.pairs.map((pair) => ({ left: pair.left || '', right: pair.right || '' }))
                                    : [],
                                items: question.type === 'ordering'
                                    ? question.answer_payload.items.map((item) => ({ value: item.value || '' }))
                                    : [],
                            },
                            media_embed: {
                                image_url: question.media_embed.image_url || null,
                                video_url: question.media_embed.video_url || null,
                                audio_url: question.media_embed.audio_url || null,
                                latex: question.media_embed.latex || null,
                            },
                            allow_partial_credit: Boolean(question.allow_partial_credit),
                            code_language: question.type === 'code_challenge' ? (question.code_language || null) : null,
                            code_starter: question.type === 'code_challenge' ? (question.code_starter || null) : null,
                            code_solution: question.type === 'code_challenge' ? (question.code_solution || null) : null,
                            code_test_cases: question.type === 'code_challenge' ? codeTests : [],
                            execution_timeout_seconds: question.type === 'code_challenge' && question.execution_timeout_seconds
                                ? Number(question.execution_timeout_seconds)
                                : null,
                            metadata: question.metadata || null,
                        };
                    });
                },

                prepareSubmit(event) {
                    try {
                        const payload = this.toPayload();
                        this.$refs.questionsJson.value = JSON.stringify(payload);
                    } catch (error) {
                        event.preventDefault();
                        alert(error.message || 'Unable to save quiz.');
                    }
                },
            };
        }
    </script>
</x-app-layout>
