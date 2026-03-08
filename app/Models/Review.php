<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'course_id',
        'rating',
        'comment',
        'is_approved',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_approved' => 'boolean',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /**
     * User who wrote this review.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Course this review is for.
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Scope to approved reviews.
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope to pending (unapproved) reviews.
     */
    public function scopePending($query)
    {
        return $query->where('is_approved', false);
    }

    /**
     * Scope by minimum rating.
     */
    public function scopeMinRating($query, int $rating)
    {
        return $query->where('rating', '>=', $rating);
    }

    /**
     * Scope to positive reviews (4-5 stars).
     */
    public function scopePositive($query)
    {
        return $query->where('rating', '>=', 4);
    }

    /**
     * Scope to critical reviews (1-2 stars).
     */
    public function scopeCritical($query)
    {
        return $query->where('rating', '<=', 2);
    }

    /**
     * Scope to recent reviews.
     */
    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at');
    }

    /**
     * Scope to reviews with comments.
     */
    public function scopeWithComment($query)
    {
        return $query->whereNotNull('comment')
                     ->where('comment', '!=', '');
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    /**
     * Get the star rating as filled/empty stars (e.g., "★★★★☆").
     */
    public function getStarsAttribute(): string
    {
        return str_repeat('★', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }

    /**
     * Get the relative time since creation (e.g., "2 days ago").
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    // ──────────────────────────────────────────────
    // Events
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Review $review) {
            // Clamp rating to 1-5
            $review->rating = max(1, min(5, $review->rating));
        });

        static::updating(function (Review $review) {
            if ($review->isDirty('rating')) {
                $review->rating = max(1, min(5, $review->rating));
            }
        });
    }
}
