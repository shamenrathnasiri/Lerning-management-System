<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_attempt_id',
        'question_id',
        'question_option_id',
        'answer_text',
        'is_correct',
        'points_earned',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'points_earned' => 'integer',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /**
     * Quiz attempt this answer belongs to.
     */
    public function attempt()
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    /**
     * The question this answer is for.
     */
    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * The selected option (for multiple choice / true-false).
     */
    public function selectedOption()
    {
        return $this->belongsTo(QuestionOption::class, 'question_option_id');
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Scope to correct answers.
     */
    public function scopeCorrect($query)
    {
        return $query->where('is_correct', true);
    }

    /**
     * Scope to incorrect answers.
     */
    public function scopeIncorrect($query)
    {
        return $query->where('is_correct', false);
    }

    // ──────────────────────────────────────────────
    // Events
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (QuizAnswer $answer) {
            // Auto-grade for objective question types
            if ($answer->question_option_id && is_null($answer->is_correct)) {
                $option = QuestionOption::find($answer->question_option_id);
                if ($option) {
                    $answer->is_correct = $option->is_correct;
                    $answer->points_earned = $option->is_correct
                        ? $answer->question->points
                        : 0;
                }
            }
        });
    }
}
