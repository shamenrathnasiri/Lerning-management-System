<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAnswer;
use App\Models\QuestionOption;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function index(Request $request)
    {
        $query = Quiz::with('course:id,title,slug')
            ->withCount('questions');

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        return response()->json(
            $query->latest()->paginate($request->integer('per_page', 15))
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'lesson_id' => ['nullable', 'exists:lessons,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'max_attempts' => ['nullable', 'integer', 'min:1'],
            'pass_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'shuffle_questions' => ['nullable', 'boolean'],
            'show_correct_answers' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $quiz = Quiz::create($validated);

        return response()->json($quiz, 201);
    }

    public function show(Quiz $quiz)
    {
        return response()->json(
            $quiz->load('questions.options', 'course:id,title')
                ->loadCount('questions', 'attempts')
        );
    }

    public function update(Request $request, Quiz $quiz)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'max_attempts' => ['nullable', 'integer', 'min:1'],
            'pass_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'shuffle_questions' => ['nullable', 'boolean'],
            'show_correct_answers' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $quiz->update($validated);

        return response()->json($quiz);
    }

    public function destroy(Quiz $quiz)
    {
        $quiz->delete();

        return response()->json(['message' => 'Quiz deleted.']);
    }

    public function attempt(Request $request, Quiz $quiz)
    {
        if ($quiz->max_attempts) {
            $attemptCount = QuizAttempt::where('quiz_id', $quiz->id)
                ->where('user_id', $request->user()->id)
                ->count();

            if ($attemptCount >= $quiz->max_attempts) {
                return response()->json(['message' => 'Maximum attempts reached.'], 403);
            }
        }

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $request->user()->id,
            'started_at' => now(),
        ]);

        $questions = $quiz->questions()->with('options:id,question_id,option_text,sort_order')->get();

        if ($quiz->shuffle_questions) {
            $questions = $questions->shuffle();
        }

        return response()->json([
            'attempt' => $attempt,
            'questions' => $questions,
        ], 201);
    }

    public function submitAttempt(Request $request, Quiz $quiz, QuizAttempt $attempt)
    {
        if ($attempt->completed_at) {
            return response()->json(['message' => 'This attempt has already been submitted.'], 409);
        }

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'exists:questions,id'],
            'answers.*.question_option_id' => ['nullable', 'exists:question_options,id'],
            'answers.*.answer_text' => ['nullable', 'string'],
        ]);

        $totalPoints = 0;
        $earnedPoints = 0;

        foreach ($validated['answers'] as $answerData) {
            $question = $quiz->questions()->find($answerData['question_id']);
            if (! $question) {
                continue;
            }

            $totalPoints += $question->points;
            $isCorrect = false;
            $points = 0;

            if (isset($answerData['question_option_id'])) {
                $option = QuestionOption::find($answerData['question_option_id']);
                $isCorrect = $option && $option->is_correct;
                $points = $isCorrect ? $question->points : 0;
            }

            $earnedPoints += $points;

            QuizAnswer::create([
                'quiz_attempt_id' => $attempt->id,
                'question_id' => $answerData['question_id'],
                'question_option_id' => $answerData['question_option_id'] ?? null,
                'answer_text' => $answerData['answer_text'] ?? null,
                'is_correct' => $isCorrect,
                'points_earned' => $points,
            ]);
        }

        $percentage = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;

        $attempt->update([
            'score' => $earnedPoints,
            'percentage' => $percentage,
            'passed' => $percentage >= $quiz->pass_percentage,
            'completed_at' => now(),
        ]);

        return response()->json($attempt->load('answers'));
    }

    public function attempts(Request $request, Quiz $quiz)
    {
        return response()->json(
            QuizAttempt::where('quiz_id', $quiz->id)
                ->where('user_id', $request->user()->id)
                ->with('answers')
                ->latest()
                ->get()
        );
    }
}
