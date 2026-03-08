<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Tag extends Model
{
    use HasFactory, SoftDeletes, HasSlug;

    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
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
     * Courses associated with this tag.
     */
    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_tag')
                    ->withTimestamps();
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Scope popular tags by course count.
     */
    public function scopePopular($query)
    {
        return $query->withCount('courses')
                     ->orderByDesc('courses_count');
    }

    /**
     * Scope trending tags (most used in recent courses).
     */
    public function scopeTrending($query, $days = 30)
    {
        return $query->withCount(['courses' => function ($q) use ($days) {
            $q->where('courses.created_at', '>=', now()->subDays($days));
        }])->orderByDesc('courses_count');
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    /**
     * Get the total number of courses with this tag.
     */
    public function getCoursesCountAttribute(): int
    {
        return $this->courses()->count();
    }
}
