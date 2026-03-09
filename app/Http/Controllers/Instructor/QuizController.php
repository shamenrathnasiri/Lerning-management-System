<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuizController extends Controller
{
    private const QUIZ_TYPES = ['lesson_quiz', 'course_exam', 'practice_test'];
    private const TIME_LIMIT_MODES = ['per_quiz', 'per_question'];
    private const ANSWER_VISIBILITY = ['immediate', 'after_attempts'];
    private const NAVIGATION_MODES = ['free', 'sequential'];
    private const QUESTION_TYPES = [
        'multiple_choice',
        'multiple_select',
        'true_false',
        'fill_blank',
        'matching_pairs',
        'ordering',
        'essay',
        'code_challenge',
    ];

    public function create(Request $request): View
    {
        $courses = $this->instructorCourses();

        return view('instructor.quizzes.create', [
            'courses' => $courses,
            'quizTypes' => self::QUIZ_TYPES,
            'timeLimitModes' => self::TIME_LIMIT_MODES,
            'answerVisibilityModes' => self::ANSWER_VISIBILITY,
            'navigationModes' => self::NAVIGATION_MODES,
            'defaultCourseId' => $request->input('course_id'),
            'defaultLessonId' => $request->input('lesson_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateQuizSettings($request);

        $quiz = Quiz::create($validated + [
            'is_published' => false,
            'published_at' => null,
        ]);

        return redirect()
            ->route('instructor.quizzes.edit', $quiz)
            ->with('success', 'Quiz created. Configure questions and publishing settings below.');
    }

    public function edit(Quiz $quiz): View
    {
        $quiz->load(['course.sections.lessons', 'questions.options']);
        $this->authorizeInstructor($quiz->course);

        $courses = $this->instructorCourses();

        $questionPayload = $quiz->questions
            ->sortBy('sort_order')
            ->values()
            ->map(function (Question $question) {
                return [
                    'id' => $question->id,
                    'type' => $question->type,
                    'question_text' => $question->question_text,
                    'question_content' => $question->question_content,
                    'explanation' => $question->explanation,
                    'points' => $question->points,
                    'options' => $question->options
                        ->sortBy('sort_order')
                        ->values()
                        ->map(fn ($option) => [
                            'id' => $option->id,
                            'option_text' => $option->option_text,
                            'content' => $option->content,
                            'is_correct' => (bool) $option->is_correct,
                            'match_key' => $option->match_key,
                            'option_payload' => $option->option_payload,
                        ])
                        ->all(),
                    'answer_payload' => $question->answer_payload ?? [],
                    'media_embed' => $question->media_embed ?? [],
                    'allow_partial_credit' => (bool) $question->allow_partial_credit,
                    'code_language' => $question->code_language,
                    'code_starter' => $question->code_starter,
                    'code_solution' => $question->code_solution,
                    'code_test_cases' => $question->code_test_cases ?? [],
                    'execution_timeout_seconds' => $question->execution_timeout_seconds,
                    'metadata' => $question->metadata ?? [],
                ];
            })
            ->all();

        return view('instructor.quizzes.edit', [
            'quiz' => $quiz,
            'courses' => $courses,
            'quizTypes' => self::QUIZ_TYPES,
            'timeLimitModes' => self::TIME_LIMIT_MODES,
            'answerVisibilityModes' => self::ANSWER_VISIBILITY,
            'navigationModes' => self::NAVIGATION_MODES,
            'questionTypes' => self::QUESTION_TYPES,
            'questionPayload' => $questionPayload,
        ]);
    }

    public function update(Request $request, Quiz $quiz): RedirectResponse
    {
        $quiz->load('course');
        $this->authorizeInstructor($quiz->course);

        $validated = $this->validateQuizSettings($request, $quiz);
        $questions = $this->extractQuestions($request);
        $this->validateQuestions($questions);

        DB::transaction(function () use ($quiz, $validated, $questions) {
            $quiz->update($validated);
            $this->syncQuestions($quiz, $questions);
        });

        return redirect()
            ->route('instructor.quizzes.edit', $quiz)
            ->with('success', 'Quiz updated successfully.');
    }

    public function destroy(Quiz $quiz): RedirectResponse
    {
        $quiz->load('course');
        $this->authorizeInstructor($quiz->course);

        $course = $quiz->course;
        $quiz->delete();

        return redirect()
            ->route('instructor.courses.wizard', [$course, 'step' => 3])
            ->with('success', 'Quiz deleted.');
    }

    public function duplicate(Quiz $quiz): RedirectResponse
    {
        $quiz->load(['course', 'questions.options']);
        $this->authorizeInstructor($quiz->course);

        $newQuiz = DB::transaction(function () use ($quiz) {
            $copy = $quiz->replicate(['slug', 'is_published', 'published_at', 'show_correct_answers']);
            $copy->title = $quiz->title . ' (Copy)';
            $copy->is_published = false;
            $copy->published_at = null;
            $copy->show_correct_answers = false;
            $copy->save();

            foreach ($quiz->questions as $question) {
                $newQuestion = $question->replicate(['quiz_id']);
                $newQuestion->quiz_id = $copy->id;
                $newQuestion->save();

                foreach ($question->options as $option) {
                    $newOption = $option->replicate(['question_id']);
                    $newOption->question_id = $newQuestion->id;
                    $newOption->save();
                }
            }

            return $copy;
        });

        return redirect()
            ->route('instructor.quizzes.edit', $newQuiz)
            ->with('success', 'Quiz duplicated.');
    }

    public function publish(Quiz $quiz): RedirectResponse
    {
        $quiz->load('course');
        $this->authorizeInstructor($quiz->course);

        $quiz->update([
            'is_published' => true,
            'published_at' => now(),
            'show_correct_answers' => $quiz->answer_visibility === 'immediate',
        ]);

        return back()->with('success', 'Quiz published.');
    }

    public function unpublish(Quiz $quiz): RedirectResponse
    {
        $quiz->load('course');
        $this->authorizeInstructor($quiz->course);

        $quiz->update([
            'is_published' => false,
            'published_at' => null,
            'show_correct_answers' => false,
        ]);

        return back()->with('success', 'Quiz unpublished.');
    }

    private function instructorCourses()
    {
        return Course::where('instructor_id', Auth::id())
            ->with([
                'sections' => fn ($q) => $q->ordered()->with(['lessons' => fn ($l) => $l->ordered()]),
            ])
            ->orderBy('title')
            ->get();
    }

    private function validateQuizSettings(Request $request, ?Quiz $quiz = null): array
    {
        $validated = $request->validate([
            'course_id' => [
                'required',
                Rule::exists('courses', 'id')->where(fn ($q) => $q->where('instructor_id', Auth::id())),
            ],
            'lesson_id' => ['nullable', 'exists:lessons,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
            'quiz_type' => ['required', Rule::in(self::QUIZ_TYPES)],
            'time_limit_mode' => ['required', Rule::in(self::TIME_LIMIT_MODES)],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'per_question_time_seconds' => ['nullable', 'integer', 'min:5', 'max:3600'],
            'pass_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'attempts_allowed' => ['nullable', 'integer', 'min:1', 'max:100'],
            'randomize_questions' => ['nullable', 'boolean'],
            'randomize_options' => ['nullable', 'boolean'],
            'answer_visibility' => ['required', Rule::in(self::ANSWER_VISIBILITY)],
            'show_answers_after_attempts' => ['nullable', 'integer', 'min:1', 'max:100'],
            'navigation_mode' => ['required', Rule::in(self::NAVIGATION_MODES)],
        ]);

        if ($validated['quiz_type'] === 'lesson_quiz') {
            if (empty($validated['lesson_id'])) {
                throw ValidationException::withMessages([
                    'lesson_id' => 'Lesson is required for lesson quiz type.',
                ]);
            }

            $lessonBelongs = Lesson::where('id', $validated['lesson_id'])
                ->where('course_id', $validated['course_id'])
                ->exists();

            if (! $lessonBelongs) {
                throw ValidationException::withMessages([
                    'lesson_id' => 'Selected lesson does not belong to selected course.',
                ]);
            }
        } else {
            $validated['lesson_id'] = null;
        }

        if ($validated['time_limit_mode'] === 'per_quiz' && empty($validated['time_limit_minutes'])) {
            throw ValidationException::withMessages([
                'time_limit_minutes' => 'Time limit is required when using per-quiz mode.',
            ]);
        }

        if ($validated['time_limit_mode'] === 'per_question' && empty($validated['per_question_time_seconds'])) {
            throw ValidationException::withMessages([
                'per_question_time_seconds' => 'Per-question time is required in per-question mode.',
            ]);
        }

        if (
            $validated['answer_visibility'] === 'after_attempts'
            && empty($validated['show_answers_after_attempts'])
        ) {
            throw ValidationException::withMessages([
                'show_answers_after_attempts' => 'Specify attempts threshold for showing answers.',
            ]);
        }

        return [
            'course_id' => (int) $validated['course_id'],
            'lesson_id' => $validated['lesson_id'] ? (int) $validated['lesson_id'] : null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'instructions' => $validated['instructions'] ?? null,
            'quiz_type' => $validated['quiz_type'],
            'time_limit_mode' => $validated['time_limit_mode'],
            'time_limit_minutes' => $validated['time_limit_mode'] === 'per_quiz'
                ? (int) ($validated['time_limit_minutes'] ?? 0)
                : null,
            'per_question_time_seconds' => $validated['time_limit_mode'] === 'per_question'
                ? (int) ($validated['per_question_time_seconds'] ?? 0)
                : null,
            'pass_percentage' => (float) $validated['pass_percentage'],
            'max_attempts' => ! empty($validated['attempts_allowed'])
                ? (int) $validated['attempts_allowed']
                : null,
            'shuffle_questions' => (bool) ($validated['randomize_questions'] ?? false),
            'randomize_options' => (bool) ($validated['randomize_options'] ?? false),
            'answer_visibility' => $validated['answer_visibility'],
            'show_answers_after_attempts' => $validated['answer_visibility'] === 'after_attempts'
                ? (int) ($validated['show_answers_after_attempts'] ?? 1)
                : null,
            'navigation_mode' => $validated['navigation_mode'],
            'show_correct_answers' => $validated['answer_visibility'] === 'immediate',
            'settings' => [
                'builder_version' => 1,
                'updated_by' => Auth::id(),
                'updated_at' => now()->toIso8601String(),
                'is_update' => (bool) $quiz,
            ],
        ];
    }

    private function extractQuestions(Request $request): array
    {
        $raw = $request->input('questions_json');
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'questions_json' => 'Invalid questions payload format.',
            ]);
        }

        return array_values($decoded);
    }

    private function validateQuestions(array $questions): void
    {
        $errors = [];

        foreach ($questions as $index => $question) {
            $prefix = 'Question ' . ($index + 1);
            $type = $question['type'] ?? null;

            if (! $type || ! in_array($type, self::QUESTION_TYPES, true)) {
                $errors["questions.{$index}.type"] = "{$prefix}: Unsupported type.";
                continue;
            }

            $text = trim((string) ($question['question_text'] ?? ''));
            $content = trim((string) ($question['question_content'] ?? ''));
            if ($text === '' && $content === '') {
                $errors["questions.{$index}.question_text"] = "{$prefix}: Question text/content is required.";
            }

            $options = is_array($question['options'] ?? null) ? $question['options'] : [];
            $correctCount = collect($options)->filter(fn ($o) => ! empty($o['is_correct']))->count();

            if (in_array($type, ['multiple_choice', 'multiple_select', 'true_false'], true)) {
                if (count($options) < 2) {
                    $errors["questions.{$index}.options"] = "{$prefix}: At least two options are required.";
                }

                if (in_array($type, ['multiple_choice', 'true_false'], true) && $correctCount !== 1) {
                    $errors["questions.{$index}.options_correct"] = "{$prefix}: Exactly one correct option is required.";
                }

                if ($type === 'multiple_select' && $correctCount < 1) {
                    $errors["questions.{$index}.options_correct"] = "{$prefix}: At least one correct option is required.";
                }
            }

            $answerPayload = is_array($question['answer_payload'] ?? null) ? $question['answer_payload'] : [];

            if ($type === 'fill_blank' && empty($answerPayload['blanks'])) {
                $errors["questions.{$index}.answer_payload.blanks"] = "{$prefix}: Provide at least one blank answer.";
            }

            if ($type === 'matching_pairs' && empty($answerPayload['pairs'])) {
                $errors["questions.{$index}.answer_payload.pairs"] = "{$prefix}: Provide matching pairs.";
            }

            if ($type === 'ordering' && empty($answerPayload['items'])) {
                $errors["questions.{$index}.answer_payload.items"] = "{$prefix}: Provide ordering items.";
            }

            if ($type === 'code_challenge') {
                if (empty($question['code_language'])) {
                    $errors["questions.{$index}.code_language"] = "{$prefix}: Code language is required.";
                }

                if (empty($question['code_test_cases']) || ! is_array($question['code_test_cases'])) {
                    $errors["questions.{$index}.code_test_cases"] = "{$prefix}: Provide code test cases.";
                }
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function syncQuestions(Quiz $quiz, array $questions): void
    {
        $existing = $quiz->questions()->with('options')->get()->keyBy('id');
        $keptIds = [];

        foreach ($questions as $index => $payload) {
            $questionId = ! empty($payload['id']) ? (int) $payload['id'] : null;

            if ($questionId && $existing->has($questionId)) {
                $question = $existing->get($questionId);
            } else {
                $question = new Question();
                $question->quiz_id = $quiz->id;
            }

            $question->type = $payload['type'];
            $question->question_text = trim((string) ($payload['question_text'] ?? strip_tags((string) ($payload['question_content'] ?? ''))));
            $question->question_content = $payload['question_content'] ?? null;
            $question->explanation = $payload['explanation'] ?? null;
            $question->points = max(1, (int) ($payload['points'] ?? 1));
            $question->sort_order = $index + 1;
            $question->answer_payload = is_array($payload['answer_payload'] ?? null) ? $payload['answer_payload'] : null;
            $question->media_embed = is_array($payload['media_embed'] ?? null) ? $payload['media_embed'] : null;
            $question->allow_partial_credit = (bool) ($payload['allow_partial_credit'] ?? false);
            $question->code_language = $payload['code_language'] ?? null;
            $question->code_starter = $payload['code_starter'] ?? null;
            $question->code_solution = $payload['code_solution'] ?? null;
            $question->code_test_cases = is_array($payload['code_test_cases'] ?? null) ? $payload['code_test_cases'] : null;
            $question->execution_timeout_seconds = ! empty($payload['execution_timeout_seconds'])
                ? (int) $payload['execution_timeout_seconds']
                : null;
            $question->metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : null;
            $question->save();

            $keptIds[] = $question->id;
            $this->syncQuestionOptions($question, is_array($payload['options'] ?? null) ? $payload['options'] : []);
        }

        $quiz->questions()->whereNotIn('id', $keptIds)->get()->each(function (Question $question) {
            $question->options()->delete();
            $question->delete();
        });
    }

    private function syncQuestionOptions(Question $question, array $options): void
    {
        $existingOptions = $question->options()->get()->keyBy('id');
        $keptIds = [];

        foreach ($options as $index => $optionData) {
            $optionId = ! empty($optionData['id']) ? (int) $optionData['id'] : null;

            if ($optionId && $existingOptions->has($optionId)) {
                $option = $existingOptions->get($optionId);
            } else {
                $option = $question->options()->make();
            }

            $option->option_text = $optionData['option_text'] ?? strip_tags((string) ($optionData['content'] ?? ''));
            $option->content = $optionData['content'] ?? null;
            $option->is_correct = (bool) ($optionData['is_correct'] ?? false);
            $option->sort_order = $index + 1;
            $option->match_key = $optionData['match_key'] ?? null;
            $option->option_payload = is_array($optionData['option_payload'] ?? null) ? $optionData['option_payload'] : null;
            $option->save();

            $keptIds[] = $option->id;
        }

        $question->options()->whereNotIn('id', $keptIds)->delete();
    }

    private function authorizeInstructor(Course $course): void
    {
        abort_unless(Auth::check() && $course->instructor_id === Auth::id(), 403);
    }
}
