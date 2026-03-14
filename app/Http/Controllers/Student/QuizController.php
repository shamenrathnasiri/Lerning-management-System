<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuestionOption;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    /**
     * Show quiz intro / start page.
     */
    public function show(Quiz $quiz)
    {
        $quiz->loadCount('questions', 'attempts');

        $userAttempts = $quiz->attempts()
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        $canAttempt = $quiz->hasRemainingAttempts(auth()->id());

        // Check for an existing in-progress attempt
        $inProgressAttempt = $userAttempts->firstWhere('completed_at', null);

        return view('student.quizzes.show', compact(
            'quiz',
            'userAttempts',
            'canAttempt',
            'inProgressAttempt'
        ));
    }

    /**
     * Start a new quiz attempt or resume an existing one.
     */
    public function start(Request $request, Quiz $quiz)
    {
        // Check for in-progress attempt first
        $existingAttempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('user_id', auth()->id())
            ->whereNull('completed_at')
            ->first();

        if ($existingAttempt) {
            return $this->loadQuizPlayer($quiz, $existingAttempt);
        }

        // Check remaining attempts
        if (! $quiz->hasRemainingAttempts(auth()->id())) {
            return redirect()
                ->route('student.quiz.show', $quiz)
                ->with('error', 'You have reached the maximum number of attempts for this quiz.');
        }

        // Create new attempt
        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => auth()->id(),
            'started_at' => now(),
        ]);

        return $this->loadQuizPlayer($quiz, $attempt);
    }

    /**
     * Load the quiz player view with questions.
     */
    private function loadQuizPlayer(Quiz $quiz, QuizAttempt $attempt)
    {
        $questions = $quiz->questions()
            ->with('options:id,question_id,option_text,match_key,sort_order')
            ->ordered()
            ->get();

        if ($quiz->shuffle_questions) {
            $questions = $questions->shuffle()->values();
        }

        return view('student.quizzes.play', compact('quiz', 'attempt', 'questions'));
    }

    /**
     * Auto-save answers (AJAX).
     */
    public function autoSave(Request $request, Quiz $quiz, QuizAttempt $attempt)
    {
        // Verify ownership
        if ($attempt->user_id !== auth()->id() || $attempt->quiz_id !== $quiz->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($attempt->completed_at) {
            return response()->json(['message' => 'Attempt already completed'], 409);
        }

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.question_option_id' => ['nullable', 'integer'],
            'answers.*.question_option_ids' => ['nullable', 'array'],
            'answers.*.answer_text' => ['nullable', 'string'],
        ]);

        foreach ($validated['answers'] as $answerData) {
            $question = $quiz->questions()->find($answerData['question_id']);
            if (! $question) {
                continue;
            }

            // Handle multi-select questions
            if (! empty($answerData['question_option_ids'])) {
                // Delete existing answers for this question
                QuizAnswer::where('quiz_attempt_id', $attempt->id)
                    ->where('question_id', $answerData['question_id'])
                    ->delete();

                foreach ($answerData['question_option_ids'] as $optionId) {
                    QuizAnswer::create([
                        'quiz_attempt_id' => $attempt->id,
                        'question_id' => $answerData['question_id'],
                        'question_option_id' => $optionId,
                    ]);
                }
            } else {
                // Single option or text answer - upsert
                QuizAnswer::updateOrCreate(
                    [
                        'quiz_attempt_id' => $attempt->id,
                        'question_id' => $answerData['question_id'],
                    ],
                    [
                        'question_option_id' => $answerData['question_option_id'] ?? null,
                        'answer_text' => $answerData['answer_text'] ?? null,
                    ]
                );
            }
        }

        return response()->json([
            'message' => 'Answers saved',
            'saved_at' => now()->format('H:i:s'),
        ]);
    }

    /**
     * Submit quiz (final submission).
     */
    public function submit(Request $request, Quiz $quiz)
    {
        $validated = $request->validate([
            'attempt_id' => ['required', 'integer'],
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.question_option_id' => ['nullable', 'integer'],
            'answers.*.question_option_ids' => ['nullable', 'array'],
            'answers.*.answer_text' => ['nullable', 'string'],
        ]);

        $attempt = QuizAttempt::findOrFail($validated['attempt_id']);

        if ($attempt->user_id !== auth()->id() || $attempt->quiz_id !== $quiz->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($attempt->completed_at) {
            return response()->json(['message' => 'This attempt has already been submitted.'], 409);
        }

        // Clear existing answers and re-create for accuracy
        $attempt->answers()->delete();

        $totalPoints = 0;
        $earnedPoints = 0;

        foreach ($validated['answers'] as $answerData) {
            $question = $quiz->questions()->find($answerData['question_id']);
            if (! $question) {
                continue;
            }

            $totalPoints += $question->points;

            // Handle multi-select
            if (! empty($answerData['question_option_ids'])) {
                $correctOptionIds = $question->correctOptions()->pluck('id')->toArray();
                $selectedIds = $answerData['question_option_ids'];

                $allCorrect = count(array_diff($correctOptionIds, $selectedIds)) === 0
                    && count(array_diff($selectedIds, $correctOptionIds)) === 0;

                $points = $allCorrect ? $question->points : 0;
                $earnedPoints += $points;

                foreach ($selectedIds as $optionId) {
                    $opt = QuestionOption::find($optionId);
                    QuizAnswer::create([
                        'quiz_attempt_id' => $attempt->id,
                        'question_id' => $question->id,
                        'question_option_id' => $optionId,
                        'is_correct' => $allCorrect,
                        'points_earned' => 0, // Points assigned at question level
                    ]);
                }

                // Award points on the first answer record
                if ($allCorrect && count($selectedIds) > 0) {
                    QuizAnswer::where('quiz_attempt_id', $attempt->id)
                        ->where('question_id', $question->id)
                        ->limit(1)
                        ->update(['points_earned' => $points]);
                }
            } else {
                // Single-select or text answer
                $isCorrect = false;
                $points = 0;

                if (! empty($answerData['question_option_id'])) {
                    $option = QuestionOption::find($answerData['question_option_id']);
                    $isCorrect = $option && $option->is_correct;
                    $points = $isCorrect ? $question->points : 0;
                }

                $earnedPoints += $points;

                QuizAnswer::create([
                    'quiz_attempt_id' => $attempt->id,
                    'question_id' => $question->id,
                    'question_option_id' => $answerData['question_option_id'] ?? null,
                    'answer_text' => $answerData['answer_text'] ?? null,
                    'is_correct' => $isCorrect,
                    'points_earned' => $points,
                ]);
            }
        }

        // Update attempt
        $percentage = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;

        $attempt->update([
            'score' => $earnedPoints,
            'percentage' => round($percentage, 2),
            'passed' => $percentage >= $quiz->pass_percentage,
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Quiz submitted successfully',
            'attempt_id' => $attempt->id,
            'score' => $earnedPoints,
            'percentage' => round($percentage, 2),
            'passed' => $percentage >= $quiz->pass_percentage,
        ]);
    }

    /**
     * Show quiz results.
     */
    public function results(Quiz $quiz, QuizAttempt $attempt)
    {
        if ($attempt->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        $attempt->load('answers.question.options', 'answers.selectedOption');

        return view('student.quizzes.results', compact('quiz', 'attempt'));
    }
}
