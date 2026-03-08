<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuizAttempt extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'quiz_id',
        'user_id',
        'score',
        'percentage',
        'passed',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'percentage' => 'decimal:2',
            'passed' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /**
     * Quiz this attempt belongs to.
     */
    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * User who made this attempt.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Answers in this attempt.
     */
    public function answers()
    {
        return $this->hasMany(QuizAnswer::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Scope to passed attempts.
     */
    public function scopePassed($query)
    {
        return $query->where('passed', true);
    }

    /**
     * Scope to failed attempts.
     */
    public function scopeFailed($query)
    {
        return $query->where('passed', false)
                     ->whereNotNull('completed_at');
    }

    /**
     * Scope to completed attempts.
     */
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('completed_at');
    }

    /**
     * Scope to in-progress attempts.
     */
    public function scopeInProgress($query)
    {
        return $query->whereNull('completed_at');
    }

    /**
     * Scope for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to recent attempts.
     */
    public function scopeRecent($query)
    {
        return $query->orderByDesc('started_at');
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    /**
     * Get the formatted score (e.g., "85.00%").
     */
    public function getFormattedPercentageAttribute(): string
    {
        return number_format($this->percentage ?? 0, 1) . '%';
    }

    /**
     * Check if the attempt is still in progress.
     */
    public function getIsInProgressAttribute(): bool
    {
        return is_null($this->completed_at);
    }

    /**
     * Get the duration of the attempt in minutes.
     */
    public function getDurationMinutesAttribute(): ?int
    {
        if (!$this->completed_at || !$this->started_at) {
            return null;
        }

        return $this->started_at->diffInMinutes($this->completed_at);
    }

    /**
     * Get the formatted duration (e.g., "15m 30s").
     */
    public function getFormattedDurationAttribute(): string
    {
        if (!$this->completed_at || !$this->started_at) {
            return 'In progress';
        }

        $totalSeconds = $this->started_at->diffInSeconds($this->completed_at);
        $minutes = intdiv($totalSeconds, 60);
        $seconds = $totalSeconds % 60;

        if ($minutes > 0 && $seconds > 0) {
            return "{$minutes}m {$seconds}s";
        } elseif ($minutes > 0) {
            return "{$minutes}m";
        }

        return "{$seconds}s";
    }

    /**
     * Get the number of correct answers.
     */
    public function getCorrectAnswersCountAttribute(): int
    {
        return $this->answers()->where('is_correct', true)->count();
    }

    /**
     * Get the total number of answers.
     */
    public function getTotalAnswersCountAttribute(): int
    {
        return $this->answers()->count();
    }

    // ──────────────────────────────────────────────
    // Events
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (QuizAttempt $attempt) {
            if (is_null($attempt->started_at)) {
                $attempt->started_at = now();
            }
        });

        static::updating(function (QuizAttempt $attempt) {
            // Calculate score and pass/fail when completing
            if ($attempt->isDirty('completed_at') && $attempt->completed_at) {
                $totalPoints = $attempt->quiz->questions()->sum('points');
                $earnedPoints = $attempt->answers()->sum('points_earned');

                $attempt->score = $earnedPoints;
                $attempt->percentage = $totalPoints > 0
                    ? round(($earnedPoints / $totalPoints) * 100, 2)
                    : 0;
                $attempt->passed = $attempt->percentage >= $attempt->quiz->pass_percentage;
            }
        });
    }
}
