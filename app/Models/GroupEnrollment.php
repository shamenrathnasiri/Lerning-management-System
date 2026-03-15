<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroupEnrollment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'team_name',
        'course_id',
        'created_by',
        'max_members',
        'total_amount',
        'status',
        'expires_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'max_members'  => 'integer',
            'expires_at'   => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function members()
    {
        return $this->hasManyThrough(User::class, Enrollment::class, 'group_enrollment_id', 'id', 'id', 'user_id');
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    public function getMemberCountAttribute(): int
    {
        return $this->enrollments()->count();
    }

    public function getHasCapacityAttribute(): bool
    {
        if (is_null($this->max_members)) {
            return true;
        }

        return $this->member_count < $this->max_members;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
