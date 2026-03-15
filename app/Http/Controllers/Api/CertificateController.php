<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Services\CertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    public function __construct(
        protected CertificateService $certificateService
    ) {}

    // ──────────────────────────────────────────────────────────────────
    // List & View
    // ──────────────────────────────────────────────────────────────────

    /**
     * GET /certificates
     * List certificates with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Certificate::with('course:id,title,slug', 'user:id,name,username');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->filled('template')) {
            $query->withTemplate($request->template);
        }

        if ($request->filled('status')) {
            match ($request->status) {
                'valid'   => $query->valid(),
                'revoked' => $query->revoked(),
                'expired' => $query->expired(),
                default   => null,
            };
        }

        return response()->json(
            $query->latest('issued_at')->paginate($request->integer('per_page', 15))
        );
    }

    /**
     * GET /certificates/{certificate}
     * Show certificate details.
     */
    public function show(Certificate $certificate): JsonResponse
    {
        return response()->json(
            $certificate->load([
                'course:id,title,slug,level,instructor_id',
                'course.instructor:id,name',
                'user:id,name,username,email',
                'revokedByUser:id,name',
            ])
        );
    }

    /**
     * GET /my-certificates
     * Get authenticated user's certificates.
     */
    public function myCertificates(Request $request): JsonResponse
    {
        return response()->json(
            Certificate::with('course:id,title,slug')
                ->where('user_id', $request->user()->id)
                ->latest('issued_at')
                ->paginate($request->integer('per_page', 15))
        );
    }

    // ──────────────────────────────────────────────────────────────────
    // Generate
    // ──────────────────────────────────────────────────────────────────

    /**
     * POST /certificates/generate
     * Generate a certificate for a completed course.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id'    => ['required', 'exists:courses,id'],
            'user_id'      => ['sometimes', 'exists:users,id'],
            'template'     => ['sometimes', 'in:' . implode(',', Certificate::TEMPLATES)],
            'template_id'  => ['sometimes', 'exists:certificate_templates,id'],
            'title'        => ['sometimes', 'string', 'max:255'],
            'expires_at'   => ['sometimes', 'date', 'after:today'],
        ]);

        $course = Course::findOrFail($validated['course_id']);
        $user = isset($validated['user_id'])
            ? \App\Models\User::findOrFail($validated['user_id'])
            : $request->user();

        $result = $this->certificateService->generate($user, $course, $validated);

        $statusCode = $result['success'] ? 201 : 422;

        return response()->json($result, $statusCode);
    }

    /**
     * POST /certificates/bulk-generate
     * Bulk generate certificates for all completed students in a course.
     */
    public function bulkGenerate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id'   => ['required', 'exists:courses,id'],
            'template'    => ['sometimes', 'in:' . implode(',', Certificate::TEMPLATES)],
            'template_id' => ['sometimes', 'exists:certificate_templates,id'],
        ]);

        $course = Course::findOrFail($validated['course_id']);
        $result = $this->certificateService->bulkGenerate($course, $validated);

        return response()->json($result);
    }

    // ──────────────────────────────────────────────────────────────────
    // Download & Preview
    // ──────────────────────────────────────────────────────────────────

    /**
     * GET /certificates/{certificate}/download
     * Download the certificate PDF.
     */
    public function download(Certificate $certificate)
    {
        if ($certificate->is_revoked) {
            return response()->json([
                'success' => false,
                'message' => 'This certificate has been revoked and cannot be downloaded.',
            ], 403);
        }

        return $this->certificateService->downloadPdf($certificate);
    }

    /**
     * GET /certificates/{certificate}/preview
     * Stream a preview PDF with watermark.
     */
    public function preview(Certificate $certificate)
    {
        return $this->certificateService->previewPdf($certificate);
    }

    /**
     * POST /certificates/{certificate}/regenerate
     * Regenerate the certificate PDF.
     */
    public function regenerate(Certificate $certificate): JsonResponse
    {
        $updated = $this->certificateService->regeneratePdf($certificate);

        return response()->json([
            'success'     => true,
            'message'     => 'Certificate PDF regenerated.',
            'certificate' => $updated,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Verification (Public)
    // ──────────────────────────────────────────────────────────────────

    /**
     * GET /certificates/verify/{certificateNumber}
     * Public certificate verification.
     */
    public function verify(string $certificateNumber): JsonResponse
    {
        return response()->json(
            $this->certificateService->verify($certificateNumber)
        );
    }

    /**
     * GET /certificates/share/{shareToken}
     * Verify via share token (public).
     */
    public function verifyByToken(string $shareToken): JsonResponse
    {
        return response()->json(
            $this->certificateService->verifyByShareToken($shareToken)
        );
    }

    // ──────────────────────────────────────────────────────────────────
    // Sharing
    // ──────────────────────────────────────────────────────────────────

    /**
     * GET /certificates/{certificate}/share
     * Get social sharing links and metadata.
     */
    public function share(Certificate $certificate): JsonResponse
    {
        if ($certificate->is_revoked) {
            return response()->json([
                'success' => false,
                'message' => 'Revoked certificates cannot be shared.',
            ], 403);
        }

        return response()->json(
            $this->certificateService->getShareData($certificate)
        );
    }

    // ──────────────────────────────────────────────────────────────────
    // Revocation
    // ──────────────────────────────────────────────────────────────────

    /**
     * POST /certificates/{certificate}/revoke
     * Revoke a certificate.
     */
    public function revoke(Request $request, Certificate $certificate): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $result = $this->certificateService->revoke(
            $certificate,
            $validated['reason'],
            $request->user()->id
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * POST /certificates/{certificate}/reinstate
     * Reinstate a revoked certificate.
     */
    public function reinstate(Certificate $certificate): JsonResponse
    {
        $result = $this->certificateService->reinstate($certificate);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    // ──────────────────────────────────────────────────────────────────
    // Certificate Templates
    // ──────────────────────────────────────────────────────────────────

    /**
     * GET /certificate-templates
     * List available certificate templates.
     */
    public function templates(Request $request): JsonResponse
    {
        $query = CertificateTemplate::query();

        if ($request->filled('design')) {
            $query->ofDesign($request->design);
        }

        $query->where('is_active', true);

        return response()->json($query->get());
    }

    /**
     * POST /certificate-templates
     * Create a new certificate template.
     */
    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                         => ['required', 'string', 'max:255'],
            'design'                       => ['required', 'in:' . implode(',', Certificate::TEMPLATES)],
            'background_image'             => ['sometimes', 'string'],
            'logo_image'                   => ['sometimes', 'string'],
            'primary_color'                => ['sometimes', 'string', 'max:20'],
            'secondary_color'              => ['sometimes', 'string', 'max:20'],
            'text_color'                   => ['sometimes', 'string', 'max:20'],
            'accent_color'                 => ['sometimes', 'string', 'max:20'],
            'font_family'                  => ['sometimes', 'string', 'max:100'],
            'instructor_signature_image'   => ['sometimes', 'string'],
            'organization_signature_image' => ['sometimes', 'string'],
            'organization_name'            => ['sometimes', 'string', 'max:255'],
            'custom_message'               => ['sometimes', 'string', 'max:500'],
            'show_grade'                   => ['sometimes', 'boolean'],
            'show_hours'                   => ['sometimes', 'boolean'],
            'show_qr'                      => ['sometimes', 'boolean'],
            'is_default'                   => ['sometimes', 'boolean'],
        ]);

        // If setting as default, unset existing defaults
        if (!empty($validated['is_default'])) {
            CertificateTemplate::where('is_default', true)
                ->update(['is_default' => false]);
        }

        $template = CertificateTemplate::create($validated);

        return response()->json($template, 201);
    }

    /**
     * PUT /certificate-templates/{template}
     * Update a certificate template.
     */
    public function updateTemplate(Request $request, CertificateTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'name'                         => ['sometimes', 'string', 'max:255'],
            'design'                       => ['sometimes', 'in:' . implode(',', Certificate::TEMPLATES)],
            'primary_color'                => ['sometimes', 'string', 'max:20'],
            'secondary_color'              => ['sometimes', 'string', 'max:20'],
            'text_color'                   => ['sometimes', 'string', 'max:20'],
            'accent_color'                 => ['sometimes', 'string', 'max:20'],
            'font_family'                  => ['sometimes', 'string', 'max:100'],
            'organization_name'            => ['sometimes', 'string', 'max:255'],
            'custom_message'               => ['sometimes', 'string', 'max:500'],
            'show_grade'                   => ['sometimes', 'boolean'],
            'show_hours'                   => ['sometimes', 'boolean'],
            'show_qr'                      => ['sometimes', 'boolean'],
            'is_default'                   => ['sometimes', 'boolean'],
            'is_active'                    => ['sometimes', 'boolean'],
        ]);

        if (!empty($validated['is_default'])) {
            CertificateTemplate::where('is_default', true)
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);
        }

        $template->update($validated);

        return response()->json($template->fresh());
    }

    /**
     * DELETE /certificates/{certificate}
     * Soft-delete a certificate.
     */
    public function destroy(Certificate $certificate): JsonResponse
    {
        $certificate->delete();

        return response()->json(['message' => 'Certificate deleted.']);
    }
}
