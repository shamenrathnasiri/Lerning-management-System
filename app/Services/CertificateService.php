<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\ProgressMilestone;
use App\Models\User;
use App\Notifications\CertificateAvailableNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificateService
{
    // ──────────────────────────────────────────────────────────────────
    // 1. Certificate Generation
    // ──────────────────────────────────────────────────────────────────

    /**
     * Generate a certificate when a course is completed.
     */
    public function generate(User $user, Course $course, array $options = []): array
    {
        // Check if enrollment is completed
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if (!$enrollment || $enrollment->status !== Enrollment::STATUS_COMPLETED) {
            return [
                'success' => false,
                'message' => 'Course must be completed before generating a certificate.',
            ];
        }

        // Check for existing certificate
        $existing = Certificate::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('is_revoked', false)
            ->first();

        if ($existing) {
            return [
                'success' => true,
                'message' => 'Certificate already exists.',
                'certificate' => $existing,
                'is_existing' => true,
            ];
        }

        // Get template settings
        $template = $this->resolveTemplate($course, $options);

        // Calculate achievement data
        $achievementData = $this->calculateAchievementData($user, $course, $enrollment);

        // Create certificate
        $certificate = Certificate::create([
            'user_id'                  => $user->id,
            'course_id'                => $course->id,
            'title'                    => $options['title'] ?? "Certificate of Completion — {$course->title}",
            'template'                 => $template['design'],
            'custom_background'        => $template['background_image'] ?? null,
            'logo_path'                => $template['logo_image'] ?? null,
            'primary_color'            => $template['primary_color'],
            'instructor_name'          => $course->instructor?->name,
            'instructor_signature'     => $template['instructor_signature_image'] ?? null,
            'organization_signature'   => $template['organization_signature_image'] ?? null,
            'final_grade'              => $achievementData['average_score'],
            'total_hours'              => $achievementData['total_hours'],
            'skill_level'              => $course->level,
            'issued_at'                => now(),
            'expires_at'               => $options['expires_at'] ?? null,
        ]);

        // Generate QR code
        $this->generateQrCode($certificate);

        // Generate PDF
        $this->generatePdf($certificate);

        // Notify user
        $user->notify(new CertificateAvailableNotification($enrollment));

        return [
            'success'     => true,
            'message'     => 'Certificate generated successfully.',
            'certificate' => $certificate->fresh(),
            'is_existing' => false,
        ];
    }

    /**
     * Regenerate a certificate PDF (e.g., after template change).
     */
    public function regeneratePdf(Certificate $certificate): Certificate
    {
        // Delete old PDF
        if ($certificate->pdf_path) {
            Storage::disk('public')->delete($certificate->pdf_path);
        }

        $this->generatePdf($certificate);
        $this->generateQrCode($certificate);

        return $certificate->fresh();
    }

    // ──────────────────────────────────────────────────────────────────
    // 2. PDF Generation with DOMPDF
    // ──────────────────────────────────────────────────────────────────

    /**
     * Generate the PDF for a certificate.
     */
    public function generatePdf(Certificate $certificate, bool $isPreview = false): string
    {
        $certificate->load(['user', 'course', 'course.instructor']);

        $viewData = $this->buildViewData($certificate, $isPreview);
        $template = $certificate->template ?? Certificate::TEMPLATE_MODERN;
        $viewName = "certificates.templates.{$template}";

        // Fall back to modern if template view doesn't exist
        if (!view()->exists($viewName)) {
            $viewName = 'certificates.templates.modern';
        }

        $pdf = Pdf::loadView($viewName, $viewData)
            ->setPaper('a4', 'landscape')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        // Save to storage
        $filename = "certificates/{$certificate->certificate_number}.pdf";
        Storage::disk('public')->put($filename, $pdf->output());

        $certificate->update(['pdf_path' => $filename]);

        return $filename;
    }

    /**
     * Get a PDF download response for a certificate.
     */
    public function downloadPdf(Certificate $certificate)
    {
        $certificate->load(['user', 'course', 'course.instructor']);

        $viewData = $this->buildViewData($certificate, false);
        $template = $certificate->template ?? Certificate::TEMPLATE_MODERN;
        $viewName = "certificates.templates.{$template}";

        if (!view()->exists($viewName)) {
            $viewName = 'certificates.templates.modern';
        }

        $pdf = Pdf::loadView($viewName, $viewData)
            ->setPaper('a4', 'landscape')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        $filename = "certificate-{$certificate->certificate_number}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Get a preview PDF response (with watermark).
     */
    public function previewPdf(Certificate $certificate)
    {
        $certificate->load(['user', 'course', 'course.instructor']);

        $viewData = $this->buildViewData($certificate, true);
        $template = $certificate->template ?? Certificate::TEMPLATE_MODERN;
        $viewName = "certificates.templates.{$template}";

        if (!view()->exists($viewName)) {
            $viewName = 'certificates.templates.modern';
        }

        $pdf = Pdf::loadView($viewName, $viewData)
            ->setPaper('a4', 'landscape')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        return $pdf->stream("preview-{$certificate->certificate_number}.pdf");
    }

    /**
     * Build the view data array for certificate templates.
     */
    protected function buildViewData(Certificate $certificate, bool $isPreview): array
    {
        $course = $certificate->course;
        $user = $certificate->user;

        return [
            'certificate'     => $certificate,
            'student_name'    => $user->name,
            'course_name'     => $course->title,
            'course_level'    => ucfirst($course->level ?? 'N/A'),
            'instructor_name' => $certificate->instructor_name ?? $course->instructor?->name ?? 'N/A',
            'issue_date'      => $certificate->issued_at->format('F d, Y'),
            'expiry_date'     => $certificate->expires_at?->format('F d, Y'),
            'certificate_id'  => $certificate->certificate_number,
            'final_grade'     => $certificate->formatted_grade,
            'total_hours'     => $certificate->total_hours,
            'skill_level'     => $certificate->skill_level_label,
            'verification_url' => $certificate->verification_url,
            'qr_code_url'     => $certificate->qr_code_url,
            'logo_url'        => $certificate->logo_path
                ? asset('storage/' . $certificate->logo_path)
                : asset('images/logo.png'),
            'background_url'  => $certificate->custom_background
                ? asset('storage/' . $certificate->custom_background)
                : null,
            'primary_color'   => $certificate->primary_color ?? '#6366f1',
            'instructor_signature_url' => $certificate->instructor_signature
                ? asset('storage/' . $certificate->instructor_signature)
                : null,
            'org_signature_url' => $certificate->organization_signature
                ? asset('storage/' . $certificate->organization_signature)
                : null,
            'organization_name' => config('app.name', 'LMS Platform'),
            'is_preview'       => $isPreview,
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // 3. QR Code Generation
    // ──────────────────────────────────────────────────────────────────

    /**
     * Generate a QR code SVG for certificate verification.
     * Uses a simple inline SVG QR-like code (no external dependency).
     */
    public function generateQrCode(Certificate $certificate): void
    {
        $verificationUrl = $certificate->verification_url;

        // Generate a simple QR code using inline SVG approach
        // In production, you'd use a package like simplesoftwareio/simple-qrcode
        $qrSvg = $this->generateSimpleQrSvg($verificationUrl, $certificate->certificate_number);

        $filename = "certificates/qr/{$certificate->certificate_number}.svg";
        Storage::disk('public')->put($filename, $qrSvg);

        $certificate->update(['qr_code_path' => $filename]);
    }

    /**
     * Generate a deterministic SVG pattern from the certificate data.
     * This serves as a visual verification marker.
     */
    protected function generateSimpleQrSvg(string $url, string $certNumber): string
    {
        // Create a deterministic binary pattern from the certificate number hash
        $hash = md5($url . $certNumber);
        $size = 160;
        $cellSize = 8;
        $gridSize = 17; // 17x17 grid
        $padding = 12;
        $totalSize = $size + ($padding * 2);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $totalSize . '" height="' . $totalSize . '" viewBox="0 0 ' . $totalSize . ' ' . $totalSize . '">';
        $svg .= '<rect width="100%" height="100%" fill="white"/>';

        // Draw finder patterns (three corners)
        $svg .= $this->drawFinderPattern($padding, $padding);
        $svg .= $this->drawFinderPattern($padding + ($gridSize - 7) * $cellSize, $padding);
        $svg .= $this->drawFinderPattern($padding, $padding + ($gridSize - 7) * $cellSize);

        // Fill data cells from hash
        $hashBits = '';
        foreach (str_split($hash) as $char) {
            $hashBits .= str_pad(decbin(hexdec($char)), 4, '0', STR_PAD_LEFT);
        }

        $bitIndex = 0;
        for ($row = 0; $row < $gridSize; $row++) {
            for ($col = 0; $col < $gridSize; $col++) {
                // Skip finder pattern areas
                if (($row < 8 && $col < 8) || ($row < 8 && $col >= $gridSize - 8) || ($row >= $gridSize - 8 && $col < 8)) {
                    continue;
                }

                if ($bitIndex < strlen($hashBits) && $hashBits[$bitIndex] === '1') {
                    $x = $padding + $col * $cellSize;
                    $y = $padding + $row * $cellSize;
                    $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $cellSize . '" height="' . $cellSize . '" fill="#1e293b" rx="1"/>';
                }
                $bitIndex = ($bitIndex + 1) % strlen($hashBits);
            }
        }

        $svg .= '</svg>';

        return $svg;
    }

    /**
     * Draw a QR finder pattern (the three corner squares).
     */
    protected function drawFinderPattern(int $x, int $y): string
    {
        $svg = '';
        $cs = 8; // cell size

        // Outer ring (7x7)
        $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . (7 * $cs) . '" height="' . (7 * $cs) . '" fill="#1e293b" rx="2"/>';
        // Inner white (5x5)
        $svg .= '<rect x="' . ($x + $cs) . '" y="' . ($y + $cs) . '" width="' . (5 * $cs) . '" height="' . (5 * $cs) . '" fill="white" rx="1"/>';
        // Center dot (3x3)
        $svg .= '<rect x="' . ($x + 2 * $cs) . '" y="' . ($y + 2 * $cs) . '" width="' . (3 * $cs) . '" height="' . (3 * $cs) . '" fill="#1e293b" rx="1"/>';

        return $svg;
    }

    // ──────────────────────────────────────────────────────────────────
    // 4. Certificate Verification
    // ──────────────────────────────────────────────────────────────────

    /**
     * Verify a certificate by its number.
     */
    public function verify(string $certificateNumber): array
    {
        $certificate = Certificate::with(['user:id,name', 'course:id,title,slug', 'course.instructor:id,name'])
            ->where('certificate_number', $certificateNumber)
            ->first();

        if (!$certificate) {
            return [
                'valid'   => false,
                'found'   => false,
                'message' => 'Certificate not found. This certificate number does not exist in our records.',
            ];
        }

        // Verify hash integrity
        $expectedHash = $certificate->generateVerificationHash();
        $hashValid = $certificate->verification_hash === $expectedHash;

        $isValid = $certificate->is_valid && $hashValid;

        return [
            'valid'            => $isValid,
            'found'            => true,
            'status'           => $certificate->status_label,
            'status_color'     => $certificate->status_color,
            'hash_verified'    => $hashValid,
            'certificate'      => [
                'certificate_number' => $certificate->certificate_number,
                'title'              => $certificate->title,
                'student_name'       => $certificate->user?->name,
                'course_name'        => $certificate->course?->title,
                'course_slug'        => $certificate->course?->slug,
                'instructor_name'    => $certificate->instructor_name,
                'issued_at'          => $certificate->formatted_issue_date,
                'expires_at'         => $certificate->expires_at?->format('F d, Y'),
                'final_grade'        => $certificate->formatted_grade,
                'total_hours'        => $certificate->total_hours,
                'skill_level'        => $certificate->skill_level_label,
                'template'           => $certificate->template,
            ],
            'revocation'       => $certificate->is_revoked ? [
                'revoked_at' => $certificate->revoked_at?->format('F d, Y'),
                'reason'     => $certificate->revocation_reason,
            ] : null,
            'message'          => $isValid
                ? 'This certificate is valid and verified.'
                : ($certificate->is_revoked ? 'This certificate has been revoked.' : 'This certificate could not be fully verified.'),
        ];
    }

    /**
     * Verify a certificate by its share token.
     */
    public function verifyByShareToken(string $shareToken): array
    {
        $certificate = Certificate::where('share_token', $shareToken)->first();

        if (!$certificate) {
            return ['valid' => false, 'found' => false, 'message' => 'Shared certificate not found.'];
        }

        return $this->verify($certificate->certificate_number);
    }

    // ──────────────────────────────────────────────────────────────────
    // 5. Social Sharing
    // ──────────────────────────────────────────────────────────────────

    /**
     * Get social sharing data for a certificate.
     */
    public function getShareData(Certificate $certificate): array
    {
        $certificate->load(['user', 'course']);

        $shareUrl = $certificate->public_share_url;
        $title = "I completed {$certificate->course?->title}!";
        $description = "{$certificate->user?->name} has successfully completed {$certificate->course?->title} on " . config('app.name') . ".";

        return [
            'share_url'    => $shareUrl,
            'share_token'  => $certificate->share_token,
            'linkedin'     => [
                'url'     => $certificate->linkedin_share_url,
                'post_url' => 'https://www.linkedin.com/sharing/share-offsite/?' . http_build_query(['url' => $shareUrl]),
            ],
            'twitter'      => [
                'url' => 'https://twitter.com/intent/tweet?' . http_build_query([
                    'text' => "🎉 {$title}",
                    'url'  => $shareUrl,
                ]),
            ],
            'facebook'     => [
                'url' => 'https://www.facebook.com/sharer/sharer.php?' . http_build_query(['u' => $shareUrl]),
            ],
            'email'        => [
                'subject' => $title,
                'body'    => "{$description}\n\nVerify: {$shareUrl}",
                'url'     => 'mailto:?' . http_build_query([
                    'subject' => $title,
                    'body'    => "{$description}\n\nVerify: {$shareUrl}",
                ]),
            ],
            'embed_code'   => '<iframe src="' . $shareUrl . '/embed" width="600" height="400" frameborder="0"></iframe>',
            'open_graph'   => [
                'title'       => $title,
                'description' => $description,
                'type'        => 'article',
                'url'         => $shareUrl,
                'image'       => $certificate->pdf_url,
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // 6. Revocation
    // ──────────────────────────────────────────────────────────────────

    /**
     * Revoke a certificate.
     */
    public function revoke(Certificate $certificate, string $reason, ?int $revokedBy = null): array
    {
        if ($certificate->is_revoked) {
            return [
                'success' => false,
                'message' => 'Certificate is already revoked.',
            ];
        }

        $certificate->revoke($reason, $revokedBy);

        return [
            'success'     => true,
            'message'     => 'Certificate has been revoked.',
            'certificate' => $certificate->fresh(),
        ];
    }

    /**
     * Reinstate a revoked certificate.
     */
    public function reinstate(Certificate $certificate): array
    {
        if (!$certificate->is_revoked) {
            return [
                'success' => false,
                'message' => 'Certificate is not revoked.',
            ];
        }

        $certificate->reinstate();

        return [
            'success'     => true,
            'message'     => 'Certificate has been reinstated.',
            'certificate' => $certificate->fresh(),
        ];
    }

    /**
     * Revoke certificates for a cancelled enrollment.
     */
    public function revokeForCancelledEnrollment(Enrollment $enrollment, ?int $revokedBy = null): int
    {
        $certificates = Certificate::where('user_id', $enrollment->user_id)
            ->where('course_id', $enrollment->course_id)
            ->where('is_revoked', false)
            ->get();

        foreach ($certificates as $certificate) {
            $certificate->revoke('Enrollment cancelled', $revokedBy);
        }

        return $certificates->count();
    }

    // ──────────────────────────────────────────────────────────────────
    // 7. Bulk Operations
    // ──────────────────────────────────────────────────────────────────

    /**
     * Bulk generate certificates for all completed enrollments in a course.
     */
    public function bulkGenerate(Course $course, array $options = []): array
    {
        $completedEnrollments = Enrollment::where('course_id', $course->id)
            ->where('status', Enrollment::STATUS_COMPLETED)
            ->with('user')
            ->get();

        $generated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($completedEnrollments as $enrollment) {
            $result = $this->generate($enrollment->user, $course, $options);

            if ($result['success'] && !($result['is_existing'] ?? false)) {
                $generated++;
            } elseif ($result['is_existing'] ?? false) {
                $skipped++;
            } else {
                $errors[] = [
                    'user_id' => $enrollment->user_id,
                    'message' => $result['message'],
                ];
            }
        }

        return [
            'success'   => true,
            'generated' => $generated,
            'skipped'   => $skipped,
            'errors'    => $errors,
            'total'     => $completedEnrollments->count(),
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────────────────────────────────────

    /**
     * Resolve the template settings to use for a certificate.
     */
    protected function resolveTemplate(Course $course, array $options): array
    {
        // 1. Explicit template ID in options
        if (!empty($options['template_id'])) {
            $template = CertificateTemplate::find($options['template_id']);
            if ($template) {
                return $template->toArray();
            }
        }

        // 2. Course-specific template slug
        if ($course->certificate_template) {
            $template = CertificateTemplate::where('slug', $course->certificate_template)->first();
            if ($template) {
                return $template->toArray();
            }
        }

        // 3. Default template
        $template = CertificateTemplate::default()->first();
        if ($template) {
            return $template->toArray();
        }

        // 4. Fallback defaults
        return [
            'design'                       => $options['template'] ?? Certificate::TEMPLATE_MODERN,
            'background_image'             => null,
            'logo_image'                   => null,
            'primary_color'                => '#6366f1',
            'secondary_color'              => '#818cf8',
            'text_color'                   => '#1e293b',
            'accent_color'                 => '#f59e0b',
            'font_family'                  => 'Inter',
            'instructor_signature_image'   => null,
            'organization_signature_image' => null,
            'organization_name'            => config('app.name'),
        ];
    }

    /**
     * Calculate student achievement data for the certificate.
     */
    protected function calculateAchievementData(User $user, Course $course, Enrollment $enrollment): array
    {
        // Average quiz score
        $quizScores = LessonProgress::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereNotNull('quiz_best_score')
            ->pluck('quiz_best_score');

        $averageScore = $quizScores->isNotEmpty() ? round($quizScores->avg(), 1) : null;

        // Total time spent
        $totalSeconds = LessonProgress::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->sum('time_spent_seconds');

        $totalHours = (int) ceil($totalSeconds / 3600);
        // Fall back to course duration
        if ($totalHours === 0) {
            $totalHours = $course->duration_hours ?? 0;
        }

        return [
            'average_score' => $averageScore,
            'total_hours'   => $totalHours,
        ];
    }
}
