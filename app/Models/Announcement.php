<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'course_id', 'title', 'body',
        'audience', 'is_published', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeSiteWide($query)
    {
        return $query->whereNull('course_id');
    }

    public function scopeForCourse($query, int $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeForAudience($query, string $audience)
    {
        return $query->where(function ($q) use ($audience) {
            $q->where('audience', $audience)->orWhere('audience', 'all');
        });
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('published_at')->orderByDesc('created_at');
    }

    // Accessors
    public function getIsSiteWideAttribute(): bool
    {
        return is_null($this->course_id);
    }

    public function getExcerptAttribute(): string
    {
        return \Str::limit(strip_tags($this->body), 200);
    }

    public function getFormattedDateAttribute(): string
    {
        return ($this->published_at ?? $this->created_at)->format('M d, Y');
    }

    public function getTimeAgoAttribute(): string
    {
        return ($this->published_at ?? $this->created_at)->diffForHumans();
    }

    // Events
    protected static function booted(): void
    {
        static::updating(function (Announcement $a) {
            if ($a->isDirty('is_published') && $a->is_published && is_null($a->published_at)) {
                $a->published_at = now();
            }
        });
    }
}
