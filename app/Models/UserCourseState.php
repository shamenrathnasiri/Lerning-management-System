<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCourseState extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'last_lesson_id',
        'next_lesson_id',
        'last_video_position',
        'last_accessed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'last_video_position' => 'integer',
            'last_accessed_at'    => 'datetime',
            'metadata'            => 'array',
        ];
    }

    // ──── Relationships ─────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function lastLesson()
    {
        return $this->belongsTo(Lesson::class, 'last_lesson_id');
    }

    public function nextLesson()
    {
        return $this->belongsTo(Lesson::class, 'next_lesson_id');
    }

    // ──── Scopes ────────────────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecentlyAccessed($query, int $limit = 5)
    {
        return $query->orderByDesc('last_accessed_at')->limit($limit);
    }
}
