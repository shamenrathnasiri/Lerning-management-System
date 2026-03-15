<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentWaitlist;
use App\Models\GroupEnrollment;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\EnrollmentWelcomeNotification;
use App\Notifications\CertificateAvailableNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnrollmentService
{
    // ──────────────────────────────────────────────────────────────────
    // Core Enrollment Methods
    // ──────────────────────────────────────────────────────────────────

    /**
     * Process enrollment based on the enrollment type.
     */
    public function enroll(User $user, Course $course, string $type = 'self', array $data = []): array
    {
        // Check if user is already enrolled
        $existingEnrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereNotIn('status', [Enrollment::STATUS_CANCELLED, Enrollment::STATUS_EXPIRED])
            ->first();

        if ($existingEnrollment) {
            return [
                'success' => false,
                'message' => 'User is already enrolled in this course.',
                'enrollment' => $existingEnrollment,
            ];
        }

        // Check course capacity
        if ($this->isCourseFull($course) && $type !== 'waitlist') {
            if ($type !== 'bulk') { // Admins can override capacity for bulk
                return $this->addToWaitlist($user, $course);
            }
        }

        return match ($type) {
            'self'     => $this->selfEnroll($user, $course, $data),
            'paid'     => $this->paidEnroll($user, $course, $data),
            'bulk'     => $this->bulkEnroll($user, $course, $data),
            'coupon'   => $this->couponEnroll($user, $course, $data),
            'group'    => $this->groupEnroll($user, $course, $data),
            'waitlist' => $this->addToWaitlist($user, $course),
            default    => ['success' => false, 'message' => 'Invalid enrollment type.'],
        };
    }

    /**
     * Self enrollment for free courses.
     */
    protected function selfEnroll(User $user, Course $course, array $data = []): array
    {
        if (!$course->is_free) {
            return [
                'success' => false,
                'message' => 'This course is not free. Please use paid enrollment.',
            ];
        }

        $enrollment = Enrollment::create([
            'user_id'         => $user->id,
            'course_id'       => $course->id,
            'enrollment_type' => Enrollment::TYPE_SELF,
            'status'          => Enrollment::STATUS_ACTIVE,
            'enrolled_at'     => now(),
            'expires_at'      => $course->enrollment_duration_days
                ? now()->addDays($course->enrollment_duration_days)
                : null,
            'notes'           => $data['notes'] ?? null,
        ]);

        $this->sendWelcomeNotification($enrollment);

        return [
            'success'    => true,
            'message'    => 'Successfully enrolled in the course.',
            'enrollment' => $enrollment->load('course:id,title,slug'),
        ];
    }

    /**
     * Paid enrollment with payment processing.
     */
    protected function paidEnroll(User $user, Course $course, array $data = []): array
    {
        return DB::transaction(function () use ($user, $course, $data) {
            $amount = $course->effective_price;
            $discountAmount = 0;
            $couponId = null;

            // Apply coupon if provided
            if (!empty($data['coupon_code'])) {
                $couponResult = $this->applyCoupon($data['coupon_code'], $user, $course);
                if (!$couponResult['success']) {
                    return $couponResult;
                }
                $discountAmount = $couponResult['discount'];
                $couponId = $couponResult['coupon_id'];
                $amount -= $discountAmount;
            }

            // If amount is 0 after discount, treat as free
            if ($amount <= 0) {
                $amount = 0;
            }

            // Create payment record
            $payment = Payment::create([
                'user_id'         => $user->id,
                'course_id'       => $course->id,
                'coupon_id'       => $couponId,
                'amount'          => $course->effective_price,
                'discount_amount' => $discountAmount,
                'currency'        => $data['currency'] ?? 'USD',
                'payment_method'  => $data['payment_method'] ?? null,
                'payment_gateway' => $data['payment_gateway'] ?? null,
                'status'          => 'pending',
            ]);

            // Create enrollment in pending status
            $enrollment = Enrollment::create([
                'user_id'         => $user->id,
                'course_id'       => $course->id,
                'enrollment_type' => Enrollment::TYPE_PAID,
                'status'          => $amount > 0 ? Enrollment::STATUS_PENDING : Enrollment::STATUS_ACTIVE,
                'payment_id'      => $payment->id,
                'coupon_id'       => $couponId,
                'amount_paid'     => $amount,
                'enrolled_at'     => $amount > 0 ? null : now(),
                'expires_at'      => $course->enrollment_duration_days
                    ? now()->addDays($course->enrollment_duration_days)
                    : null,
            ]);

            // If fully discounted, activate immediately
            if ($amount <= 0) {
                $payment->update(['status' => 'completed', 'paid_at' => now()]);
                $enrollment->activate();
                $this->sendWelcomeNotification($enrollment);

                // Increment coupon usage
                if ($couponId) {
                    Coupon::where('id', $couponId)->increment('used_count');
                }
            }

            return [
                'success'    => true,
                'message'    => $amount > 0
                    ? 'Enrollment pending. Please complete payment.'
                    : 'Enrolled successfully with full discount.',
                'enrollment' => $enrollment->load('course:id,title,slug', 'payment'),
                'payment'    => $payment,
                'amount_due' => max(0, $amount),
            ];
        });
    }

    /**
     * Confirm payment and activate enrollment.
     */
    public function confirmPayment(Enrollment $enrollment, array $paymentData = []): array
    {
        if ($enrollment->status !== Enrollment::STATUS_PENDING) {
            return [
                'success' => false,
                'message' => 'This enrollment is not pending payment.',
            ];
        }

        return DB::transaction(function () use ($enrollment, $paymentData) {
            // Update payment
            if ($enrollment->payment) {
                $enrollment->payment->update([
                    'status'           => 'completed',
                    'transaction_id'   => $paymentData['transaction_id'] ?? $enrollment->payment->transaction_id,
                    'payment_method'   => $paymentData['payment_method'] ?? $enrollment->payment->payment_method,
                    'payment_gateway'  => $paymentData['payment_gateway'] ?? $enrollment->payment->payment_gateway,
                    'gateway_response' => $paymentData['gateway_response'] ?? null,
                    'paid_at'          => now(),
                ]);
            }

            // Activate enrollment
            $enrollment->activate();

            // Increment coupon usage
            if ($enrollment->coupon_id) {
                Coupon::where('id', $enrollment->coupon_id)->increment('used_count');
            }

            $this->sendWelcomeNotification($enrollment);

            return [
                'success'    => true,
                'message'    => 'Payment confirmed. Enrollment activated.',
                'enrollment' => $enrollment->fresh()->load('course:id,title,slug'),
            ];
        });
    }

    /**
     * Bulk enrollment by admin/instructor.
     */
    protected function bulkEnroll(User $enrolledByUser, Course $course, array $data = []): array
    {
        $userIds = $data['user_ids'] ?? [];

        if (empty($userIds)) {
            return ['success' => false, 'message' => 'No users specified for bulk enrollment.'];
        }

        $results = ['enrolled' => [], 'skipped' => [], 'errors' => []];

        DB::transaction(function () use ($userIds, $course, $enrolledByUser, $data, &$results) {
            foreach ($userIds as $userId) {
                $user = User::find($userId);
                if (!$user) {
                    $results['errors'][] = ['user_id' => $userId, 'reason' => 'User not found.'];
                    continue;
                }

                // Check if already enrolled
                $exists = Enrollment::where('user_id', $userId)
                    ->where('course_id', $course->id)
                    ->whereNotIn('status', [Enrollment::STATUS_CANCELLED, Enrollment::STATUS_EXPIRED])
                    ->exists();

                if ($exists) {
                    $results['skipped'][] = ['user_id' => $userId, 'reason' => 'Already enrolled.'];
                    continue;
                }

                $enrollment = Enrollment::create([
                    'user_id'         => $userId,
                    'course_id'       => $course->id,
                    'enrollment_type' => Enrollment::TYPE_BULK,
                    'status'          => Enrollment::STATUS_ACTIVE,
                    'enrolled_by'     => $enrolledByUser->id,
                    'enrolled_at'     => now(),
                    'expires_at'      => $course->enrollment_duration_days
                        ? now()->addDays($course->enrollment_duration_days)
                        : null,
                    'notes'           => $data['notes'] ?? 'Bulk enrolled by ' . $enrolledByUser->name,
                ]);

                $results['enrolled'][] = $enrollment;
                $this->sendWelcomeNotification($enrollment);
            }
        });

        return [
            'success'  => true,
            'message'  => count($results['enrolled']) . ' user(s) enrolled successfully.',
            'enrolled' => count($results['enrolled']),
            'skipped'  => count($results['skipped']),
            'errors'   => count($results['errors']),
            'details'  => $results,
        ];
    }

    /**
     * Coupon-based enrollment.
     */
    protected function couponEnroll(User $user, Course $course, array $data = []): array
    {
        $couponCode = $data['coupon_code'] ?? null;

        if (!$couponCode) {
            return ['success' => false, 'message' => 'Coupon code is required.'];
        }

        $couponResult = $this->applyCoupon($couponCode, $user, $course);
        if (!$couponResult['success']) {
            return $couponResult;
        }

        $coursePrice = $course->effective_price;
        $discount = $couponResult['discount'];
        $finalAmount = max(0, $coursePrice - $discount);

        return DB::transaction(function () use ($user, $course, $couponResult, $finalAmount, $coursePrice, $discount) {
            // Create payment record
            $payment = null;
            if ($coursePrice > 0) {
                $payment = Payment::create([
                    'user_id'         => $user->id,
                    'course_id'       => $course->id,
                    'coupon_id'       => $couponResult['coupon_id'],
                    'amount'          => $coursePrice,
                    'discount_amount' => $discount,
                    'status'          => $finalAmount > 0 ? 'pending' : 'completed',
                    'paid_at'         => $finalAmount <= 0 ? now() : null,
                ]);
            }

            $enrollment = Enrollment::create([
                'user_id'         => $user->id,
                'course_id'       => $course->id,
                'enrollment_type' => Enrollment::TYPE_COUPON,
                'status'          => $finalAmount > 0 ? Enrollment::STATUS_PENDING : Enrollment::STATUS_ACTIVE,
                'payment_id'      => $payment?->id,
                'coupon_id'       => $couponResult['coupon_id'],
                'amount_paid'     => $finalAmount,
                'enrolled_at'     => $finalAmount <= 0 ? now() : null,
                'expires_at'      => $course->enrollment_duration_days
                    ? now()->addDays($course->enrollment_duration_days)
                    : null,
            ]);

            // Increment coupon usage
            Coupon::where('id', $couponResult['coupon_id'])->increment('used_count');

            if ($finalAmount <= 0) {
                $this->sendWelcomeNotification($enrollment);
            }

            return [
                'success'    => true,
                'message'    => $finalAmount > 0
                    ? "Coupon applied. Remaining amount: \${$finalAmount}. Please complete payment."
                    : 'Coupon applied. Enrolled for free!',
                'enrollment' => $enrollment->load('course:id,title,slug'),
                'discount'   => $discount,
                'amount_due' => $finalAmount,
            ];
        });
    }

    /**
     * Group/team enrollment.
     */
    protected function groupEnroll(User $creator, Course $course, array $data = []): array
    {
        $memberIds = $data['member_ids'] ?? [];
        $teamName  = $data['team_name'] ?? null;
        $groupName = $data['group_name'] ?? ($teamName ?? $creator->name . "'s Team");

        if (empty($memberIds)) {
            return ['success' => false, 'message' => 'No team members specified.'];
        }

        // Include creator if not already in list
        if (!in_array($creator->id, $memberIds)) {
            array_unshift($memberIds, $creator->id);
        }

        return DB::transaction(function () use ($creator, $course, $memberIds, $teamName, $groupName, $data) {
            // Create group enrollment record
            $groupEnrollment = GroupEnrollment::create([
                'name'        => $groupName,
                'team_name'   => $teamName,
                'course_id'   => $course->id,
                'created_by'  => $creator->id,
                'max_members' => $data['max_members'] ?? null,
                'total_amount' => $course->effective_price * count($memberIds),
                'status'      => 'active',
                'expires_at'  => $course->enrollment_duration_days
                    ? now()->addDays($course->enrollment_duration_days)
                    : null,
                'notes'       => $data['notes'] ?? null,
            ]);

            $enrolled = [];
            $skipped  = [];

            foreach ($memberIds as $memberId) {
                $user = User::find($memberId);
                if (!$user) {
                    $skipped[] = ['user_id' => $memberId, 'reason' => 'User not found.'];
                    continue;
                }

                $exists = Enrollment::where('user_id', $memberId)
                    ->where('course_id', $course->id)
                    ->whereNotIn('status', [Enrollment::STATUS_CANCELLED, Enrollment::STATUS_EXPIRED])
                    ->exists();

                if ($exists) {
                    $skipped[] = ['user_id' => $memberId, 'reason' => 'Already enrolled.'];
                    continue;
                }

                $enrollment = Enrollment::create([
                    'user_id'             => $memberId,
                    'course_id'           => $course->id,
                    'enrollment_type'     => Enrollment::TYPE_GROUP,
                    'status'              => Enrollment::STATUS_ACTIVE,
                    'enrolled_by'         => $creator->id,
                    'group_enrollment_id' => $groupEnrollment->id,
                    'enrolled_at'         => now(),
                    'expires_at'          => $groupEnrollment->expires_at,
                    'notes'               => "Group enrollment: {$groupName}",
                ]);

                $enrolled[] = $enrollment;
                $this->sendWelcomeNotification($enrollment);
            }

            return [
                'success'          => true,
                'message'          => count($enrolled) . ' team member(s) enrolled.',
                'group_enrollment' => $groupEnrollment->load('enrollments'),
                'enrolled_count'   => count($enrolled),
                'skipped_count'    => count($skipped),
                'skipped_details'  => $skipped,
            ];
        });
    }

    // ──────────────────────────────────────────────────────────────────
    // Waitlist Management
    // ──────────────────────────────────────────────────────────────────

    /**
     * Add a user to the course waitlist.
     */
    public function addToWaitlist(User $user, Course $course): array
    {
        $existing = EnrollmentWaitlist::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereIn('status', ['waiting', 'notified'])
            ->first();

        if ($existing) {
            return [
                'success'  => false,
                'message'  => 'Already on the waitlist for this course.',
                'waitlist' => $existing,
            ];
        }

        $position = EnrollmentWaitlist::where('course_id', $course->id)
            ->where('status', 'waiting')
            ->max('position') + 1;

        $waitlistEntry = EnrollmentWaitlist::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'position'  => $position,
            'status'    => 'waiting',
        ]);

        return [
            'success'  => true,
            'message'  => "Added to waitlist at position {$position}.",
            'waitlist' => $waitlistEntry,
            'position' => $position,
        ];
    }

    /**
     * Process waitlist when a spot opens up.
     */
    public function processWaitlist(Course $course): array
    {
        $processed = [];

        $waitlistEntries = EnrollmentWaitlist::where('course_id', $course->id)
            ->where('status', 'waiting')
            ->orderBy('position')
            ->get();

        foreach ($waitlistEntries as $entry) {
            if ($this->isCourseFull($course)) {
                break;
            }

            $entry->update([
                'status'      => 'notified',
                'notified_at' => now(),
                'expires_at'  => now()->addHours(48), // 48 hours to accept
            ]);

            // Notify user
            $entry->user->notify(new \App\Notifications\WaitlistSpotAvailableNotification($entry));

            $processed[] = $entry;
        }

        return [
            'success'   => true,
            'processed' => count($processed),
            'message'   => count($processed) . ' waitlist notification(s) sent.',
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // Cancel / Refund / Transfer / Extend
    // ──────────────────────────────────────────────────────────────────

    /**
     * Cancel an enrollment with optional refund.
     */
    public function cancelEnrollment(Enrollment $enrollment, ?string $reason = null, bool $processRefund = true): array
    {
        if ($enrollment->status === Enrollment::STATUS_CANCELLED) {
            return ['success' => false, 'message' => 'Enrollment is already cancelled.'];
        }

        return DB::transaction(function () use ($enrollment, $reason, $processRefund) {
            $refundAmount = 0;

            // Process refund if applicable
            if ($processRefund && $enrollment->amount_paid > 0 && $enrollment->is_refundable) {
                $refundAmount = $this->calculateRefund($enrollment);

                if ($refundAmount > 0 && $enrollment->payment) {
                    $enrollment->payment->update([
                        'status'      => 'refunded',
                        'refunded_at' => now(),
                    ]);
                }
            }

            $enrollment->update([
                'status'              => Enrollment::STATUS_CANCELLED,
                'cancelled_at'        => now(),
                'cancellation_reason' => $reason,
                'refund_amount'       => $refundAmount,
                'refunded_at'         => $refundAmount > 0 ? now() : null,
            ]);

            // Process waitlist if spot opened
            $this->processWaitlist($enrollment->course);

            return [
                'success'       => true,
                'message'       => $refundAmount > 0
                    ? "Enrollment cancelled. Refund of \${$refundAmount} processed."
                    : 'Enrollment cancelled successfully.',
                'enrollment'    => $enrollment->fresh(),
                'refund_amount' => $refundAmount,
            ];
        });
    }

    /**
     * Calculate the refund amount based on progress and time.
     */
    protected function calculateRefund(Enrollment $enrollment): float
    {
        $amountPaid = (float) $enrollment->amount_paid;
        $daysEnrolled = $enrollment->days_enrolled;
        $progress = (float) $enrollment->progress_percentage;

        // Full refund within 7 days and less than 10% progress
        if ($daysEnrolled <= 7 && $progress < 10) {
            return $amountPaid;
        }

        // 75% refund within 14 days and less than 25% progress
        if ($daysEnrolled <= 14 && $progress < 25) {
            return round($amountPaid * 0.75, 2);
        }

        // 50% refund within 30 days and less than 50% progress
        if ($daysEnrolled <= 30 && $progress < 50) {
            return round($amountPaid * 0.50, 2);
        }

        // No refund after 30 days or more than 50% progress
        return 0;
    }

    /**
     * Transfer an enrollment to another student.
     */
    public function transferEnrollment(Enrollment $enrollment, User $newStudent, ?string $reason = null): array
    {
        if (!$enrollment->is_transferable) {
            return [
                'success' => false,
                'message' => 'This enrollment cannot be transferred. Requirements: active status, less than 25% progress, and not expired.',
            ];
        }

        // Check if new student is already enrolled
        $exists = Enrollment::where('user_id', $newStudent->id)
            ->where('course_id', $enrollment->course_id)
            ->whereNotIn('status', [Enrollment::STATUS_CANCELLED, Enrollment::STATUS_EXPIRED])
            ->exists();

        if ($exists) {
            return [
                'success' => false,
                'message' => 'The target student is already enrolled in this course.',
            ];
        }

        return DB::transaction(function () use ($enrollment, $newStudent, $reason) {
            // Cancel original enrollment (no refund)
            $enrollment->update([
                'status'              => Enrollment::STATUS_CANCELLED,
                'cancelled_at'        => now(),
                'cancellation_reason' => 'Transferred to user #' . $newStudent->id . ($reason ? ": {$reason}" : ''),
            ]);

            // Create new enrollment for the new student
            $newEnrollment = Enrollment::create([
                'user_id'          => $newStudent->id,
                'course_id'        => $enrollment->course_id,
                'enrollment_type'  => $enrollment->enrollment_type,
                'status'           => Enrollment::STATUS_ACTIVE,
                'payment_id'       => $enrollment->payment_id,
                'coupon_id'        => $enrollment->coupon_id,
                'transferred_from' => $enrollment->id,
                'amount_paid'      => $enrollment->amount_paid,
                'enrolled_at'      => now(),
                'expires_at'       => $enrollment->expires_at,
                'notes'            => 'Transferred from enrollment #' . $enrollment->id,
            ]);

            $this->sendWelcomeNotification($newEnrollment);

            return [
                'success'            => true,
                'message'            => "Enrollment successfully transferred to {$newStudent->name}.",
                'original_enrollment' => $enrollment->fresh(),
                'new_enrollment'      => $newEnrollment->load('course:id,title,slug'),
            ];
        });
    }

    /**
     * Extend an enrollment's expiration date.
     */
    public function extendEnrollment(Enrollment $enrollment, int $additionalDays, ?string $reason = null): array
    {
        if ($enrollment->status === Enrollment::STATUS_CANCELLED) {
            return ['success' => false, 'message' => 'Cannot extend a cancelled enrollment.'];
        }

        $currentExpiry = $enrollment->expires_at;
        $baseDate = ($currentExpiry && $currentExpiry->isFuture()) ? $currentExpiry : now();
        $newExpiry = $baseDate->copy()->addDays($additionalDays);

        // If enrollment was expired, reactivate it
        $newStatus = $enrollment->status;
        if ($enrollment->status === Enrollment::STATUS_EXPIRED) {
            $newStatus = Enrollment::STATUS_ACTIVE;
        }

        $enrollment->update([
            'status'     => $newStatus,
            'expires_at' => $newExpiry,
            'notes'      => ($enrollment->notes ? $enrollment->notes . "\n" : '')
                . "Extended by {$additionalDays} days on " . now()->format('Y-m-d') . ($reason ? ": {$reason}" : ''),
            'metadata'   => array_merge($enrollment->metadata ?? [], [
                'extensions' => array_merge($enrollment->metadata['extensions'] ?? [], [
                    [
                        'days'           => $additionalDays,
                        'previous_expiry' => $currentExpiry?->toISOString(),
                        'new_expiry'      => $newExpiry->toISOString(),
                        'reason'          => $reason,
                        'extended_at'     => now()->toISOString(),
                    ],
                ]),
            ]),
        ]);

        return [
            'success'          => true,
            'message'          => "Enrollment extended by {$additionalDays} days. New expiry: " . $newExpiry->format('M d, Y'),
            'enrollment'       => $enrollment->fresh(),
            'previous_expiry'  => $currentExpiry?->format('M d, Y'),
            'new_expiry'       => $newExpiry->format('M d, Y'),
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // Utility Methods
    // ──────────────────────────────────────────────────────────────────

    /**
     * Check if a course has reached its student capacity.
     */
    public function isCourseFull(Course $course): bool
    {
        if (is_null($course->max_students)) {
            return false;
        }

        $activeCount = Enrollment::where('course_id', $course->id)
            ->whereIn('status', [
                Enrollment::STATUS_ACTIVE,
                Enrollment::STATUS_IN_PROGRESS,
                Enrollment::STATUS_PENDING,
            ])
            ->count();

        return $activeCount >= $course->max_students;
    }

    /**
     * Get available spots in a course.
     */
    public function getAvailableSpots(Course $course): ?int
    {
        if (is_null($course->max_students)) {
            return null; // Unlimited
        }

        $activeCount = Enrollment::where('course_id', $course->id)
            ->whereIn('status', [
                Enrollment::STATUS_ACTIVE,
                Enrollment::STATUS_IN_PROGRESS,
                Enrollment::STATUS_PENDING,
            ])
            ->count();

        return max(0, $course->max_students - $activeCount);
    }

    /**
     * Apply a coupon code and return discount info.
     */
    protected function applyCoupon(string $code, User $user, Course $course): array
    {
        $coupon = Coupon::where('code', strtoupper($code))->first();

        if (!$coupon) {
            return ['success' => false, 'message' => 'Invalid coupon code.'];
        }

        if (!$coupon->is_valid) {
            return ['success' => false, 'message' => 'This coupon is no longer valid.'];
        }

        if (!$coupon->canBeUsedBy($user->id)) {
            return ['success' => false, 'message' => 'You have already used this coupon the maximum number of times.'];
        }

        $discount = $coupon->calculateDiscount($course->effective_price);

        return [
            'success'   => true,
            'coupon_id' => $coupon->id,
            'discount'  => $discount,
            'coupon'    => $coupon,
        ];
    }

    /**
     * Expire enrollments that have passed their expiration date.
     */
    public function expireEnrollments(): int
    {
        $count = Enrollment::whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereNotIn('status', [
                Enrollment::STATUS_EXPIRED,
                Enrollment::STATUS_CANCELLED,
                Enrollment::STATUS_COMPLETED,
            ])
            ->update(['status' => Enrollment::STATUS_EXPIRED]);

        return $count;
    }

    /**
     * Get inactive students for a course.
     */
    public function getInactiveStudents(Course $course, int $inactiveDays = 14)
    {
        return Enrollment::with('user:id,name,email')
            ->where('course_id', $course->id)
            ->inactive($inactiveDays)
            ->get();
    }

    // ──────────────────────────────────────────────────────────────────
    // Notifications
    // ──────────────────────────────────────────────────────────────────

    /**
     * Send welcome notification to enrolled student.
     */
    protected function sendWelcomeNotification(Enrollment $enrollment): void
    {
        $enrollment->load('user', 'course');
        $enrollment->user->notify(new EnrollmentWelcomeNotification($enrollment));
    }

    /**
     * Send reminder to inactive students.
     */
    public function sendInactivityReminders(int $inactiveDays = 14): int
    {
        $inactiveEnrollments = Enrollment::with('user', 'course')
            ->inactive($inactiveDays)
            ->get();

        foreach ($inactiveEnrollments as $enrollment) {
            $enrollment->user->notify(
                new \App\Notifications\EnrollmentReminderNotification($enrollment)
            );
        }

        return $inactiveEnrollments->count();
    }

    /**
     * Send certificate available notification.
     */
    public function sendCertificateNotification(Enrollment $enrollment): void
    {
        $enrollment->load('user', 'course');
        $enrollment->user->notify(new CertificateAvailableNotification($enrollment));
    }
}
