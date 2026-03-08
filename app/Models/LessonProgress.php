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
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'watch_time_seconds' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /**
     * The user who made this progress.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The lesson this progress belongs to.
     */
    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * The course this progress belongs to.
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Scope to completed lessons.
     */
    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    /**
     * Scope to incomplete lessons.
     */
    public function scopeIncomplete($query)
    {
        return $query->where('is_completed', false);
    }

    /**
     * Scope for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for a specific course.
     */
    public function scopeForCourse($query, int $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    /**
     * Get the watch time formatted as minutes and seconds (e.g., "12m 30s").
     */
    public function getFormattedWatchTimeAttribute(): string
    {
        $minutes = intdiv($this->watch_time_seconds, 60);
        $seconds = $this->watch_time_seconds % 60;

        if ($minutes > 0 && $seconds > 0) {
            return "{$minutes}m {$seconds}s";
        } elseif ($minutes > 0) {
            return "{$minutes}m";
        }

        return "{$seconds}s";
    }

    /**
     * Get the watch time in minutes (decimal).
     */
    public function getWatchTimeMinutesAttribute(): float
    {
        return round($this->watch_time_seconds / 60, 2);
    }

    // ──────────────────────────────────────────────
    // Events
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::updating(function (LessonProgress $progress) {
            // Set completed_at when marked as completed
            if ($progress->isDirty('is_completed') && $progress->is_completed && is_null($progress->completed_at)) {
                $progress->completed_at = now();
            }
        });

        static::saved(function (LessonProgress $progress) {
            // Recalculate enrollment progress_percentage when lesson progress is updated
            if ($progress->isDirty('is_completed')) {
                $enrollment = Enrollment::where('user_id', $progress->user_id)
                    ->where('course_id', $progress->course_id)
                    ->first();

                if ($enrollment) {
                    $totalLessons = Lesson::where('course_id', $progress->course_id)
                        ->where('is_published', true)
                        ->count();

                    $completedLessons = LessonProgress::where('user_id', $progress->user_id)
                        ->where('course_id', $progress->course_id)
                        ->where('is_completed', true)
                        ->count();

                    $enrollment->update([
                        'progress_percentage' => $totalLessons > 0
                            ? round(($completedLessons / $totalLessons) * 100, 2)
                            : 0,
                    ]);
                }
            }
        });
    }
}
