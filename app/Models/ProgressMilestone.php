<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgressMilestone extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'milestone',
        'reached_at',
    ];

    protected function casts(): array
    {
        return [
            'milestone'  => 'integer',
            'reached_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForCourse($query, int $courseId)
    {
        return $query->where('course_id', $courseId);
    }
}
