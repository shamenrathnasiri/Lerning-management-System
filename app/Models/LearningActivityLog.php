<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearningActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'lesson_id',
        'activity_type',
        'duration_seconds',
        'activity_date',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'activity_date'    => 'date',
            'metadata'         => 'array',
        ];
    }

    // ──── Constants ──────────────────────────────────────────────
    const TYPE_LESSON_VIEW       = 'lesson_view';
    const TYPE_VIDEO_WATCH       = 'video_watch';
    const TYPE_QUIZ_ATTEMPT      = 'quiz_attempt';
    const TYPE_ASSIGNMENT_SUBMIT = 'assignment_submit';
    const TYPE_PDF_READ          = 'pdf_read';
    const TYPE_NOTE_ADDED        = 'note_added';
    const TYPE_DISCUSSION_POST   = 'discussion_post';

    // ──── Relationships ─────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    // ──── Scopes ────────────────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForCourse($query, int $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeBetweenDates($query, string $from, string $to)
    {
        return $query->whereBetween('activity_date', [$from, $to]);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('activity_type', $type);
    }

    // ──── Accessors ─────────────────────────────────────────────

    public function getFormattedDurationAttribute(): string
    {
        $minutes = intdiv($this->duration_seconds, 60);
        $seconds = $this->duration_seconds % 60;

        if ($minutes > 0 && $seconds > 0) {
            return "{$minutes}m {$seconds}s";
        } elseif ($minutes > 0) {
            return "{$minutes}m";
        }
        return "{$seconds}s";
    }
}
