<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table) {
            // ── Video-specific tracking ──────────────────────────────
            $table->integer('video_resume_position')->default(0)->after('watch_time_seconds');
            // in seconds — where to resume playback
            $table->integer('video_total_duration')->default(0)->after('video_resume_position');
            // total video length in seconds
            $table->decimal('video_watched_percentage', 5, 2)->default(0)->after('video_total_duration');
            // how much of the video has been watched (may exceed 100% for rewatches)
            $table->integer('video_play_count')->default(0)->after('video_watched_percentage');
            // number of times video was played

            // ── Quiz-specific tracking ───────────────────────────────
            $table->integer('quiz_attempts_count')->default(0)->after('video_play_count');
            $table->decimal('quiz_best_score', 5, 2)->nullable()->after('quiz_attempts_count');
            $table->decimal('quiz_latest_score', 5, 2)->nullable()->after('quiz_best_score');
            $table->boolean('quiz_passed')->default(false)->after('quiz_latest_score');

            // ── Assignment-specific tracking ─────────────────────────
            $table->string('assignment_status', 30)->nullable()->after('quiz_passed');
            // draft, submitted, graded, returned, resubmitted
            $table->decimal('assignment_score', 5, 2)->nullable()->after('assignment_status');

            // ── PDF / Document-specific tracking ─────────────────────
            $table->integer('pdf_view_duration_seconds')->default(0)->after('assignment_score');
            $table->integer('pdf_pages_viewed')->default(0)->after('pdf_view_duration_seconds');
            $table->integer('pdf_total_pages')->default(0)->after('pdf_pages_viewed');

            // ── General tracking ─────────────────────────────────────
            $table->integer('time_spent_seconds')->default(0)->after('pdf_total_pages');
            // total time spent on this lesson (all types)
            $table->integer('interaction_count')->default(0)->after('time_spent_seconds');
            // number of times the student interacted with this lesson
            $table->timestamp('last_accessed_at')->nullable()->after('interaction_count');
            $table->timestamp('first_accessed_at')->nullable()->after('last_accessed_at');
            $table->json('metadata')->nullable()->after('first_accessed_at');
            // for type-specific extra data

            $table->index('last_accessed_at');
            $table->index('first_accessed_at');
        });

        // ── Course Progress Milestones ───────────────────────────────
        Schema::create('progress_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->integer('milestone');
            // e.g., 25, 50, 75, 100
            $table->timestamp('reached_at');
            $table->timestamps();

            $table->unique(['user_id', 'course_id', 'milestone']);
            $table->index(['user_id', 'course_id']);
        });

        // ── Activity Log (for heatmap) ───────────────────────────────
        Schema::create('learning_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('activity_type', 50);
            // lesson_view, video_watch, quiz_attempt, assignment_submit, pdf_read, note_added
            $table->integer('duration_seconds')->default(0);
            $table->date('activity_date');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'activity_date']);
            $table->index(['user_id', 'course_id', 'activity_date']);
            $table->index('activity_type');
        });

        // ── Resume State ─────────────────────────────────────────────
        Schema::create('user_course_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('last_lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->foreignId('next_lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->integer('last_video_position')->default(0);
            // seconds in the last watched video
            $table->timestamp('last_accessed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_course_states');
        Schema::dropIfExists('learning_activity_logs');
        Schema::dropIfExists('progress_milestones');

        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->dropIndex(['last_accessed_at']);
            $table->dropIndex(['first_accessed_at']);

            $table->dropColumn([
                'video_resume_position', 'video_total_duration', 'video_watched_percentage',
                'video_play_count', 'quiz_attempts_count', 'quiz_best_score', 'quiz_latest_score',
                'quiz_passed', 'assignment_status', 'assignment_score',
                'pdf_view_duration_seconds', 'pdf_pages_viewed', 'pdf_total_pages',
                'time_spent_seconds', 'interaction_count', 'last_accessed_at',
                'first_accessed_at', 'metadata',
            ]);
        });
    }
};
