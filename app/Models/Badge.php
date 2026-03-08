<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Badge extends Model
{
    use HasFactory, SoftDeletes, HasSlug;

    protected $fillable = [
        'name', 'slug', 'description', 'icon',
        'criteria_type', 'criteria_value',
    ];

    protected function casts(): array
    {
        return ['criteria_value' => 'integer'];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // Relationships
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_badges')
                    ->withPivot('earned_at')->withTimestamps();
    }

    public function userBadges()
    {
        return $this->hasMany(UserBadge::class);
    }

    // Scopes
    public function scopeOfCriteria($query, string $type)
    {
        return $query->where('criteria_type', $type);
    }

    public function scopePopular($query)
    {
        return $query->withCount('users')->orderByDesc('users_count');
    }

    public function scopeRare($query)
    {
        return $query->withCount('users')->orderBy('users_count');
    }

    // Accessors
    public function getEarnedCountAttribute(): int
    {
        return $this->users()->count();
    }

    public function getCriteriaDescriptionAttribute(): string
    {
        $map = [
            'courses_completed' => "Complete {$this->criteria_value} courses",
            'quizzes_passed' => "Pass {$this->criteria_value} quizzes",
            'streak_days' => "Maintain a {$this->criteria_value}-day streak",
        ];
        return $map[$this->criteria_type] ?? "{$this->criteria_type}: {$this->criteria_value}";
    }

    public function getIconUrlAttribute(): string
    {
        return $this->icon ? asset('storage/' . $this->icon) : asset('images/badge-default.png');
    }
}
