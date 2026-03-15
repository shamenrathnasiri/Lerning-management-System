<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentWaitlist;
use App\Models\GroupEnrollment;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function __construct(
        protected EnrollmentService $enrollmentService
    ) {}

    // ──────────────────────────────────────────────────────────────────
    // List & View
    // ──────────────────────────────────────────────────────────────────

    /**
     * GET /enrollments
     * List enrollments with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Enrollment::with('course:id,title,slug,thumbnail', 'user:id,name,username,email');

        // Filters
        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('enrollment_type')) {
            $query->where('enrollment_type', $request->enrollment_type);
        }

        if ($request->filled('date_from')) {
            $query->where('enrolled_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('enrolled_at', '<=', $request->date_to);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                  ->orWhereHas('course', fn($cq) => $cq->where('title', 'like', "%{$search}%"));
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        return response()->json(
            $query->paginate($request->integer('per_page', 15))
        );
    }

    /**
     * GET /enrollments/{enrollment}
     * Show a single enrollment.
     */
    public function show(Enrollment $enrollment): JsonResponse
    {
        return response()->json(
            $enrollment->load([
                'course:id,title,slug,thumbnail,instructor_id,price,is_free',
                'course.instructor:id,name',
                'user:id,name,username,email',
                'payment',
                'coupon:id,code,type,value',
                'enrolledByUser:id,name',
                'groupEnrollment',
                'transferredFromEnrollment',
            ])
        );
    }

    /**
     * GET /my-enrollments
     * Get the authenticated user's enrollments.
     */
    public function myEnrollments(Request $request): JsonResponse
    {
        $query = Enrollment::with('course:id,title,slug,thumbnail,instructor_id', 'course.instructor:id,name')
            ->where('user_id', $request->user()->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(
            $query->latest()->paginate($request->integer('per_page', 15))
        );
    }

    // ──────────────────────────────────────────────────────────────────
    // Enrollment Actions
    // ──────────────────────────────────────────────────────────────────

    /**
     * POST /enrollments
     * Process enrollment based on type.
     */
    public function enroll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id'       => ['required', 'exists:courses,id'],
            'enrollment_type' => ['sometimes', 'in:' . implode(',', Enrollment::TYPES)],
            'coupon_code'     => ['sometimes', 'string'],
            'payment_method'  => ['sometimes', 'string'],
            'payment_gateway' => ['sometimes', 'string'],
            'currency'        => ['sometimes', 'string', 'size:3'],
            'notes'           => ['sometimes', 'string', 'max:500'],
            // For bulk enrollment
            'user_ids'        => ['sometimes', 'array', 'min:1'],
            'user_ids.*'      => ['exists:users,id'],
            // For group enrollment
            'member_ids'      => ['sometimes', 'array', 'min:1'],
            'member_ids.*'    => ['exists:users,id'],
            'team_name'       => ['sometimes', 'string', 'max:255'],
            'group_name'      => ['sometimes', 'string', 'max:255'],
            'max_members'     => ['sometimes', 'integer', 'min:2'],
        ]);

        $course = Course::findOrFail($validated['course_id']);
        $user = $request->user();

        // Determine enrollment type
        $type = $validated['enrollment_type'] ?? $this->determineEnrollmentType($course, $validated);

        $result = $this->enrollmentService->enroll($user, $course, $type, $validated);

        $statusCode = $result['success'] ? 201 : 422;

        return response()->json($result, $statusCode);
    }

    /**
     * POST /enrollments/{enrollment}/confirm-payment
     * Confirm payment for a pending enrollment.
     */
    public function confirmPayment(Request $request, Enrollment $enrollment): JsonResponse
    {
        $validated = $request->validate([
            'transaction_id'   => ['sometimes', 'string'],
            'payment_method'   => ['sometimes', 'string'],
            'payment_gateway'  => ['sometimes', 'string'],
            'gateway_response' => ['sometimes', 'array'],
        ]);

        $result = $this->enrollmentService->confirmPayment($enrollment, $validated);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * POST /enrollments/{enrollment}/cancel
     * Cancel an enrollment with optional refund.
     */
    public function cancelEnrollment(Request $request, Enrollment $enrollment): JsonResponse
    {
        $validated = $request->validate([
            'reason'         => ['sometimes', 'string', 'max:500'],
            'process_refund' => ['sometimes', 'boolean'],
        ]);

        $result = $this->enrollmentService->cancelEnrollment(
            $enrollment,
            $validated['reason'] ?? null,
            $validated['process_refund'] ?? true
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * POST /enrollments/{enrollment}/transfer
     * Transfer an enrollment to another student.
     */
    public function transferEnrollment(Request $request, Enrollment $enrollment): JsonResponse
    {
        $validated = $request->validate([
            'new_user_id' => ['required', 'exists:users,id'],
            'reason'      => ['sometimes', 'string', 'max:500'],
        ]);

        $newStudent = User::findOrFail($validated['new_user_id']);

        $result = $this->enrollmentService->transferEnrollment(
            $enrollment,
            $newStudent,
            $validated['reason'] ?? null
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * POST /enrollments/{enrollment}/extend
     * Extend an enrollment's expiration date.
     */
    public function extendEnrollment(Request $request, Enrollment $enrollment): JsonResponse
    {
        $validated = $request->validate([
            'additional_days' => ['required', 'integer', 'min:1', 'max:365'],
            'reason'          => ['sometimes', 'string', 'max:500'],
        ]);

        $result = $this->enrollmentService->extendEnrollment(
            $enrollment,
            $validated['additional_days'],
            $validated['reason'] ?? null
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * PUT /enrollments/{enrollment}/status
     * Update enrollment status.
     */
    public function updateStatus(Request $request, Enrollment $enrollment): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:' . implode(',', Enrollment::STATUSES)],
        ]);

        $oldStatus = $enrollment->status;
        $newStatus = $validated['status'];

        // Validate status transition
        if (!$this->isValidStatusTransition($oldStatus, $newStatus)) {
            return response()->json([
                'success' => false,
                'message' => "Cannot transition from '{$oldStatus}' to '{$newStatus}'.",
            ], 422);
        }

        $enrollment->update([
            'status'          => $newStatus,
            'completed_at'    => $newStatus === Enrollment::STATUS_COMPLETED ? now() : $enrollment->completed_at,
            'cancelled_at'    => $newStatus === Enrollment::STATUS_CANCELLED ? now() : $enrollment->cancelled_at,
            'last_activity_at' => now(),
        ]);

        return response()->json([
            'success'    => true,
            'message'    => "Status updated to '{$newStatus}'.",
            'enrollment' => $enrollment->fresh(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Waitlist
    // ──────────────────────────────────────────────────────────────────

    /**
     * POST /enrollments/waitlist
     * Join a course waitlist.
     */
    public function joinWaitlist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
        ]);

        $course = Course::findOrFail($validated['course_id']);
        $result = $this->enrollmentService->addToWaitlist($request->user(), $course);

        return response()->json($result, $result['success'] ? 201 : 422);
    }

    /**
     * DELETE /enrollments/waitlist/{courseId}
     * Leave waitlist.
     */
    public function leaveWaitlist(Request $request, int $courseId): JsonResponse
    {
        $entry = EnrollmentWaitlist::where('user_id', $request->user()->id)
            ->where('course_id', $courseId)
            ->where('status', 'waiting')
            ->first();

        if (!$entry) {
            return response()->json([
                'success' => false,
                'message' => 'You are not on the waitlist for this course.',
            ], 404);
        }

        $entry->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Removed from the waitlist.',
        ]);
    }

    /**
     * GET /enrollments/waitlist
     * Get course waitlist (admin/instructor view).
     */
    public function getWaitlist(Request $request): JsonResponse
    {
        $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
        ]);

        $waitlist = EnrollmentWaitlist::with('user:id,name,email')
            ->where('course_id', $request->course_id)
            ->where('status', 'waiting')
            ->orderBy('position')
            ->paginate($request->integer('per_page', 15));

        return response()->json($waitlist);
    }

    // ──────────────────────────────────────────────────────────────────
    // Group Enrollments
    // ──────────────────────────────────────────────────────────────────

    /**
     * GET /enrollments/groups
     * List group enrollments.
     */
    public function groups(Request $request): JsonResponse
    {
        $query = GroupEnrollment::with('course:id,title,slug', 'creator:id,name')
            ->withCount('enrollments');

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(
            $query->latest()->paginate($request->integer('per_page', 15))
        );
    }

    /**
     * GET /enrollments/groups/{groupEnrollment}
     * Show group enrollment detail.
     */
    public function showGroup(GroupEnrollment $groupEnrollment): JsonResponse
    {
        return response()->json(
            $groupEnrollment->load([
                'course:id,title,slug',
                'creator:id,name,email',
                'enrollments.user:id,name,email',
            ])
        );
    }

    // ──────────────────────────────────────────────────────────────────
    // Course Enrollment Info
    // ──────────────────────────────────────────────────────────────────

    /**
     * GET /courses/{course}/enrollment-info
     * Get enrollment information for a course (spots, waitlist, etc.).
     */
    public function courseEnrollmentInfo(Request $request, Course $course): JsonResponse
    {
        $user = $request->user();

        $data = [
            'course_id'        => $course->id,
            'max_students'     => $course->max_students,
            'available_spots'  => $this->enrollmentService->getAvailableSpots($course),
            'is_full'          => $this->enrollmentService->isCourseFull($course),
            'total_enrolled'   => Enrollment::where('course_id', $course->id)
                ->whereIn('status', [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_IN_PROGRESS])
                ->count(),
            'waitlist_count'   => EnrollmentWaitlist::where('course_id', $course->id)
                ->where('status', 'waiting')
                ->count(),
            'is_free'          => $course->is_free,
            'price'            => $course->effective_price,
            'enrollment_duration_days' => $course->enrollment_duration_days,
        ];

        if ($user) {
            $data['user_enrollment'] = Enrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->whereNotIn('status', [Enrollment::STATUS_CANCELLED])
                ->first();

            $data['user_on_waitlist'] = EnrollmentWaitlist::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->whereIn('status', ['waiting', 'notified'])
                ->first();
        }

        return response()->json($data);
    }

    // ──────────────────────────────────────────────────────────────────
    // Statistics
    // ──────────────────────────────────────────────────────────────────

    /**
     * GET /enrollments/statistics
     * Get enrollment statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $courseId = $request->course_id;
        $query = Enrollment::query();

        if ($courseId) {
            $query->where('course_id', $courseId);
        }

        $stats = [
            'total'        => (clone $query)->count(),
            'pending'      => (clone $query)->pending()->count(),
            'active'       => (clone $query)->active()->count(),
            'in_progress'  => (clone $query)->inProgress()->count(),
            'completed'    => (clone $query)->completed()->count(),
            'expired'      => (clone $query)->expired()->count(),
            'cancelled'    => (clone $query)->cancelled()->count(),
            'by_type'      => (clone $query)->selectRaw('enrollment_type, count(*) as count')
                ->groupBy('enrollment_type')
                ->pluck('count', 'enrollment_type'),
            'recent_30_days' => (clone $query)->recent(30)->count(),
            'completion_rate' => $this->calculateCompletionRate($query),
            'revenue' => [
                'total_paid'     => (clone $query)->sum('amount_paid'),
                'total_refunded' => (clone $query)->sum('refund_amount'),
                'net_revenue'    => (clone $query)->sum('amount_paid') - (clone $query)->sum('refund_amount'),
            ],
        ];

        return response()->json($stats);
    }

    // ──────────────────────────────────────────────────────────────────
    // Delete (Soft)
    // ──────────────────────────────────────────────────────────────────

    /**
     * DELETE /enrollments/{enrollment}
     * Soft-delete an enrollment.
     */
    public function destroy(Enrollment $enrollment): JsonResponse
    {
        $enrollment->delete();

        return response()->json(['message' => 'Enrollment removed.']);
    }

    // ──────────────────────────────────────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────────────────────────────────────

    /**
     * Auto-determine enrollment type based on course and request data.
     */
    private function determineEnrollmentType(Course $course, array $data): string
    {
        if (!empty($data['member_ids'])) {
            return 'group';
        }

        if (!empty($data['user_ids'])) {
            return 'bulk';
        }

        if (!empty($data['coupon_code'])) {
            return 'coupon';
        }

        if ($course->is_free) {
            return 'self';
        }

        return 'paid';
    }

    /**
     * Validate enrollment status transitions.
     */
    private function isValidStatusTransition(string $from, string $to): bool
    {
        $transitions = [
            Enrollment::STATUS_PENDING     => [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_CANCELLED],
            Enrollment::STATUS_ACTIVE      => [Enrollment::STATUS_IN_PROGRESS, Enrollment::STATUS_COMPLETED, Enrollment::STATUS_EXPIRED, Enrollment::STATUS_CANCELLED],
            Enrollment::STATUS_IN_PROGRESS => [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_COMPLETED, Enrollment::STATUS_EXPIRED, Enrollment::STATUS_CANCELLED],
            Enrollment::STATUS_COMPLETED   => [], // Terminal state
            Enrollment::STATUS_EXPIRED     => [Enrollment::STATUS_ACTIVE], // Can reactivate via extension
            Enrollment::STATUS_CANCELLED   => [], // Terminal state
        ];

        return in_array($to, $transitions[$from] ?? []);
    }

    /**
     * Calculate course completion rate.
     */
    private function calculateCompletionRate($query): float
    {
        $total = (clone $query)->whereNotIn('status', [Enrollment::STATUS_PENDING])->count();
        $completed = (clone $query)->completed()->count();

        return $total > 0 ? round(($completed / $total) * 100, 1) : 0;
    }
}
