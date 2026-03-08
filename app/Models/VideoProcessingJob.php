<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoProcessingJob extends Model
{
    use HasFactory;

    const STATUS_PENDING    = 'pending';
    const STATUS_QUEUED     = 'queued';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_FAILED     = 'failed';

    protected $fillable = [
        'lesson_id',
        'original_filename',
        'original_size',
        's3_key',
        'status',
        'thumbnails',
        'qualities',
        'watermark_applied',
        'error_message',
        'queued_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'original_size'    => 'integer',
            'thumbnails'       => 'array',
            'qualities'        => 'array',
            'watermark_applied'=> 'boolean',
            'queued_at'        => 'datetime',
            'started_at'       => 'datetime',
            'completed_at'     => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopePending($query)    { return $query->where('status', self::STATUS_PENDING); }
    public function scopeProcessing($query) { return $query->where('status', self::STATUS_PROCESSING); }
    public function scopeCompleted($query)  { return $query->where('status', self::STATUS_COMPLETED); }
    public function scopeFailed($query)     { return $query->where('status', self::STATUS_FAILED); }

    // ── State Helpers ────────────────────────────────────────────────────────

    public function isCompleted(): bool { return $this->status === self::STATUS_COMPLETED; }
    public function hasFailed(): bool   { return $this->status === self::STATUS_FAILED; }
    public function isRunning(): bool   { return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_PROCESSING]); }

    public function markAsQueued(): void
    {
        $this->update(['status' => self::STATUS_QUEUED, 'queued_at' => now()]);
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => self::STATUS_PROCESSING, 'started_at' => now()]);
    }

    public function markAsCompleted(array $qualities = [], array $thumbnails = []): void
    {
        $this->update([
            'status'       => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'qualities'    => $qualities  ?: $this->qualities,
            'thumbnails'   => $thumbnails ?: $this->thumbnails,
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status'        => self::STATUS_FAILED,
            'error_message' => $error,
        ]);
    }

    // ── Accessors ────────────────────────────────────────────────────────────

    public function getElapsedSecondsAttribute(): ?int
    {
        if (! $this->started_at) {
            return null;
        }
        $end = $this->completed_at ?? now();
        return (int) $this->started_at->diffInSeconds($end);
    }
}
