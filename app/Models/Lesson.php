<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Lesson extends Model
{
    use HasFactory, SoftDeletes, HasSlug;

    protected $fillable = [
        'section_id',
        'course_id',
        'title',
        'slug',
        'type',
        'content',
        'video_url',
        'video_provider',
        'duration_minutes',
        'sort_order',
        'is_free_preview',
        'is_published',
        'resources',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'sort_order' => 'integer',
            'is_free_preview' => 'boolean',
            'is_published' => 'boolean',
            'resources' => 'array',
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
     * Section this lesson belongs to.
     */
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Course this lesson belongs to.
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Progress records for this lesson.
     */
    public function progress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    /**
     * Quizzes attached to this lesson.
     */
    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    /**
     * Assignments attached to this lesson.
     */
    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Discussions for this lesson.
     */
    public function discussions()
    {
        return $this->hasMany(Discussion::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Scope to published lessons.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope to free preview lessons.
     */
    public function scopeFreePreview($query)
    {
        return $query->where('is_free_preview', true);
    }

    /**
     * Scope ordered by sort_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Scope by lesson type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to video lessons only.
     */
    public function scopeVideos($query)
    {
        return $query->where('type', 'video');
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    /**
     * Get the duration formatted as hours and minutes (e.g., "1h 30m" or "45m").
     */
    public function getFormattedDurationAttribute(): string
    {
        $hours = intdiv($this->duration_minutes, 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        }

        return "{$minutes}m";
    }

    /**
     * Get the duration in hours (decimal).
     */
    public function getDurationInHoursAttribute(): float
    {
        return round($this->duration_minutes / 60, 2);
    }

    /**
     * Check if this is a video lesson.
     */
    public function getIsVideoAttribute(): bool
    {
        return $this->type === 'video';
    }

    /**
     * Get the number of resources/attachments.
     */
    public function getResourcesCountAttribute(): int
    {
        return is_array($this->resources) ? count($this->resources) : 0;
    }

    // ──────────────────────────────────────────────
    // Events
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Lesson $lesson) {
            if (is_null($lesson->sort_order)) {
                $lesson->sort_order = static::where('section_id', $lesson->section_id)->max('sort_order') + 1;
            }
        });

        static::saved(function (Lesson $lesson) {
            // Update course total duration when a lesson is saved
            if ($lesson->isDirty('duration_minutes')) {
                $lesson->course->update([
                    'duration_hours' => (int) ceil($lesson->course->lessons()->sum('duration_minutes') / 60),
                ]);
            }
        });
    }
}
