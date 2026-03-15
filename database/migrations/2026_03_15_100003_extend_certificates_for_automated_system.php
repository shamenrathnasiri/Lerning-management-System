<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            // Template & design
            $table->string('template', 30)->default('modern')->after('title');
            // modern, classic, minimal

            // Instructor signature
            $table->string('instructor_name')->nullable()->after('template');
            $table->string('instructor_signature_path')->nullable()->after('instructor_name');

            // Course details captured at issue time
            $table->string('course_title')->nullable()->after('instructor_signature_path');
            $table->string('course_level', 30)->nullable()->after('course_title');
            $table->integer('course_duration_hours')->nullable()->after('course_level');

            // Student snapshot
            $table->string('student_name')->nullable()->after('course_duration_hours');

            // Progress snapshot
            $table->decimal('final_score', 5, 2)->nullable()->after('student_name');
            $table->integer('completion_days')->nullable()->after('final_score');
            $table->integer('total_lessons_completed')->nullable()->after('completion_days');

            // Verification
            $table->string('verification_hash', 64)->nullable()->after('total_lessons_completed');
            $table->string('qr_code_path')->nullable()->after('verification_hash');

            // Social sharing
            $table->string('share_token', 40)->nullable()->after('qr_code_path');
            $table->string('linkedin_share_url')->nullable()->after('share_token');
            $table->string('public_image_path')->nullable()->after('linkedin_share_url');

            // Revocation
            $table->boolean('is_revoked')->default(false)->after('public_image_path');
            $table->timestamp('revoked_at')->nullable()->after('is_revoked');
            $table->string('revocation_reason')->nullable()->after('revoked_at');

            // Metadata (for extra data)
            $table->json('metadata')->nullable()->after('revocation_reason');

            // Indexes
            $table->index('template');
            $table->index('share_token');
            $table->index('verification_hash');
        });

        // Certificate templates table
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('design', 30)->default('modern');
            // modern, classic, minimal
            $table->text('description')->nullable();

            // Design configuration
            $table->string('background_image_path')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('badge_image_path')->nullable();
            $table->string('border_style', 30)->default('elegant');
            // elegant, gold, simple, none
            $table->string('primary_color', 10)->default('#1a365d');
            $table->string('accent_color', 10)->default('#c9a84c');
            $table->string('text_color', 10)->default('#1a202c');
            $table->string('font_family')->default('serif');

            // Content configuration
            $table->string('heading_text')->default('Certificate of Completion');
            $table->text('body_text')->nullable();
            // supports {{student_name}}, {{course_title}}, etc.
            $table->text('footer_text')->nullable();

            // Signature fields
            $table->json('signature_fields')->nullable();
            // [{ "name": "...", "title": "...", "signature_path": "..." }]

            // Layout
            $table->string('orientation', 15)->default('landscape');
            $table->string('paper_size', 10)->default('a4');

            $table->boolean('show_qr_code')->default(true);
            $table->boolean('show_logo')->default(true);
            $table->boolean('show_badge')->default(false);
            $table->boolean('show_date')->default(true);
            $table->boolean('show_score')->default(false);
            $table->boolean('show_duration')->default(false);

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_templates');

        Schema::table('certificates', function (Blueprint $table) {
            $table->dropIndex(['template']);
            $table->dropIndex(['share_token']);
            $table->dropIndex(['verification_hash']);

            $table->dropColumn([
                'template', 'instructor_name', 'instructor_signature_path',
                'course_title', 'course_level', 'course_duration_hours',
                'student_name', 'final_score', 'completion_days',
                'total_lessons_completed', 'verification_hash', 'qr_code_path',
                'share_token', 'linkedin_share_url', 'public_image_path',
                'is_revoked', 'revoked_at', 'revocation_reason', 'metadata',
            ]);
        });
    }
};
