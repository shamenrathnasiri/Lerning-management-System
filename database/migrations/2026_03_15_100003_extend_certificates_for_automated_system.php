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
            $table->string('template', 50)->default('modern')->after('title');
            // modern, classic, minimal
            $table->string('custom_background')->nullable()->after('template');
            // path to custom background image
            $table->string('logo_path')->nullable()->after('custom_background');
            // custom organization logo
            $table->string('primary_color', 20)->default('#6366f1')->after('logo_path');
            // theme color for the template

            // Instructor & signatures
            $table->string('instructor_name')->nullable()->after('primary_color');
            $table->string('instructor_signature')->nullable()->after('instructor_name');
            // path to instructor signature image
            $table->string('organization_signature')->nullable()->after('instructor_signature');
            // path to org/platform signature image

            // Student achievement data
            $table->decimal('final_grade', 5, 2)->nullable()->after('organization_signature');
            $table->integer('total_hours')->default(0)->after('final_grade');
            $table->string('skill_level', 30)->nullable()->after('total_hours');
            // beginner, intermediate, advanced

            // QR code & verification
            $table->string('qr_code_path')->nullable()->after('skill_level');
            $table->string('verification_hash', 64)->nullable()->after('qr_code_path');
            // SHA-256 hash for tamper verification
            $table->text('verification_data')->nullable()->after('verification_hash');
            // JSON string of verification payload

            // Sharing & social
            $table->string('share_token', 64)->nullable()->unique()->after('verification_data');
            // token for public shared link
            $table->string('linkedin_badge_url')->nullable()->after('share_token');
            $table->json('share_metadata')->nullable()->after('linkedin_badge_url');
            // Open Graph metadata for social sharing

            // Revocation
            $table->boolean('is_revoked')->default(false)->after('share_metadata');
            $table->timestamp('revoked_at')->nullable()->after('is_revoked');
            $table->string('revocation_reason')->nullable()->after('revoked_at');
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete()->after('revocation_reason');

            // Additional indexes
            $table->index('template');
            $table->index('is_revoked');
            $table->index('share_token');
        });

        // Certificate template presets table
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('design', 50);
            // modern, classic, minimal
            $table->string('background_image')->nullable();
            $table->string('logo_image')->nullable();
            $table->string('primary_color', 20)->default('#6366f1');
            $table->string('secondary_color', 20)->default('#818cf8');
            $table->string('text_color', 20)->default('#1e293b');
            $table->string('accent_color', 20)->default('#f59e0b');
            $table->string('font_family')->default('Inter');
            $table->string('instructor_signature_image')->nullable();
            $table->string('organization_signature_image')->nullable();
            $table->string('organization_name')->nullable();
            $table->text('custom_message')->nullable();
            $table->boolean('show_grade')->default(true);
            $table->boolean('show_hours')->default(true);
            $table->boolean('show_qr')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_templates');

        Schema::table('certificates', function (Blueprint $table) {
            $table->dropIndex(['template']);
            $table->dropIndex(['is_revoked']);
            $table->dropIndex(['share_token']);

            $table->dropForeign(['revoked_by']);

            $table->dropColumn([
                'template', 'custom_background', 'logo_path', 'primary_color',
                'instructor_name', 'instructor_signature', 'organization_signature',
                'final_grade', 'total_hours', 'skill_level',
                'qr_code_path', 'verification_hash', 'verification_data',
                'share_token', 'linkedin_badge_url', 'share_metadata',
                'is_revoked', 'revoked_at', 'revocation_reason', 'revoked_by',
            ]);
        });
    }
};
