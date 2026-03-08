<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'quiz_id',
        'type',
        'question_text',
        'explanation',
        'points',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /**
     * Quiz this question belongs to.
     */
    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * Options for this question (multiple choice / true-false).
     */
    public function options()
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort_order');
    }

    /**
     * Correct options for this question.
     */
    public function correctOptions()
    {
        return $this->hasMany(QuestionOption::class)->where('is_correct', true);
    }

    /**
     * Answers given for this question.
     */
    public function answers()
    {
        return $this->hasMany(QuizAnswer::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Scope ordered by sort_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Scope by question type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to multiple choice questions.
     */
    public function scopeMultipleChoice($query)
    {
        return $query->where('type', 'multiple_choice');
    }

    /**
     * Scope to true/false questions.
     */
    public function scopeTrueFalse($query)
    {
        return $query->where('type', 'true_false');
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    /**
     * Check if this is a multiple choice question.
     */
    public function getIsMultipleChoiceAttribute(): bool
    {
        return $this->type === 'multiple_choice';
    }

    /**
     * Check if this is a true/false question.
     */
    public function getIsTrueFalseAttribute(): bool
    {
        return $this->type === 'true_false';
    }

    /**
     * Check if this question requires subjective grading.
     */
    public function getRequiresManualGradingAttribute(): bool
    {
        return in_array($this->type, ['short_answer', 'essay']);
    }

    /**
     * Get the number of options.
     */
    public function getOptionsCountAttribute(): int
    {
        return $this->options()->count();
    }

    // ──────────────────────────────────────────────
    // Events
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Question $question) {
            if (is_null($question->sort_order)) {
                $question->sort_order = static::where('quiz_id', $question->quiz_id)->max('sort_order') + 1;
            }
        });

        static::deleting(function (Question $question) {
            if ($question->isForceDeleting()) {
                $question->options()->delete();
                $question->answers()->delete();
            }
        });
    }
}
