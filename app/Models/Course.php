<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Course extends Model
{
    use HasFactory, SoftDeletes, HasSlug;

    protected $fillable = [
        'instructor_id',
        'category_id',
        'title',
        'slug',
        'subtitle',
        'description',
        'requirements',
        'what_you_will_learn',
        'target_audience',
        'thumbnail',
        'intro_video',
        'level',
        'language',
        'price',
        'discount_price',
        'duration_hours',
        'status',
        'is_featured',
        'is_free',
        'published_at',
        'scheduled_publish_at',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'certificate_template',
        'wizard_step',
    ];

    protected function casts(): array
    {
        return [
            'requirements' => 'array',
            'what_you_will_learn' => 'array',
            'target_audience' => 'array',
            'price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'duration_hours' => 'integer',
            'is_featured' => 'boolean',
            'is_free' => 'boolean',
            'published_at' => 'datetime',
            'scheduled_publish_at' => 'datetime',
            'wizard_step' => 'integer',
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
     * Instructor who created this course.
     */
    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * Category this course belongs to.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Tags associated with this course.
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'course_tag');
    }

    /**
     * Sections of this course.
     */
    public function sections()
    {
        return $this->hasMany(Section::class)->orderBy('sort_order');
    }

    /**
     * All lessons in this course.
     */
    public function lessons()
    {
        return $this->hasMany(Lesson::class)->orderBy('sort_order');
    }

    /**
     * Enrollments for this course.
     */
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Students enrolled in this course.
     */
    public function students()
    {
        return $this->belongsToMany(User::class, 'enrollments')
                    ->withPivot('status', 'progress_percentage', 'enrolled_at', 'completed_at')
                    ->withTimestamps();
    }

    /**
     * Lesson progress records for this course.
     */
    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    /**
     * Quizzes in this course.
     */
    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    /**
     * Assignments in this course.
     */
    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Certificates issued for this course.
     */
    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * Reviews for this course.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Snapshot history for this course.
     */
    public function versions()
    {
        return $this->hasMany(CourseVersion::class)->orderByDesc('created_at');
    }

    /**
     * Approved reviews for this course.
     */
    public function approvedReviews()
    {
        return $this->hasMany(Review::class)->where('is_approved', true);
    }

    /**
     * Discussions for this course.
     */
    public function discussions()
    {
        return $this->hasMany(Discussion::class);
    }

    /**
     * Payments for this course.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Wishlists containing this course.
     */
    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Announcements for this course.
     */
    public function announcements()
    {
        return $this->hasMany(Announcement::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Scope to published courses only.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope to draft courses.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope to pending review courses.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to free courses.
     */
    public function scopeFree($query)
    {
        return $query->where('is_free', true);
    }

    /**
     * Scope to paid courses.
     */
    public function scopePaid($query)
    {
        return $query->where('is_free', false)->where('price', '>', 0);
    }

    /**
     * Scope to featured courses.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope to popular courses (by enrollment count).
     */
    public function scopePopular($query)
    {
        return $query->withCount('enrollments')
                     ->orderByDesc('enrollments_count');
    }

    /**
     * Scope to trending courses (recently popular).
     */
    public function scopeTrending($query, $days = 30)
    {
        return $query->withCount(['enrollments' => function ($q) use ($days) {
            $q->where('enrollments.created_at', '>=', now()->subDays($days));
        }])->orderByDesc('enrollments_count');
    }

    /**
     * Scope to top rated courses.
     */
    public function scopeTopRated($query)
    {
        return $query->withAvg('approvedReviews', 'rating')
                     ->orderByDesc('approved_reviews_avg_rating');
    }

    /**
     * Scope by level.
     */
    public function scopeLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope by language.
     */
    public function scopeLanguage($query, string $language)
    {
        return $query->where('language', $language);
    }

    /**
     * Scope by price range.
     */
    public function scopePriceRange($query, float $min, float $max)
    {
        return $query->whereBetween('price', [$min, $max]);
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    /**
     * Get the formatted price (e.g., "$29.99").
     */
    public function getFormattedPriceAttribute(): string
    {
        if ($this->is_free) {
            return 'Free';
        }

        return '$' . number_format((float) $this->price, 2);
    }

    /**
     * Get the formatted discount price.
     */
    public function getFormattedDiscountPriceAttribute(): ?string
    {
        if (!$this->discount_price) {
            return null;
        }

        return '$' . number_format((float) $this->discount_price, 2);
    }

    /**
     * Get the effective price (discount price if available, otherwise regular price).
     */
    public function getEffectivePriceAttribute(): float
    {
        return (float) ($this->discount_price ?? $this->price ?? 0);
    }

    /**
     * Get the discount percentage.
     */
    public function getDiscountPercentageAttribute(): ?int
    {
        if (!$this->discount_price || $this->price <= 0) {
            return null;
        }

        return (int) round((($this->price - $this->discount_price) / $this->price) * 100);
    }

    /**
     * Get the total duration from all lessons (in minutes).
     */
    public function getTotalDurationMinutesAttribute(): int
    {
        return $this->lessons()->sum('duration_minutes');
    }

    /**
     * Get the total duration formatted (e.g., "12h 30m").
     */
    public function getFormattedDurationAttribute(): string
    {
        $totalMinutes = $this->total_duration_minutes;
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        }

        return "{$minutes}m";
    }

    /**
     * Get the average review rating.
     */
    public function getAverageRatingAttribute(): float
    {
        return round($this->approvedReviews()->avg('rating') ?? 0, 1);
    }

    /**
     * Get the total number of enrolled students.
     */
    public function getStudentsCountAttribute(): int
    {
        return $this->enrollments()->count();
    }

    /**
     * Get the total number of lessons.
     */
    public function getLessonsCountAttribute(): int
    {
        return $this->lessons()->count();
    }

    /**
     * Get the thumbnail URL with a fallback.
     */
    public function getThumbnailUrlAttribute(): string
    {
        return $this->thumbnail
            ? asset('storage/' . $this->thumbnail)
            : asset('images/course-placeholder.png');
    }

    // ──────────────────────────────────────────────
    // Events
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Course $course) {
            if (is_null($course->status)) {
                $course->status = 'draft';
            }
        });

        static::updating(function (Course $course) {
            // Set published_at when course is first published
            if ($course->isDirty('status') && $course->status === 'published' && is_null($course->published_at)) {
                $course->published_at = now();
            }
        });
    }
}
