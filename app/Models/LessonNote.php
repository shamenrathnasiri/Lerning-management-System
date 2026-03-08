<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LessonNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'lesson_id',
        'course_id',
        'content',
        'timestamp_seconds',
        'color',
        'is_private',
    ];

    protected function casts(): array
    {
        return [
            'timestamp_seconds' => 'integer',
            'is_private'        => 'boolean',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

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

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }

    // ── Accessors ────────────────────────────────────────────────────────────

    /**
     * Format timestamp_seconds as m:ss or h:mm:ss.
     */
    public function getFormattedTimestampAttribute(): ?string
    {
        if (is_null($this->timestamp_seconds)) {
            return null;
        }

        $h = intdiv($this->timestamp_seconds, 3600);
        $m = intdiv($this->timestamp_seconds % 3600, 60);
        $s = $this->timestamp_seconds % 60;

        return $h > 0
            ? sprintf('%d:%02d:%02d', $h, $m, $s)
            : sprintf('%d:%02d', $m, $s);
    }
}
