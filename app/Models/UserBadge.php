<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserBadge extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'badge_id', 'earned_at',
    ];

    protected function casts(): array
    {
        return ['earned_at' => 'datetime'];
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function badge()
    {
        return $this->belongsTo(Badge::class);
    }

    // Scopes
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('earned_at', '>=', now()->subDays($days));
    }

    // Accessors
    public function getFormattedEarnedDateAttribute(): string
    {
        return $this->earned_at?->format('M d, Y') ?? 'N/A';
    }

    // Events
    protected static function booted(): void
    {
        static::creating(function (UserBadge $ub) {
            if (is_null($ub->earned_at)) {
                $ub->earned_at = now();
            }
        });
    }
}
