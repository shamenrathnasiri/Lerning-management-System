<?php

namespace App\Exports;

use App\Models\QuizAttempt;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class QuizAttemptsExport implements WithMultipleSheets
{
    use Exportable;

    protected int $quizId;

    public function __construct(int $quizId)
    {
        $this->quizId = $quizId;
    }

    public function sheets(): array
    {
        return [
            'Attempts' => new AttemptsSheet($this->quizId),
            'Summary' => new SummarySheet($this->quizId),
            'Question Analysis' => new QuestionAnalysisSheet($this->quizId),
        ];
    }
}

// ── Sheet 1: All Attempts ──────────────────────────────────

class AttemptsSheet implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected int $quizId;

    public function __construct(int $quizId)
    {
        $this->quizId = $quizId;
    }

    public function query()
    {
        return QuizAttempt::query()
            ->where('quiz_id', $this->quizId)
            ->whereNotNull('completed_at')
            ->with('user:id,name,email', 'quiz:id,title')
            ->orderBy('completed_at', 'desc');
    }

    public function headings(): array
    {
        return [
            'Attempt ID',
            'Student Name',
            'Student Email',
            'Score',
            'Total Points',
            'Percentage',
            'Passed',
            'Started At',
            'Completed At',
            'Duration (minutes)',
            'Correct Answers',
            'Total Questions',
        ];
    }

    public function map($attempt): array
    {
        $duration = $attempt->started_at && $attempt->completed_at
            ? round($attempt->started_at->diffInMinutes($attempt->completed_at), 1)
            : 0;

        $correctCount = $attempt->answers()->where('is_correct', true)->count();
        $totalQuestions = $attempt->quiz->questions()->count();

        return [
            $attempt->id,
            $attempt->user->name ?? 'N/A',
            $attempt->user->email ?? 'N/A',
            $attempt->score,
            $attempt->quiz->total_points ?? 0,
            round($attempt->percentage, 1) . '%',
            $attempt->passed ? 'Yes' : 'No',
            $attempt->started_at?->format('Y-m-d H:i:s'),
            $attempt->completed_at?->format('Y-m-d H:i:s'),
            $duration,
            $correctCount,
            $totalQuestions,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4F46E5'],
                ],
            ],
        ];
    }
}

// ── Sheet 2: Summary Statistics ─────────────────────────────

class SummarySheet implements \Maatwebsite\Excel\Concerns\FromArray, WithStyles, ShouldAutoSize
{
    protected int $quizId;

    public function __construct(int $quizId)
    {
        $this->quizId = $quizId;
    }

    public function array(): array
    {
        $quiz = Quiz::find($this->quizId);
        $attempts = QuizAttempt::where('quiz_id', $this->quizId)
            ->whereNotNull('completed_at')
            ->get();

        $total = $attempts->count();
        $passed = $attempts->where('passed', true)->count();
        $avg = round($attempts->avg('percentage') ?? 0, 1);
        $median = $this->median($attempts->pluck('percentage')->sort()->values()->toArray());
        $highest = round($attempts->max('percentage') ?? 0, 1);
        $lowest = round($attempts->min('percentage') ?? 0, 1);
        $uniqueStudents = $attempts->unique('user_id')->count();

        $avgDuration = $attempts->avg(function ($a) {
            return $a->started_at && $a->completed_at
                ? round($a->started_at->diffInMinutes($a->completed_at), 1) : 0;
        });

        return [
            ['Quiz Analytics Summary Report'],
            ['Quiz Title', $quiz->title ?? 'N/A'],
            ['Generated', now()->format('Y-m-d H:i:s')],
            [''],
            ['Metric', 'Value'],
            ['Total Attempts', $total],
            ['Unique Students', $uniqueStudents],
            ['Average Score', $avg . '%'],
            ['Median Score', $median . '%'],
            ['Highest Score', $highest . '%'],
            ['Lowest Score', $lowest . '%'],
            ['Pass Count', $passed],
            ['Fail Count', $total - $passed],
            ['Pass Rate', $total > 0 ? round(($passed / $total) * 100, 1) . '%' : '0%'],
            ['Average Duration (min)', round($avgDuration, 1)],
            ['Pass Percentage Required', ($quiz->pass_percentage ?? 0) . '%'],
        ];
    }

    private function median(array $values): float
    {
        if (empty($values)) return 0;
        $count = count($values);
        $mid = intdiv($count, 2);
        return $count % 2 === 0
            ? round(($values[$mid - 1] + $values[$mid]) / 2, 1)
            : round($values[$mid], 1);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => 'FF4F46E5']]],
            5 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4F46E5'],
                ],
            ],
        ];
    }
}

// ── Sheet 3: Question Analysis ──────────────────────────────

class QuestionAnalysisSheet implements \Maatwebsite\Excel\Concerns\FromArray, WithStyles, ShouldAutoSize
{
    protected int $quizId;

    public function __construct(int $quizId)
    {
        $this->quizId = $quizId;
    }

    public function array(): array
    {
        $quiz = Quiz::with('questions')->find($this->quizId);
        if (!$quiz) return [['No quiz found']];

        $rows = [['#', 'Question', 'Type', 'Points', 'Total Answers', 'Correct', 'Incorrect', 'Correct Rate', 'Difficulty']];

        foreach ($quiz->questions()->orderBy('sort_order')->get() as $idx => $question) {
            $totalAnswers = QuizAnswer::where('question_id', $question->id)
                ->whereHas('attempt', fn ($q) => $q->whereNotNull('completed_at'))
                ->count();

            $correctAnswers = QuizAnswer::where('question_id', $question->id)
                ->where('is_correct', true)
                ->whereHas('attempt', fn ($q) => $q->whereNotNull('completed_at'))
                ->count();

            $correctRate = $totalAnswers > 0 ? round(($correctAnswers / $totalAnswers) * 100, 1) : 0;
            $difficulty = $correctRate >= 80 ? 'Easy' : ($correctRate >= 50 ? 'Medium' : 'Hard');

            $rows[] = [
                $idx + 1,
                strip_tags(mb_substr($question->question_text, 0, 100)),
                ucfirst(str_replace('_', ' ', $question->type)),
                $question->points,
                $totalAnswers,
                $correctAnswers,
                $totalAnswers - $correctAnswers,
                $correctRate . '%',
                $difficulty,
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF059669'],
                ],
            ],
        ];
    }
}
