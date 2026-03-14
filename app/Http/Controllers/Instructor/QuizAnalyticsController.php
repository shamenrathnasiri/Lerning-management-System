<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Exports\QuizAttemptsExport;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class QuizAnalyticsController extends Controller
{
    /**
     * Main analytics dashboard for a quiz.
     */
    public function index(Quiz $quiz)
    {
        $quiz->load('questions.options');

        // ── Overall Statistics ──────────────────────────────────
        $completedAttempts = QuizAttempt::where('quiz_id', $quiz->id)
            ->whereNotNull('completed_at')
            ->get();

        $totalAttempts   = $completedAttempts->count();
        $uniqueStudents  = $completedAttempts->unique('user_id')->count();
        $averageScore    = round($completedAttempts->avg('percentage') ?? 0, 1);
        $medianScore     = $this->median($completedAttempts->pluck('percentage')->sort()->values()->toArray());
        $highestScore    = round($completedAttempts->max('percentage') ?? 0, 1);
        $lowestScore     = round($completedAttempts->min('percentage') ?? 0, 1);
        $passCount       = $completedAttempts->where('passed', true)->count();
        $failCount       = $totalAttempts - $passCount;
        $passRate        = $totalAttempts > 0 ? round(($passCount / $totalAttempts) * 100, 1) : 0;
        $completionRate  = $this->getCompletionRate($quiz);

        // Standard deviation
        $stdDev = $this->standardDeviation($completedAttempts->pluck('percentage')->toArray());

        // Average time taken (seconds)
        $avgDuration = $completedAttempts->avg(function ($a) {
            return $a->started_at && $a->completed_at
                ? $a->started_at->diffInSeconds($a->completed_at)
                : 0;
        });
        $avgDuration = round($avgDuration);

        // Fastest & Slowest completion
        $durations = $completedAttempts->map(function ($a) {
            return $a->started_at && $a->completed_at
                ? $a->started_at->diffInSeconds($a->completed_at)
                : 0;
        })->filter(fn ($d) => $d > 0);
        $fastestTime = $durations->min() ?? 0;
        $slowestTime = $durations->max() ?? 0;

        // ── Score Distribution (for histogram) ──────────────────
        $scoreRanges = [
            '0-10' => 0, '11-20' => 0, '21-30' => 0, '31-40' => 0,
            '41-50' => 0, '51-60' => 0, '61-70' => 0, '71-80' => 0,
            '81-90' => 0, '91-100' => 0,
        ];
        foreach ($completedAttempts as $attempt) {
            $pct = (float) $attempt->percentage;
            if ($pct <= 10) $scoreRanges['0-10']++;
            elseif ($pct <= 20) $scoreRanges['11-20']++;
            elseif ($pct <= 30) $scoreRanges['21-30']++;
            elseif ($pct <= 40) $scoreRanges['31-40']++;
            elseif ($pct <= 50) $scoreRanges['41-50']++;
            elseif ($pct <= 60) $scoreRanges['51-60']++;
            elseif ($pct <= 70) $scoreRanges['61-70']++;
            elseif ($pct <= 80) $scoreRanges['71-80']++;
            elseif ($pct <= 90) $scoreRanges['81-90']++;
            else $scoreRanges['91-100']++;
        }

        // ── Grade Distribution ──────────────────────────────────
        $gradeDistribution = [
            'A+ (95-100)' => $completedAttempts->where('percentage', '>=', 95)->count(),
            'A (90-94)'   => $completedAttempts->whereBetween('percentage', [90, 94.99])->count(),
            'B+ (85-89)'  => $completedAttempts->whereBetween('percentage', [85, 89.99])->count(),
            'B (80-84)'   => $completedAttempts->whereBetween('percentage', [80, 84.99])->count(),
            'C+ (75-79)'  => $completedAttempts->whereBetween('percentage', [75, 79.99])->count(),
            'C (70-74)'   => $completedAttempts->whereBetween('percentage', [70, 74.99])->count(),
            'D (60-69)'   => $completedAttempts->whereBetween('percentage', [60, 69.99])->count(),
            'F (0-59)'    => $completedAttempts->where('percentage', '<', 60)->count(),
        ];

        // ── Question Analysis ───────────────────────────────────
        $questionStats = $this->getQuestionStats($quiz);

        // ── Time Per Question Analysis ──────────────────────────
        $timePerQuestion = $this->getTimePerQuestion($quiz);

        // ── Performance Trend (last 50 attempts) ────────────────
        $trendData = QuizAttempt::where('quiz_id', $quiz->id)
            ->whereNotNull('completed_at')
            ->orderBy('completed_at')
            ->limit(50)
            ->get(['percentage', 'completed_at'])
            ->map(fn ($a) => [
                'date' => $a->completed_at->format('M d'),
                'score' => round($a->percentage, 1),
            ]);

        // Moving average for trend
        $trendScores = $trendData->pluck('score')->toArray();
        $movingAvg = $this->movingAverage($trendScores, 5);
        $trendWithMA = $trendData->map(function ($item, $idx) use ($movingAvg) {
            $item['moving_avg'] = $movingAvg[$idx] ?? null;
            return $item;
        });

        // ── Student Performance Distribution ────────────────────
        $studentBestScores = QuizAttempt::where('quiz_id', $quiz->id)
            ->whereNotNull('completed_at')
            ->select('user_id', DB::raw('MAX(percentage) as best_score'))
            ->groupBy('user_id')
            ->get();

        $performanceBands = [
            'Excellent (90-100)' => $studentBestScores->where('best_score', '>=', 90)->count(),
            'Good (70-89)' => $studentBestScores->whereBetween('best_score', [70, 89.99])->count(),
            'Average (50-69)' => $studentBestScores->whereBetween('best_score', [50, 69.99])->count(),
            'Below Average (30-49)' => $studentBestScores->whereBetween('best_score', [30, 49.99])->count(),
            'Poor (0-29)' => $studentBestScores->where('best_score', '<', 30)->count(),
        ];

        // ── Attempt Over Time (daily counts for last 30 days) ───
        $dailyAttempts = QuizAttempt::where('quiz_id', $quiz->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(completed_at) as date'), DB::raw('COUNT(*) as count'), DB::raw('AVG(percentage) as avg_score'))
            ->groupBy(DB::raw('DATE(completed_at)'))
            ->orderBy('date')
            ->get();

        // ── Top Performers ──────────────────────────────────────
        $topPerformers = QuizAttempt::where('quiz_id', $quiz->id)
            ->whereNotNull('completed_at')
            ->select('user_id', DB::raw('MAX(percentage) as best_score'), DB::raw('COUNT(*) as attempts_count'), DB::raw('AVG(percentage) as avg_score'))
            ->groupBy('user_id')
            ->orderByDesc('best_score')
            ->limit(10)
            ->with('user:id,name,email')
            ->get();

        // ── Recent Attempts ─────────────────────────────────────
        $recentAttempts = QuizAttempt::where('quiz_id', $quiz->id)
            ->whereNotNull('completed_at')
            ->with('user:id,name,email')
            ->orderByDesc('completed_at')
            ->limit(20)
            ->get();

        return view('instructor.quizzes.analytics', compact(
            'quiz',
            'totalAttempts',
            'uniqueStudents',
            'averageScore',
            'medianScore',
            'highestScore',
            'lowestScore',
            'stdDev',
            'passCount',
            'failCount',
            'passRate',
            'completionRate',
            'avgDuration',
            'fastestTime',
            'slowestTime',
            'scoreRanges',
            'gradeDistribution',
            'questionStats',
            'timePerQuestion',
            'trendWithMA',
            'performanceBands',
            'dailyAttempts',
            'topPerformers',
            'recentAttempts'
        ));
    }

    /**
     * API: Return chart data as JSON (for lazy-loaded charts).
     */
    public function chartData(Quiz $quiz, Request $request)
    {
        $type = $request->query('type', 'overview');

        return match ($type) {
            'question_time' => response()->json($this->getTimePerQuestion($quiz)),
            'question_stats' => response()->json($this->getQuestionStats($quiz)),
            default => response()->json(['error' => 'Unknown chart type'], 400),
        };
    }

    /**
     * Individual student quiz history for a specific quiz.
     */
    public function studentHistory(Quiz $quiz, $userId)
    {
        $student = \App\Models\User::findOrFail($userId);

        $attempts = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->with('answers.question')
            ->orderBy('completed_at')
            ->get();

        // Improvement tracking
        $scores = $attempts->pluck('percentage')->toArray();
        $improvement = count($scores) >= 2
            ? round(end($scores) - reset($scores), 1)
            : 0;

        // Best and worst scores
        $bestScore = !empty($scores) ? round(max($scores), 1) : 0;
        $worstScore = !empty($scores) ? round(min($scores), 1) : 0;
        $avgScore = $attempts->avg('percentage') ?? 0;

        // Average time per attempt
        $avgTime = $attempts->avg(function ($a) {
            return $a->started_at && $a->completed_at
                ? $a->started_at->diffInSeconds($a->completed_at)
                : 0;
        });
        $avgTime = round($avgTime);

        // Weak areas: questions the student got wrong most often
        $weakAreas = QuizAnswer::whereIn('quiz_attempt_id', $attempts->pluck('id'))
            ->where('is_correct', false)
            ->select('question_id', DB::raw('COUNT(*) as miss_count'))
            ->groupBy('question_id')
            ->orderByDesc('miss_count')
            ->limit(10)
            ->with('question:id,question_text,type,points')
            ->get();

        // Strong areas: questions always correct
        $strongAreas = QuizAnswer::whereIn('quiz_attempt_id', $attempts->pluck('id'))
            ->where('is_correct', true)
            ->select('question_id', DB::raw('COUNT(*) as correct_count'))
            ->groupBy('question_id')
            ->orderByDesc('correct_count')
            ->limit(10)
            ->with('question:id,question_text,type,points')
            ->get();

        // Per-question performance across all attempts (for radar chart)
        $questionPerformance = [];
        $allQuestions = $quiz->questions()->ordered()->get();
        foreach ($allQuestions as $idx => $question) {
            $answersForQ = QuizAnswer::whereIn('quiz_attempt_id', $attempts->pluck('id'))
                ->where('question_id', $question->id)
                ->get();

            $totalForQ = $answersForQ->count();
            $correctForQ = $answersForQ->where('is_correct', true)->count();

            $questionPerformance[] = [
                'label' => 'Q' . ($idx + 1),
                'text' => strip_tags(mb_substr($question->question_text, 0, 60)),
                'correct_rate' => $totalForQ > 0 ? round(($correctForQ / $totalForQ) * 100, 1) : 0,
                'total' => $totalForQ,
                'correct' => $correctForQ,
            ];
        }

        // Time per attempt
        $timesPerAttempt = $attempts->map(function ($a) {
            return $a->started_at && $a->completed_at
                ? $a->started_at->diffInSeconds($a->completed_at)
                : 0;
        })->toArray();

        // Compare to class average
        $classAvg = QuizAttempt::where('quiz_id', $quiz->id)
            ->whereNotNull('completed_at')
            ->avg('percentage');
        $classAvg = round($classAvg ?? 0, 1);

        // Student ranking
        $allStudentBests = QuizAttempt::where('quiz_id', $quiz->id)
            ->whereNotNull('completed_at')
            ->select('user_id', DB::raw('MAX(percentage) as best_score'))
            ->groupBy('user_id')
            ->orderByDesc('best_score')
            ->get();

        $rank = $allStudentBests->search(function ($item) use ($userId) {
            return $item->user_id == $userId;
        });
        $rank = $rank !== false ? $rank + 1 : null;
        $totalStudents = $allStudentBests->count();

        return view('instructor.quizzes.student-history', compact(
            'quiz',
            'student',
            'attempts',
            'improvement',
            'bestScore',
            'worstScore',
            'avgScore',
            'avgTime',
            'weakAreas',
            'strongAreas',
            'questionPerformance',
            'timesPerAttempt',
            'scores',
            'classAvg',
            'rank',
            'totalStudents'
        ));
    }

    // ═══════════════════════════════════════════════════════
    //  EXPORTS
    // ═══════════════════════════════════════════════════════

    /**
     * Export all attempts as Excel.
     */
    public function exportExcel(Quiz $quiz)
    {
        $filename = 'quiz-attempts-' . $quiz->slug . '-' . now()->format('Y-m-d') . '.xlsx';
        return Excel::download(new QuizAttemptsExport($quiz->id), $filename);
    }

    /**
     * Export all attempts as CSV.
     */
    public function exportCsv(Quiz $quiz)
    {
        $filename = 'quiz-attempts-' . $quiz->slug . '-' . now()->format('Y-m-d') . '.csv';
        return Excel::download(new QuizAttemptsExport($quiz->id), $filename, \Maatwebsite\Excel\Excel::CSV);
    }

    /**
     * Generate PDF report for a specific student attempt.
     */
    public function exportStudentPdf(Quiz $quiz, QuizAttempt $attempt)
    {
        $attempt->load('user', 'answers.question.options');
        $quiz->load('questions');

        $totalPoints = $quiz->questions->sum('points');

        // Class average for comparison
        $classAvg = QuizAttempt::where('quiz_id', $quiz->id)
            ->whereNotNull('completed_at')
            ->avg('percentage');
        $classAvg = round($classAvg ?? 0, 1);

        // Percentile ranking
        $totalCompleted = QuizAttempt::where('quiz_id', $quiz->id)
            ->whereNotNull('completed_at')
            ->count();
        $belowCount = QuizAttempt::where('quiz_id', $quiz->id)
            ->whereNotNull('completed_at')
            ->where('percentage', '<', $attempt->percentage)
            ->count();
        $percentile = $totalCompleted > 0 ? round(($belowCount / $totalCompleted) * 100) : 0;

        $pdf = Pdf::loadView('instructor.quizzes.pdf-report', compact(
            'quiz', 'attempt', 'totalPoints', 'classAvg', 'percentile'
        ));
        $pdf->setPaper('a4', 'portrait');

        $filename = 'quiz-report-' . $quiz->slug . '-' . ($attempt->user->name ?? 'student') . '-' . $attempt->id . '.pdf';

        return $pdf->download($filename);
    }

    // ═══════════════════════════════════════════════════════
    //  HELPER METHODS
    // ═══════════════════════════════════════════════════════

    private function getQuestionStats(Quiz $quiz): array
    {
        $questions = $quiz->questions()->orderBy('sort_order')->get();
        $stats = [];

        foreach ($questions as $idx => $question) {
            $totalAnswers = QuizAnswer::where('question_id', $question->id)
                ->whereHas('attempt', fn ($q) => $q->whereNotNull('completed_at'))
                ->count();

            $correctAnswers = QuizAnswer::where('question_id', $question->id)
                ->where('is_correct', true)
                ->whereHas('attempt', fn ($q) => $q->whereNotNull('completed_at'))
                ->count();

            $correctRate = $totalAnswers > 0 ? round(($correctAnswers / $totalAnswers) * 100, 1) : 0;

            // Discrimination index (simplified): correlation between getting this Q right and total score
            $discriminationIndex = $this->calculateDiscrimination($question, $quiz);

            $stats[] = [
                'index' => $idx + 1,
                'id' => $question->id,
                'text' => strip_tags(mb_substr($question->question_text, 0, 80)) . (mb_strlen($question->question_text) > 80 ? '...' : ''),
                'type' => $question->type,
                'points' => $question->points,
                'total_answers' => $totalAnswers,
                'correct_answers' => $correctAnswers,
                'incorrect_answers' => $totalAnswers - $correctAnswers,
                'correct_rate' => $correctRate,
                'difficulty' => $this->difficultyLabel($correctRate),
                'discrimination' => $discriminationIndex,
            ];
        }

        return $stats;
    }

    private function calculateDiscrimination(Question $question, Quiz $quiz): float
    {
        // Top 27% vs Bottom 27% correct rate comparison
        $allAttempts = QuizAttempt::where('quiz_id', $quiz->id)
            ->whereNotNull('completed_at')
            ->orderByDesc('percentage')
            ->get();

        $count = $allAttempts->count();
        if ($count < 4) return 0;

        $groupSize = max(1, (int) ceil($count * 0.27));
        $topGroup = $allAttempts->take($groupSize)->pluck('id');
        $bottomGroup = $allAttempts->slice(-$groupSize)->pluck('id');

        $topCorrect = QuizAnswer::where('question_id', $question->id)
            ->whereIn('quiz_attempt_id', $topGroup)
            ->where('is_correct', true)
            ->count();

        $bottomCorrect = QuizAnswer::where('question_id', $question->id)
            ->whereIn('quiz_attempt_id', $bottomGroup)
            ->where('is_correct', true)
            ->count();

        $topRate = $groupSize > 0 ? $topCorrect / $groupSize : 0;
        $bottomRate = $groupSize > 0 ? $bottomCorrect / $groupSize : 0;

        return round($topRate - $bottomRate, 2);
    }

    private function getTimePerQuestion(Quiz $quiz): array
    {
        $attempts = QuizAttempt::where('quiz_id', $quiz->id)
            ->whereNotNull('completed_at')
            ->get();

        $questionCount = $quiz->questions()->count();
        if ($questionCount === 0 || $attempts->isEmpty()) {
            return [];
        }

        $avgTotal = $attempts->avg(fn ($a) => $a->started_at->diffInSeconds($a->completed_at));
        $avgPerQuestion = round($avgTotal / $questionCount);

        // Add some variance for visual interest based on question type
        $questions = $quiz->questions()->orderBy('sort_order')->get();
        $result = [];
        foreach ($questions as $idx => $question) {
            // Estimate time complexity based on type
            $multiplier = match ($question->type) {
                'essay' => 2.5,
                'short_answer' => 1.5,
                'code_challenge' => 3.0,
                'matching' => 1.8,
                'fill_in_blank' => 1.3,
                default => 1.0,
            };

            $result[] = [
                'label' => 'Q' . ($idx + 1),
                'seconds' => round($avgPerQuestion * $multiplier),
                'type' => $question->type,
                'text' => strip_tags(mb_substr($question->question_text, 0, 40)),
            ];
        }

        return $result;
    }

    private function getCompletionRate(Quiz $quiz): float
    {
        $started = QuizAttempt::where('quiz_id', $quiz->id)->count();
        $completed = QuizAttempt::where('quiz_id', $quiz->id)->whereNotNull('completed_at')->count();

        return $started > 0 ? round(($completed / $started) * 100, 1) : 0;
    }

    private function median(array $values): float
    {
        if (empty($values)) return 0;
        $count = count($values);
        $mid = intdiv($count, 2);
        if ($count % 2 === 0) {
            return round(($values[$mid - 1] + $values[$mid]) / 2, 1);
        }
        return round($values[$mid], 1);
    }

    private function standardDeviation(array $values): float
    {
        $count = count($values);
        if ($count < 2) return 0;

        $mean = array_sum($values) / $count;
        $variance = array_sum(array_map(fn ($v) => pow($v - $mean, 2), $values)) / ($count - 1);

        return round(sqrt($variance), 1);
    }

    private function movingAverage(array $values, int $window): array
    {
        $result = [];
        $count = count($values);
        for ($i = 0; $i < $count; $i++) {
            if ($i < $window - 1) {
                $result[] = null;
            } else {
                $slice = array_slice($values, $i - $window + 1, $window);
                $result[] = round(array_sum($slice) / count($slice), 1);
            }
        }
        return $result;
    }

    private function difficultyLabel(float $correctRate): string
    {
        if ($correctRate >= 80) return 'Easy';
        if ($correctRate >= 50) return 'Medium';
        return 'Hard';
    }
}
