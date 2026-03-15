<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LessonProgress extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lesson_progress';

    protected $fillable = [
        'user_id',
        'lesson_id',
        'course_id',
        'is_completed',
        'watch_time_seconds',
        'completed_at',
        // Video-specific
        'video_resume_position',
        'video_total_duration',
        'video_watched_percentage',
        'video_play_count',
        // Quiz-specific
        'quiz_attempts_count',
        'quiz_best_score',
        'quiz_latest_score',
        'quiz_passed',
        // Assignment-specific
        'assignment_status',
        'assignment_score',
        // PDF-specific
        'pdf_view_duration_seconds',
        'pdf_pages_viewed',
        'pdf_total_pages',
        // General
        'time_spent_seconds',
        'interaction_count',
        'last_accessed_at',
        'first_accessed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_completed'              => 'boolean',
            'watch_time_seconds'        => 'integer',
            'completed_at'              => 'datetime',
            'video_resume_position'     => 'integer',
            'video_total_duration'      => 'integer',
            'video_watched_percentage'  => 'decimal:2',
            'video_play_count'          => 'integer',
            'quiz_attempts_count'       => 'integer',
            'quiz_best_score'           => 'decimal:2',
            'quiz_latest_score'         => 'decimal:2',
            'quiz_passed'               => 'boolean',
            'assignment_score'          => 'decimal:2',
            'pdf_view_duration_seconds' => 'integer',
            'pdf_pages_viewed'          => 'integer',
            'pdf_total_pages'           => 'integer',
            'time_spent_seconds'        => 'integer',
            'interaction_count'         => 'integer',
            'last_accessed_at'          => 'datetime',
            'first_accessed_at'         => 'datetime',
            'metadata'                  => 'array',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    public function scopeIncomplete($query)
    {
        return $query->where('is_completed', false);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForCourse($query, int $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeRecentlyAccessed($query, int $days = 7)
    {
        return $query->where('last_accessed_at', '>=', now()->subDays($days));
    }

    public function scopeForLessonType($query, string $type)
    {
        return $query->whereHas('lesson', fn($q) => $q->where('type', $type));
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    /**
     * Formatted watch time (e.g., "12m 30s").
     */
    public function getFormattedWatchTimeAttribute(): string
    {
        return $this->formatSeconds($this->watch_time_seconds);
    }

    /**
     * Watch time in minutes (decimal).
     */
    public function getWatchTimeMinutesAttribute(): float
    {
        return round($this->watch_time_seconds / 60, 2);
    }

    /**
     * Formatted total time spent on this lesson.
     */
    public function getFormattedTimeSpentAttribute(): string
    {
        return $this->formatSeconds($this->time_spent_seconds);
    }

    /**
     * Time spent in minutes.
     */
    public function getTimeSpentMinutesAttribute(): float
    {
        return round($this->time_spent_seconds / 60, 2);
    }

    /**
     * Formatted video resume position (e.g., "5:30" or "1:05:30").
     */
    public function getFormattedResumePositionAttribute(): string
    {
        $seconds = $this->video_resume_position;
        $hours = intdiv($seconds, 3600);
        $mins = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $mins, $secs);
        }

        return sprintf('%d:%02d', $mins, $secs);
    }

    /**
     * Video completion percentage.
     */
    public function getVideoCompletionAttribute(): float
    {
        if ($this->video_total_duration <= 0) {
            return 0;
        }

        return min(100, round(($this->watch_time_seconds / $this->video_total_duration) * 100, 1));
    }

    /**
     * PDF read completion percentage.
     */
    public function getPdfCompletionAttribute(): float
    {
        if ($this->pdf_total_pages <= 0) {
            return 0;
        }

        return min(100, round(($this->pdf_pages_viewed / $this->pdf_total_pages) * 100, 1));
    }

    /**
     * Formatted PDF view duration.
     */
    public function getFormattedPdfDurationAttribute(): string
    {
        return $this->formatSeconds($this->pdf_view_duration_seconds);
    }

    /**
     * Get lesson-type-specific completion detail.
     */
    public function getCompletionDetailAttribute(): array
    {
        $lesson = $this->lesson;
        if (!$lesson) {
            return ['status' => $this->is_completed ? 'completed' : 'incomplete'];
        }

        return match ($lesson->type) {
            'video'      => [
                'status'           => $this->is_completed ? 'completed' : 'in-progress',
                'watch_time'       => $this->formatted_watch_time,
                'resume_position'  => $this->formatted_resume_position,
                'video_completion' => $this->video_completion . '%',
                'play_count'       => $this->video_play_count,
            ],
            'quiz'       => [
                'status'     => $this->quiz_passed ? 'passed' : ($this->quiz_attempts_count > 0 ? 'attempted' : 'not-started'),
                'attempts'   => $this->quiz_attempts_count,
                'best_score' => $this->quiz_best_score !== null ? $this->quiz_best_score . '%' : 'N/A',
                'passed'     => $this->quiz_passed,
            ],
            'assignment' => [
                'status' => $this->assignment_status ?? 'not-started',
                'score'  => $this->assignment_score !== null ? $this->assignment_score . '%' : 'N/A',
            ],
            'pdf'        => [
                'status'       => $this->is_completed ? 'completed' : 'in-progress',
                'pages_viewed' => "{$this->pdf_pages_viewed}/{$this->pdf_total_pages}",
                'view_time'    => $this->formatted_pdf_duration,
                'completion'   => $this->pdf_completion . '%',
            ],
            default      => [
                'status'     => $this->is_completed ? 'completed' : 'incomplete',
                'time_spent' => $this->formatted_time_spent,
            ],
        };
    }

    // ──────────────────────────────────────────────
    // Helper
    // ──────────────────────────────────────────────

    protected function formatSeconds(int $totalSeconds): string
    {
        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $seconds = $totalSeconds % 60;

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($minutes > 0 && $seconds > 0) {
            return "{$minutes}m {$seconds}s";
        } elseif ($minutes > 0) {
            return "{$minutes}m";
        }

        return "{$seconds}s";
    }

    // ──────────────────────────────────────────────
    // Events — kept lean; heavy logic in ProgressTracker
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (LessonProgress $progress) {
            if (is_null($progress->first_accessed_at)) {
                $progress->first_accessed_at = now();
            }
            $progress->last_accessed_at = now();
        });

        static::updating(function (LessonProgress $progress) {
            $progress->last_accessed_at = now();

            if ($progress->isDirty('is_completed') && $progress->is_completed && is_null($progress->completed_at)) {
                $progress->completed_at = now();
            }
        });
    }
}
