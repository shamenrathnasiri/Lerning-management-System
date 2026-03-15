<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Extend enrollments table ─────────────────────────────────
        Schema::table('enrollments', function (Blueprint $table) {
            // Change status enum to include new statuses
            // We'll use string instead of enum for flexibility
            $table->string('enrollment_type', 30)->default('self')->after('course_id');
            // self, paid, bulk, coupon, group, waitlist

            $table->foreignId('payment_id')->nullable()->after('status')
                  ->constrained()->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->after('payment_id')
                  ->constrained()->nullOnDelete();
            $table->foreignId('enrolled_by')->nullable()->after('coupon_id')
                  ->constrained('users')->nullOnDelete();
            $table->foreignId('transferred_from')->nullable()->after('enrolled_by')
                  ->constrained('enrollments')->nullOnDelete();
            $table->foreignId('group_enrollment_id')->nullable()->after('transferred_from');

            $table->decimal('amount_paid', 10, 2)->default(0)->after('progress_percentage');
            $table->decimal('refund_amount', 10, 2)->default(0)->after('amount_paid');
            $table->timestamp('refunded_at')->nullable()->after('expires_at');
            $table->timestamp('cancelled_at')->nullable()->after('refunded_at');
            $table->timestamp('last_activity_at')->nullable()->after('cancelled_at');
            $table->text('cancellation_reason')->nullable()->after('last_activity_at');
            $table->text('notes')->nullable()->after('cancellation_reason');
            $table->json('metadata')->nullable()->after('notes');

            $table->index('enrollment_type');
            $table->index('last_activity_at');
            $table->index('group_enrollment_id');
        });

        // ── Group Enrollments table ──────────────────────────────────
        Schema::create('group_enrollments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('team_name')->nullable();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->integer('max_members')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->enum('status', ['active', 'cancelled', 'expired'])->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('course_id');
            $table->index('created_by');
            $table->index('status');
        });

        // ── Waitlist table ───────────────────────────────────────────
        Schema::create('enrollment_waitlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->integer('position')->default(0);
            $table->enum('status', ['waiting', 'notified', 'enrolled', 'expired', 'cancelled'])->default('waiting');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // notification expiry
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
            $table->index(['course_id', 'position']);
            $table->index('status');
        });

        // ── Add max_students to courses ──────────────────────────────
        Schema::table('courses', function (Blueprint $table) {
            $table->integer('max_students')->nullable()->after('is_free');
            $table->integer('enrollment_duration_days')->nullable()->after('max_students');
        });

        // ── Update enrollment status column to string ────────────────
        // (allows: pending, active, in-progress, completed, expired, cancelled)
        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('status', 30)->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropForeign(['coupon_id']);
            $table->dropForeign(['enrolled_by']);
            $table->dropForeign(['transferred_from']);

            $table->dropIndex(['enrollment_type']);
            $table->dropIndex(['last_activity_at']);
            $table->dropIndex(['group_enrollment_id']);

            $table->dropColumn([
                'enrollment_type', 'payment_id', 'coupon_id', 'enrolled_by',
                'transferred_from', 'group_enrollment_id', 'amount_paid',
                'refund_amount', 'refunded_at', 'cancelled_at', 'last_activity_at',
                'cancellation_reason', 'notes', 'metadata',
            ]);
        });

        Schema::dropIfExists('enrollment_waitlists');
        Schema::dropIfExists('group_enrollments');

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['max_students', 'enrollment_duration_days']);
        });
    }
};
