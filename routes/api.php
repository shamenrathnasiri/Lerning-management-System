<?php

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BadgeController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\DiscussionController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\LessonProgressController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WishlistController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ─── Public (Guest) Routes ───────────────────────────────────────────

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public course browsing
Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{course}', [CourseController::class, 'show']);

// Public category & tag listing
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);
Route::get('/tags', [TagController::class, 'index']);
Route::get('/tags/{tag}', [TagController::class, 'show']);

// Public reviews (approved only)
Route::get('/reviews', [ReviewController::class, 'index']);

// Public instructor listing
Route::get('/instructors', [UserController::class, 'instructors']);
Route::get('/users/{user}/courses', [UserController::class, 'courses']);

// Certificate verification (public)
Route::get('/certificates/verify/{certificateNumber}', [CertificateController::class, 'verify']);

// Coupon validation (public)
Route::post('/coupons/validate', [CouponController::class, 'validate_code']);

// Public announcements
Route::get('/announcements', [AnnouncementController::class, 'index']);
Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show']);

// ─── Authenticated Routes ────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // ─── Profile / Users ─────────────────────────────────────────

    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    Route::post('/users/{user}/assign-role', [UserController::class, 'assignRole']);

    // ─── Categories ──────────────────────────────────────────────

    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

    // ─── Tags ────────────────────────────────────────────────────

    Route::post('/tags', [TagController::class, 'store']);
    Route::put('/tags/{tag}', [TagController::class, 'update']);
    Route::delete('/tags/{tag}', [TagController::class, 'destroy']);

    // ─── Courses ─────────────────────────────────────────────────

    Route::post('/courses', [CourseController::class, 'store']);
    Route::put('/courses/{course}', [CourseController::class, 'update']);
    Route::delete('/courses/{course}', [CourseController::class, 'destroy']);

    // ─── Sections (nested under courses) ─────────────────────────

    Route::get('/courses/{course}/sections', [SectionController::class, 'index']);
    Route::post('/courses/{course}/sections', [SectionController::class, 'store']);
    Route::get('/courses/{course}/sections/{section}', [SectionController::class, 'show']);
    Route::put('/courses/{course}/sections/{section}', [SectionController::class, 'update']);
    Route::delete('/courses/{course}/sections/{section}', [SectionController::class, 'destroy']);

    // ─── Lessons (nested under courses / sections) ───────────────

    Route::get('/courses/{course}/sections/{section}/lessons', [LessonController::class, 'index']);
    Route::post('/courses/{course}/sections/{section}/lessons', [LessonController::class, 'store']);
    Route::get('/courses/{course}/sections/{section}/lessons/{lesson}', [LessonController::class, 'show']);
    Route::put('/courses/{course}/sections/{section}/lessons/{lesson}', [LessonController::class, 'update']);
    Route::delete('/courses/{course}/sections/{section}/lessons/{lesson}', [LessonController::class, 'destroy']);

    // ─── Enrollments ─────────────────────────────────────────────

    // Core enrollment endpoints
    Route::get('/enrollments', [EnrollmentController::class, 'index']);
    Route::post('/enrollments', [EnrollmentController::class, 'enroll']);
    Route::get('/enrollments/{enrollment}', [EnrollmentController::class, 'show']);
    Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy']);
    Route::get('/my-enrollments', [EnrollmentController::class, 'myEnrollments']);

    // Enrollment actions
    Route::post('/enrollments/{enrollment}/confirm-payment', [EnrollmentController::class, 'confirmPayment']);
    Route::post('/enrollments/{enrollment}/cancel', [EnrollmentController::class, 'cancelEnrollment']);
    Route::post('/enrollments/{enrollment}/transfer', [EnrollmentController::class, 'transferEnrollment']);
    Route::post('/enrollments/{enrollment}/extend', [EnrollmentController::class, 'extendEnrollment']);
    Route::put('/enrollments/{enrollment}/status', [EnrollmentController::class, 'updateStatus']);

    // Waitlist management
    Route::post('/enrollments/waitlist', [EnrollmentController::class, 'joinWaitlist']);
    Route::delete('/enrollments/waitlist/{courseId}', [EnrollmentController::class, 'leaveWaitlist']);
    Route::get('/enrollments/waitlist', [EnrollmentController::class, 'getWaitlist']);

    // Group enrollments
    Route::get('/enrollments/groups', [EnrollmentController::class, 'groups']);
    Route::get('/enrollments/groups/{groupEnrollment}', [EnrollmentController::class, 'showGroup']);

    // Course enrollment info & statistics
    Route::get('/courses/{course}/enrollment-info', [EnrollmentController::class, 'courseEnrollmentInfo']);
    Route::get('/enrollments/statistics', [EnrollmentController::class, 'statistics']);

    // ─── Progress Tracking ──────────────────────────────────────

    // Course-level progress
    Route::get('/courses/{course}/progress', [LessonProgressController::class, 'index']);
    Route::get('/courses/{course}/progress/circular', [LessonProgressController::class, 'circularProgress']);
    Route::get('/courses/{course}/progress/modules', [LessonProgressController::class, 'moduleCheckmarks']);
    Route::get('/courses/{course}/progress/activity', [LessonProgressController::class, 'activityBreakdown']);
    Route::get('/courses/{course}/progress/export', [LessonProgressController::class, 'exportForCertificate']);

    // Resume learning
    Route::get('/courses/{course}/resume', [LessonProgressController::class, 'resume']);
    Route::get('/courses/{course}/next-lesson', [LessonProgressController::class, 'nextLesson']);

    // Lesson-level progress tracking
    Route::post('/progress/track', [LessonProgressController::class, 'track']);
    Route::post('/progress/complete', [LessonProgressController::class, 'markComplete']);
    Route::post('/progress/incomplete', [LessonProgressController::class, 'markIncomplete']);
    Route::get('/progress/lesson/{lesson}', [LessonProgressController::class, 'lessonProgress']);

    // Activity heatmap
    Route::get('/progress/heatmap', [LessonProgressController::class, 'heatmap']);

    // ─── Quizzes ─────────────────────────────────────────────────

    Route::get('/quizzes', [QuizController::class, 'index']);
    Route::post('/quizzes', [QuizController::class, 'store']);
    Route::get('/quizzes/{quiz}', [QuizController::class, 'show']);
    Route::put('/quizzes/{quiz}', [QuizController::class, 'update']);
    Route::delete('/quizzes/{quiz}', [QuizController::class, 'destroy']);
    Route::post('/quizzes/{quiz}/attempt', [QuizController::class, 'attempt']);
    Route::post('/quizzes/{quiz}/submit', [QuizController::class, 'submitAttempt']);
    Route::get('/quizzes/{quiz}/attempts', [QuizController::class, 'attempts']);

    // ─── Questions (nested under quizzes) ────────────────────────

    Route::get('/quizzes/{quiz}/questions', [QuestionController::class, 'index']);
    Route::post('/quizzes/{quiz}/questions', [QuestionController::class, 'store']);
    Route::get('/quizzes/{quiz}/questions/{question}', [QuestionController::class, 'show']);
    Route::put('/quizzes/{quiz}/questions/{question}', [QuestionController::class, 'update']);
    Route::delete('/quizzes/{quiz}/questions/{question}', [QuestionController::class, 'destroy']);

    // ─── Assignments ─────────────────────────────────────────────

    Route::get('/assignments', [AssignmentController::class, 'index']);
    Route::post('/assignments', [AssignmentController::class, 'store']);
    Route::get('/assignments/{assignment}', [AssignmentController::class, 'show']);
    Route::put('/assignments/{assignment}', [AssignmentController::class, 'update']);
    Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy']);
    Route::get('/assignments/{assignment}/submissions', [AssignmentController::class, 'submissions']);
    Route::post('/assignments/{assignment}/submit', [AssignmentController::class, 'submit']);
    Route::post('/assignments/{assignment}/submissions/{submission}/grade', [AssignmentController::class, 'grade']);

    // ─── Certificates ────────────────────────────────────────────

    Route::get('/certificates', [CertificateController::class, 'index']);
    Route::post('/certificates', [CertificateController::class, 'store']);
    Route::get('/certificates/{certificate}', [CertificateController::class, 'show']);
    Route::get('/certificates/{certificate}/download', [CertificateController::class, 'download']);
    Route::delete('/certificates/{certificate}', [CertificateController::class, 'destroy']);
    Route::get('/my-certificates', [CertificateController::class, 'myCertificates']);

    // ─── Reviews ─────────────────────────────────────────────────

    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::get('/reviews/{review}', [ReviewController::class, 'show']);
    Route::put('/reviews/{review}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
    Route::post('/reviews/{review}/approve', [ReviewController::class, 'approve']);

    // ─── Discussions ─────────────────────────────────────────────

    Route::get('/discussions', [DiscussionController::class, 'index']);
    Route::post('/discussions', [DiscussionController::class, 'store']);
    Route::get('/discussions/{discussion}', [DiscussionController::class, 'show']);
    Route::put('/discussions/{discussion}', [DiscussionController::class, 'update']);
    Route::delete('/discussions/{discussion}', [DiscussionController::class, 'destroy']);

    // Discussion replies
    Route::get('/discussions/{discussion}/replies', [DiscussionController::class, 'replies']);
    Route::post('/discussions/{discussion}/replies', [DiscussionController::class, 'storeReply']);
    Route::put('/discussions/{discussion}/replies/{reply}', [DiscussionController::class, 'updateReply']);
    Route::delete('/discussions/{discussion}/replies/{reply}', [DiscussionController::class, 'destroyReply']);

    // ─── Badges ──────────────────────────────────────────────────

    Route::get('/badges', [BadgeController::class, 'index']);
    Route::post('/badges', [BadgeController::class, 'store']);
    Route::get('/badges/{badge}', [BadgeController::class, 'show']);
    Route::put('/badges/{badge}', [BadgeController::class, 'update']);
    Route::delete('/badges/{badge}', [BadgeController::class, 'destroy']);
    Route::post('/badges/{badge}/award', [BadgeController::class, 'award']);
    Route::get('/my-badges', [BadgeController::class, 'myBadges']);

    // ─── Payments ────────────────────────────────────────────────

    Route::get('/payments', [PaymentController::class, 'index']);
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::put('/payments/{payment}/status', [PaymentController::class, 'updateStatus']);
    Route::get('/my-payments', [PaymentController::class, 'myPayments']);

    // ─── Coupons ─────────────────────────────────────────────────

    Route::get('/coupons', [CouponController::class, 'index']);
    Route::post('/coupons', [CouponController::class, 'store']);
    Route::get('/coupons/{coupon}', [CouponController::class, 'show']);
    Route::put('/coupons/{coupon}', [CouponController::class, 'update']);
    Route::delete('/coupons/{coupon}', [CouponController::class, 'destroy']);

    // ─── Wishlist ────────────────────────────────────────────────

    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{courseId}', [WishlistController::class, 'destroy']);

    // ─── Announcements (management) ──────────────────────────────

    Route::post('/announcements', [AnnouncementController::class, 'store']);
    Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update']);
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy']);

    // ─── Notifications ───────────────────────────────────────────

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
});
