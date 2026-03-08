<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscussionReply extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'discussion_id',
        'user_id',
        'parent_id',
        'body',
        'is_best_answer',
    ];

    protected function casts(): array
    {
        return [
            'is_best_answer' => 'boolean',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /**
     * Discussion this reply belongs to.
     */
    public function discussion()
    {
        return $this->belongsTo(Discussion::class);
    }

    /**
     * User who wrote this reply.
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
     * Parent reply (for nested replies).
     */
    public function parent()
    {
        return $this->belongsTo(DiscussionReply::class, 'parent_id');
    }

    /**
     * Child replies (nested replies).
     */
    public function children()
    {
        return $this->hasMany(DiscussionReply::class, 'parent_id')
                    ->orderBy('created_at');
    }

    /**
     * Recursive children (nested tree).
     */
    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Scope to best answers.
     */
    public function scopeBestAnswer($query)
    {
        return $query->where('is_best_answer', true);
    }

    /**
     * Scope to root-level replies (no parent).
     */
    public function scopeRootLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to recent replies.
     */
    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at');
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

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
        return \Str::limit(strip_tags($this->body), 100);
    }

    /**
     * Check if this reply has child replies.
     */
    public function getHasChildrenAttribute(): bool
    {
        return $this->children()->exists();
    }

    // ──────────────────────────────────────────────
    // Events
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::updating(function (DiscussionReply $reply) {
            // When marking as best answer, unmark any existing best answer for the same discussion
            if ($reply->isDirty('is_best_answer') && $reply->is_best_answer) {
                static::where('discussion_id', $reply->discussion_id)
                    ->where('id', '!=', $reply->id)
                    ->where('is_best_answer', true)
                    ->update(['is_best_answer' => false]);

                // Also mark the discussion as resolved
                $reply->discussion->update(['is_resolved' => true]);
            }
        });
    }
}
