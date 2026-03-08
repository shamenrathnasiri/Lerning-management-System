<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssignmentSubmission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'assignment_id',
        'user_id',
        'content',
        'attachments',
        'score',
        'feedback',
        'graded_by',
        'status',
        'submitted_at',
        'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'score' => 'decimal:2',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /**
     * Assignment this submission belongs to.
     */
    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * Student who submitted this.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Alias for user — the student.
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Instructor who graded this submission.
     */
    public function grader()
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Scope to submitted (pending grading) submissions.
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Scope to graded submissions.
     */
    public function scopeGraded($query)
    {
        return $query->where('status', 'graded');
    }

    /**
     * Scope to returned submissions.
     */
    public function scopeReturned($query)
    {
        return $query->where('status', 'returned');
    }

    /**
     * Scope to resubmitted submissions.
     */
    public function scopeResubmitted($query)
    {
        return $query->where('status', 'resubmitted');
    }

    /**
     * Scope to pending review (submitted or resubmitted).
     */
    public function scopePendingReview($query)
    {
        return $query->whereIn('status', ['submitted', 'resubmitted']);
    }

    /**
     * Scope for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    /**
     * Get the formatted score as a percentage of max score.
     */
    public function getScorePercentageAttribute(): ?float
    {
        if (is_null($this->score) || !$this->assignment) {
            return null;
        }

        $maxScore = $this->assignment->max_score;

        return $maxScore > 0 ? round(($this->score / $maxScore) * 100, 1) : 0;
    }

    /**
     * Get the formatted score (e.g., "85 / 100").
     */
    public function getFormattedScoreAttribute(): ?string
    {
        if (is_null($this->score)) {
            return null;
        }

        return number_format($this->score, 1) . ' / ' . $this->assignment->max_score;
    }

    /**
     * Check if this submission has been graded.
     */
    public function getIsGradedAttribute(): bool
    {
        return $this->status === 'graded';
    }

    /**
     * Check if this submission is pending review.
     */
    public function getIsPendingAttribute(): bool
    {
        return in_array($this->status, ['submitted', 'resubmitted']);
    }

    /**
     * Get the number of attachments.
     */
    public function getAttachmentsCountAttribute(): int
    {
        return is_array($this->attachments) ? count($this->attachments) : 0;
    }

    /**
     * Check if the submission was late.
     */
    public function getIsLateAttribute(): bool
    {
        if (!$this->assignment->due_date || !$this->submitted_at) {
            return false;
        }

        return $this->submitted_at->isAfter($this->assignment->due_date);
    }

    // ──────────────────────────────────────────────
    // Events
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (AssignmentSubmission $submission) {
            if (is_null($submission->submitted_at)) {
                $submission->submitted_at = now();
            }
        });

        static::updating(function (AssignmentSubmission $submission) {
            // Set graded_at when status changes to graded
            if ($submission->isDirty('status') && $submission->status === 'graded' && is_null($submission->graded_at)) {
                $submission->graded_at = now();
            }

            // Reset submitted_at on resubmission
            if ($submission->isDirty('status') && $submission->status === 'resubmitted') {
                $submission->submitted_at = now();
            }
        });
    }
}
