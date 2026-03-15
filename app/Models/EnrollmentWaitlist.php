<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnrollmentWaitlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'position',
        'status',
        'notified_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'position'    => 'integer',
            'notified_at' => 'datetime',
            'expires_at'  => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting');
    }

    public function scopeNotified($query)
    {
        return $query->where('status', 'notified');
    }

    public function scopeForCourse($query, int $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getIsWaitingAttribute(): bool
    {
        return $this->status === 'waiting';
    }
}
