<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enrollment extends Model
{
    use HasFactory, SoftDeletes;

    // ──────────────────────────────────────────────
    // Constants
    // ──────────────────────────────────────────────

    // Enrollment types
    const TYPE_SELF    = 'self';
    const TYPE_PAID    = 'paid';
    const TYPE_BULK    = 'bulk';
    const TYPE_COUPON  = 'coupon';
    const TYPE_GROUP   = 'group';
    const TYPE_WAITLIST = 'waitlist';

    // Enrollment statuses
    const STATUS_PENDING     = 'pending';
    const STATUS_ACTIVE      = 'active';
    const STATUS_IN_PROGRESS = 'in-progress';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_EXPIRED     = 'expired';
    const STATUS_CANCELLED   = 'cancelled';

    const TYPES = [
        self::TYPE_SELF,
        self::TYPE_PAID,
        self::TYPE_BULK,
        self::TYPE_COUPON,
        self::TYPE_GROUP,
        self::TYPE_WAITLIST,
    ];

    const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACTIVE,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_EXPIRED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'user_id',
        'course_id',
        'enrollment_type',
        'status',
        'payment_id',
        'coupon_id',
        'enrolled_by',
        'transferred_from',
        'group_enrollment_id',
        'progress_percentage',
        'amount_paid',
        'refund_amount',
        'enrolled_at',
        'completed_at',
        'expires_at',
        'refunded_at',
        'cancelled_at',
        'last_activity_at',
        'cancellation_reason',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'progress_percentage' => 'decimal:2',
            'amount_paid'         => 'decimal:2',
            'refund_amount'       => 'decimal:2',
            'enrolled_at'         => 'datetime',
            'completed_at'        => 'datetime',
            'expires_at'          => 'datetime',
            'refunded_at'         => 'datetime',
            'cancelled_at'        => 'datetime',
            'last_activity_at'    => 'datetime',
            'metadata'            => 'array',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /**
     * The enrolled user (student).
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
     * The enrolled course.
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * The payment associated with this enrollment (for paid enrollments).
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * The coupon used for this enrollment.
     */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * The user who enrolled the student (admin/instructor for bulk enrollments).
     */
    public function enrolledByUser()
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    /**
     * The enrollment this was transferred from.
     */
    public function transferredFromEnrollment()
    {
        return $this->belongsTo(Enrollment::class, 'transferred_from');
    }

    /**
     * Enrollments transferred from this one.
     */
    public function transfers()
    {
        return $this->hasMany(Enrollment::class, 'transferred_from');
    }

    /**
     * The group enrollment this belongs to.
     */
    public function groupEnrollment()
    {
        return $this->belongsTo(GroupEnrollment::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Scope to active enrollments.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to in-progress enrollments.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    /**
     * Scope to completed enrollments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope to pending enrollments.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to expired enrollments.
     */
    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    /**
     * Scope to cancelled enrollments.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    /**
     * Scope to non-expired enrollments.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope to enrollments with access (active or in-progress, not expired).
     */
    public function scopeWithAccess($query)
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_IN_PROGRESS])
                     ->notExpired();
    }

    /**
     * Scope to recent enrollments.
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('enrolled_at', '>=', now()->subDays($days));
    }

    /**
     * Scope by enrollment type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('enrollment_type', $type);
    }

    /**
     * Scope to inactive students (no activity in N days).
     */
    public function scopeInactive($query, int $days = 14)
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_IN_PROGRESS])
                     ->where(function ($q) use ($days) {
                         $q->whereNull('last_activity_at')
                           ->orWhere('last_activity_at', '<', now()->subDays($days));
                     });
    }

    /**
     * Scope to enrollments expiring soon.
     */
    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->whereNotNull('expires_at')
                     ->where('expires_at', '>', now())
                     ->where('expires_at', '<=', now()->addDays($days))
                     ->whereNotIn('status', [self::STATUS_EXPIRED, self::STATUS_CANCELLED, self::STATUS_COMPLETED]);
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    /**
     * Get the formatted progress (e.g., "75.5%").
     */
    public function getFormattedProgressAttribute(): string
    {
        return number_format($this->progress_percentage, 1) . '%';
    }

    /**
     * Check if this enrollment is active.
     */
    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_IN_PROGRESS]);
    }

    /**
     * Check if this enrollment is completed.
     */
    public function getIsCompletedAttribute(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if this enrollment has expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->status === self::STATUS_EXPIRED
            || ($this->expires_at && $this->expires_at->isPast());
    }

    /**
     * Check if this enrollment is pending (payment not completed).
     */
    public function getIsPendingAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if this enrollment is cancelled.
     */
    public function getIsCancelledAttribute(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Check if this enrollment has course access.
     */
    public function getHasAccessAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_IN_PROGRESS])
            && !$this->is_expired;
    }

    /**
     * Check if this enrollment can be refunded.
     */
    public function getIsRefundableAttribute(): bool
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return false;
        }
        if ($this->refund_amount > 0) {
            return false;
        }
        if ($this->amount_paid <= 0) {
            return false;
        }
        // Refundable within 30 days of enrollment
        return $this->enrolled_at && $this->enrolled_at->diffInDays(now()) <= 30;
    }

    /**
     * Check if this enrollment is transferable.
     */
    public function getIsTransferableAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_IN_PROGRESS])
            && !$this->is_expired
            && $this->progress_percentage < 25; // Can only transfer if less than 25% complete
    }

    /**
     * Get the number of days since enrollment.
     */
    public function getDaysEnrolledAttribute(): int
    {
        return $this->enrolled_at ? $this->enrolled_at->diffInDays(now()) : 0;
    }

    /**
     * Get the number of days until expiration.
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expires_at || $this->expires_at->isPast()) {
            return null;
        }

        return now()->diffInDays($this->expires_at);
    }

    /**
     * Get the formatted enrollment type.
     */
    public function getEnrollmentTypeDisplayAttribute(): string
    {
        return match ($this->enrollment_type) {
            self::TYPE_SELF    => 'Self Enrollment',
            self::TYPE_PAID    => 'Paid Enrollment',
            self::TYPE_BULK    => 'Bulk Enrollment',
            self::TYPE_COUPON  => 'Coupon Enrollment',
            self::TYPE_GROUP   => 'Group Enrollment',
            self::TYPE_WAITLIST => 'Waitlist Enrollment',
            default            => ucfirst($this->enrollment_type),
        };
    }

    /**
     * Get the status display with badge color.
     */
    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            self::STATUS_PENDING     => ['label' => 'Pending',     'color' => 'yellow'],
            self::STATUS_ACTIVE      => ['label' => 'Active',      'color' => 'green'],
            self::STATUS_IN_PROGRESS => ['label' => 'In Progress', 'color' => 'blue'],
            self::STATUS_COMPLETED   => ['label' => 'Completed',   'color' => 'emerald'],
            self::STATUS_EXPIRED     => ['label' => 'Expired',     'color' => 'gray'],
            self::STATUS_CANCELLED   => ['label' => 'Cancelled',   'color' => 'red'],
            default                  => ['label' => ucfirst($this->status), 'color' => 'gray'],
        };
    }

    // ──────────────────────────────────────────────
    // Helper Methods
    // ──────────────────────────────────────────────

    /**
     * Activate the enrollment.
     */
    public function activate(): self
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'enrolled_at' => $this->enrolled_at ?? now(),
            'last_activity_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark the enrollment as in-progress.
     */
    public function markInProgress(): self
    {
        if ($this->status === self::STATUS_ACTIVE) {
            $this->update([
                'status' => self::STATUS_IN_PROGRESS,
                'last_activity_at' => now(),
            ]);
        }

        return $this;
    }

    /**
     * Mark the enrollment as completed.
     */
    public function markCompleted(): self
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'progress_percentage' => 100,
            'completed_at' => now(),
            'last_activity_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark the enrollment as expired.
     */
    public function markExpired(): self
    {
        $this->update([
            'status' => self::STATUS_EXPIRED,
        ]);

        return $this;
    }

    /**
     * Record student activity.
     */
    public function recordActivity(): self
    {
        $this->update(['last_activity_at' => now()]);

        // Auto-transition from active to in-progress
        if ($this->status === self::STATUS_ACTIVE && $this->progress_percentage > 0) {
            $this->markInProgress();
        }

        return $this;
    }

    // ──────────────────────────────────────────────
    // Events
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Enrollment $enrollment) {
            if (is_null($enrollment->enrolled_at)) {
                $enrollment->enrolled_at = now();
            }
            if (is_null($enrollment->enrollment_type)) {
                $enrollment->enrollment_type = self::TYPE_SELF;
            }
        });

        static::updating(function (Enrollment $enrollment) {
            // Auto-set completed_at when status changes to completed
            if ($enrollment->isDirty('status') && $enrollment->status === self::STATUS_COMPLETED && is_null($enrollment->completed_at)) {
                $enrollment->completed_at = now();
                $enrollment->progress_percentage = 100;
            }

            // Auto-set cancelled_at when status changes to cancelled
            if ($enrollment->isDirty('status') && $enrollment->status === self::STATUS_CANCELLED && is_null($enrollment->cancelled_at)) {
                $enrollment->cancelled_at = now();
            }
        });
    }
}
