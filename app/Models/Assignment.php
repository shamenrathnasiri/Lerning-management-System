<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Assignment extends Model
{
    use HasFactory, SoftDeletes, HasSlug;

    protected $fillable = [
        'course_id',
        'lesson_id',
        'title',
        'slug',
        'description',
        'instructions',
        'max_score',
        'due_date',
        'max_file_size_mb',
        'allowed_file_types',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'max_score' => 'integer',
            'due_date' => 'datetime',
            'max_file_size_mb' => 'integer',
            'allowed_file_types' => 'array',
            'is_published' => 'boolean',
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
     * Course this assignment belongs to.
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Lesson this assignment is associated with (optional).
     */
    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Submissions for this assignment.
     */
    public function submissions()
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    /**
     * Graded submissions.
     */
    public function gradedSubmissions()
    {
        return $this->hasMany(AssignmentSubmission::class)->where('status', 'graded');
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Scope to published assignments.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope to assignments with upcoming due dates.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('due_date', '>', now())
                     ->orderBy('due_date');
    }

    /**
     * Scope to overdue assignments.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                     ->whereNotNull('due_date');
    }

    /**
     * Scope to assignments without a due date.
     */
    public function scopeNoDueDate($query)
    {
        return $query->whereNull('due_date');
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    /**
     * Check if the assignment is past due.
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date && $this->due_date->isPast();
    }

    /**
     * Get the days remaining until due date.
     */
    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->due_date) {
            return null;
        }

        return max(0, now()->diffInDays($this->due_date, false));
    }

    /**
     * Get the formatted due date (e.g., "Mar 15, 2026 at 11:59 PM").
     */
    public function getFormattedDueDateAttribute(): ?string
    {
        return $this->due_date?->format('M d, Y \a\t h:i A');
    }

    /**
     * Get the total number of submissions.
     */
    public function getSubmissionsCountAttribute(): int
    {
        return $this->submissions()->count();
    }

    /**
     * Get the average score of graded submissions.
     */
    public function getAverageScoreAttribute(): float
    {
        return round($this->gradedSubmissions()->avg('score') ?? 0, 1);
    }

    /**
     * Get the allowed file types as a comma-separated string.
     */
    public function getAllowedFileTypesStringAttribute(): string
    {
        if (!$this->allowed_file_types) {
            return 'All file types';
        }

        return implode(', ', $this->allowed_file_types);
    }

    // ──────────────────────────────────────────────
    // Events
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::deleting(function (Assignment $assignment) {
            if ($assignment->isForceDeleting()) {
                $assignment->submissions()->forceDelete();
            }
        });
    }
}
