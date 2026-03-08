<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enrollment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'course_id',
        'status',
        'progress_percentage',
        'enrolled_at',
        'completed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'progress_percentage' => 'decimal:2',
            'enrolled_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /**
     * The enrolled user (student).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Alias for user — the student.
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The enrolled course.
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Scope to active enrollments.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to completed enrollments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to expired enrollments.
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    /**
     * Scope to cancelled enrollments.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope to non-expired enrollments.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope to recent enrollments.
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('enrolled_at', '>=', now()->subDays($days));
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    /**
     * Get the formatted progress (e.g., "75.50%").
     */
    public function getFormattedProgressAttribute(): string
    {
        return number_format($this->progress_percentage, 1) . '%';
    }

    /**
     * Check if this enrollment is active.
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if this enrollment is completed.
     */
    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if this enrollment has expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->status === 'expired'
            || ($this->expires_at && $this->expires_at->isPast());
    }

    /**
     * Get the number of days since enrollment.
     */
    public function getDaysEnrolledAttribute(): int
    {
        return $this->enrolled_at ? $this->enrolled_at->diffInDays(now()) : 0;
    }

    // ──────────────────────────────────────────────
    // Events
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Enrollment $enrollment) {
            if (is_null($enrollment->enrolled_at)) {
                $enrollment->enrolled_at = now();
            }
        });

        static::updating(function (Enrollment $enrollment) {
            // Auto-set completed_at when status changes to completed
            if ($enrollment->isDirty('status') && $enrollment->status === 'completed' && is_null($enrollment->completed_at)) {
                $enrollment->completed_at = now();
                $enrollment->progress_percentage = 100;
            }
        });
    }
}
