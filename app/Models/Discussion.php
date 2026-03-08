<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Discussion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'course_id',
        'lesson_id',
        'title',
        'body',
        'is_pinned',
        'is_resolved',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_resolved' => 'boolean',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /**
     * User who created this discussion.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Alias for user — the author.
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Course this discussion belongs to.
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Lesson this discussion is about (optional).
     */
    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Replies to this discussion.
     */
    public function replies()
    {
        return $this->hasMany(DiscussionReply::class)->orderBy('created_at');
    }

    /**
     * Latest replies to this discussion.
     */
    public function latestReplies()
    {
        return $this->hasMany(DiscussionReply::class)->latest()->limit(5);
    }

    /**
     * Best answer for this discussion.
     */
    public function bestAnswer()
    {
        return $this->hasOne(DiscussionReply::class)->where('is_best_answer', true);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Scope to pinned discussions.
     */
    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    /**
     * Scope to resolved discussions.
     */
    public function scopeResolved($query)
    {
        return $query->where('is_resolved', true);
    }

    /**
     * Scope to unresolved discussions.
     */
    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    /**
     * Scope to recent discussions.
     */
    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at');
    }

    /**
     * Scope to popular discussions (most replies).
     */
    public function scopePopular($query)
    {
        return $query->withCount('replies')
                     ->orderByDesc('replies_count');
    }

    /**
     * Scope ordered with pinned first.
     */
    public function scopePinnedFirst($query)
    {
        return $query->orderByDesc('is_pinned')
                     ->orderByDesc('created_at');
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    /**
     * Get the total number of replies.
     */
    public function getRepliesCountAttribute(): int
    {
        return $this->replies()->count();
    }

    /**
     * Get the relative time since creation.
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get a short excerpt of the body.
     */
    public function getExcerptAttribute(): string
    {
        return \Str::limit(strip_tags($this->body), 150);
    }

    /**
     * Get the last activity timestamp (latest reply or creation).
     */
    public function getLastActivityAttribute()
    {
        $latestReply = $this->replies()->latest()->first();

        return $latestReply ? $latestReply->created_at : $this->created_at;
    }
}
