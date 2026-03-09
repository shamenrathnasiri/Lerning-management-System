<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Quiz extends Model
{
    use HasFactory, SoftDeletes, HasSlug;

    protected $fillable = [
        'course_id',
        'lesson_id',
        'title',
        'slug',
        'description',
        'quiz_type',
        'time_limit_mode',
        'time_limit_minutes',
        'per_question_time_seconds',
        'max_attempts',
        'pass_percentage',
        'shuffle_questions',
        'randomize_options',
        'answer_visibility',
        'show_answers_after_attempts',
        'navigation_mode',
        'instructions',
        'settings',
        'show_correct_answers',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'quiz_type' => 'string',
            'time_limit_mode' => 'string',
            'time_limit_minutes' => 'integer',
            'per_question_time_seconds' => 'integer',
            'max_attempts' => 'integer',
            'pass_percentage' => 'decimal:2',
            'shuffle_questions' => 'boolean',
            'randomize_options' => 'boolean',
            'answer_visibility' => 'string',
            'show_answers_after_attempts' => 'integer',
            'navigation_mode' => 'string',
            'settings' => 'array',
            'show_correct_answers' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug');
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /**
     * Course this quiz belongs to.
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Lesson this quiz is associated with (optional).
     */
    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Questions in this quiz.
     */
    public function questions()
    {
        return $this->hasMany(Question::class)->orderBy('sort_order');
    }

    /**
     * Attempts on this quiz.
     */
    public function attempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Scope to published quizzes.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope to timed quizzes.
     */
    public function scopeTimed($query)
    {
        return $query->whereNotNull('time_limit_minutes');
    }

    /**
     * Scope to quizzes with unlimited attempts.
     */
    public function scopeUnlimitedAttempts($query)
    {
        return $query->whereNull('max_attempts');
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    /**
     * Get the total number of questions.
     */
    public function getQuestionsCountAttribute(): int
    {
        return $this->questions()->count();
    }

    /**
     * Get the total points possible.
     */
    public function getTotalPointsAttribute(): int
    {
        return $this->questions()->sum('points');
    }

    /**
     * Get the formatted time limit (e.g., "30 minutes" or "No limit").
     */
    public function getFormattedTimeLimitAttribute(): string
    {
        if (!$this->time_limit_minutes) {
            return 'No time limit';
        }

        if ($this->time_limit_minutes >= 60) {
            $hours = intdiv($this->time_limit_minutes, 60);
            $minutes = $this->time_limit_minutes % 60;
            return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
        }

        return "{$this->time_limit_minutes} minutes";
    }

    /**
     * Get the average score of all attempts.
     */
    public function getAverageScoreAttribute(): float
    {
        return round($this->attempts()->avg('percentage') ?? 0, 1);
    }

    /**
     * Get the pass rate percentage.
     */
    public function getPassRateAttribute(): float
    {
        $totalAttempts = $this->attempts()->whereNotNull('completed_at')->count();

        if ($totalAttempts === 0) {
            return 0;
        }

        $passedAttempts = $this->attempts()
            ->where('passed', true)
            ->whereNotNull('completed_at')
            ->count();

        return round(($passedAttempts / $totalAttempts) * 100, 1);
    }

    /**
     * Check if a user has remaining attempts.
     */
    public function hasRemainingAttempts(int $userId): bool
    {
        if (is_null($this->max_attempts)) {
            return true;
        }

        return $this->attempts()
            ->where('user_id', $userId)
            ->count() < $this->max_attempts;
    }

    // ──────────────────────────────────────────────
    // Events
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::deleting(function (Quiz $quiz) {
            if ($quiz->isForceDeleting()) {
                $quiz->questions()->forceDelete();
                $quiz->attempts()->forceDelete();
            }
        });
    }
}
