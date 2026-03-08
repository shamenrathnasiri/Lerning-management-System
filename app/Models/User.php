<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'bio',
        'avatar',
        'expertise',
        'social_links',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'social_links' => 'array',
        ];
    }

    // ──────────────────────────────────────────────
    // Role Helper Methods
    // ──────────────────────────────────────────────

    /**
     * Check if the user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    /**
     * Check if the user is an admin (includes super-admin).
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['admin', 'super-admin']);
    }

    /**
     * Check if the user is an instructor.
     */
    public function isInstructor(): bool
    {
        return $this->hasRole('instructor');
    }

    /**
     * Check if the user is a student.
     */
    public function isStudent(): bool
    {
        return $this->hasRole('student');
    }

    /**
     * Check if the user is at least an instructor (instructor, admin, or super-admin).
     */
    public function isAtLeastInstructor(): bool
    {
        return $this->hasAnyRole(['instructor', 'admin', 'super-admin']);
    }

    /**
     * Check if the user can manage a specific course (owner or admin).
     */
    public function canManageCourse(Course $course): bool
    {
        return $this->isAdmin() || $this->id === $course->instructor_id;
    }

    /**
     * Check if the user is enrolled in a given course.
     */
    public function isEnrolledIn(Course $course): bool
    {
        return $this->enrollments()
            ->where('course_id', $course->id)
            ->where('status', '!=', 'cancelled')
            ->exists();
    }

    /**
     * Check if the user has completed a given course.
     */
    public function hasCompletedCourse(Course $course): bool
    {
        return $this->enrollments()
            ->where('course_id', $course->id)
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * Check if the user owns a given course (is the instructor).
     */
    public function ownsCourse(Course $course): bool
    {
        return $this->id === $course->instructor_id;
    }

    /**
     * Get the user's primary role name.
     */
    public function getPrimaryRoleAttribute(): string
    {
        $role = $this->roles->first();
        return $role ? $role->name : 'unassigned';
    }

    /**
     * Get the user's role display name (formatted).
     */
    public function getRoleDisplayNameAttribute(): string
    {
        return match ($this->primary_role) {
            'super-admin' => 'Super Admin',
            'admin'       => 'Administrator',
            'instructor'  => 'Instructor',
            'student'     => 'Student',
            default       => 'Unassigned',
        };
    }

    /**
     * Get the user's role badge color (for UI).
     */
    public function getRoleBadgeColorAttribute(): string
    {
        return match ($this->primary_role) {
            'super-admin' => 'red',
            'admin'       => 'purple',
            'instructor'  => 'blue',
            'student'     => 'green',
            default       => 'gray',
        };
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /**
     * Courses taught by this user (instructor).
     */
    public function taughtCourses()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    /**
     * Enrollments for this user.
     */
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Courses the user is enrolled in.
     */
    public function enrolledCourses()
    {
        return $this->belongsToMany(Course::class, 'enrollments')
                    ->withPivot('status', 'progress_percentage', 'enrolled_at', 'completed_at', 'expires_at')
                    ->withTimestamps();
    }

    /**
     * Lesson progress records.
     */
    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    /**
     * Quiz attempts by this user.
     */
    public function quizAttempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * Assignment submissions by this user.
     */
    public function assignmentSubmissions()
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    /**
     * Submissions graded by this user (instructor).
     */
    public function gradedSubmissions()
    {
        return $this->hasMany(AssignmentSubmission::class, 'graded_by');
    }

    /**
     * Certificates earned by this user.
     */
    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * Reviews written by this user.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Discussions started by this user.
     */
    public function discussions()
    {
        return $this->hasMany(Discussion::class);
    }

    /**
     * Discussion replies by this user.
     */
    public function discussionReplies()
    {
        return $this->hasMany(DiscussionReply::class);
    }

    /**
     * Badges earned by this user.
     */
    public function badges()
    {
        return $this->belongsToMany(Badge::class, 'user_badges')
                    ->withPivot('earned_at')
                    ->withTimestamps();
    }

    /**
     * User badge pivot records.
     */
    public function userBadges()
    {
        return $this->hasMany(UserBadge::class);
    }

    /**
     * Payments made by this user.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Wishlisted courses.
     */
    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Courses in user's wishlist.
     */
    public function wishlistedCourses()
    {
        return $this->belongsToMany(Course::class, 'wishlists')
                    ->withTimestamps();
    }

    /**
     * Announcements authored by this user.
     */
    public function announcements()
    {
        return $this->hasMany(Announcement::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Scope to only super admins.
     */
    public function scopeSuperAdmins($query)
    {
        return $query->role('super-admin');
    }

    /**
     * Scope to only admins (admin + super-admin).
     */
    public function scopeAdmins($query)
    {
        return $query->role(['admin', 'super-admin']);
    }

    /**
     * Scope to only instructors.
     */
    public function scopeInstructors($query)
    {
        return $query->role('instructor');
    }

    /**
     * Scope to only students.
     */
    public function scopeStudents($query)
    {
        return $query->role('student');
    }

    /**
     * Scope to verified users.
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    /**
     * Scope users with a specific permission.
     */
    public function scopeWithPermission($query, string $permission)
    {
        return $query->permission($permission);
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    /**
     * Get the user's avatar URL with a fallback.
     */
    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar
            ? asset('storage/' . $this->avatar)
            : 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=6366f1&color=fff';
    }

    /**
     * Get total number of enrolled courses.
     */
    public function getEnrolledCoursesCountAttribute(): int
    {
        return $this->enrollments()->count();
    }

    // ──────────────────────────────────────────────
    // Events
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->username)) {
                $user->username = \Str::slug($user->name) . '-' . \Str::random(5);
            }
        });

        static::deleting(function (User $user) {
            if ($user->isForceDeleting()) {
                $user->taughtCourses()->forceDelete();
                $user->enrollments()->forceDelete();
            }
        });
    }
}
