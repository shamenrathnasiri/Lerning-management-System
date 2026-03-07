<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CertificateController extends Controller
{
    public function index(Request $request)
    {
        $query = Certificate::with('course:id,title,slug', 'user:id,name,username');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        return response()->json(
            $query->latest('issued_at')->paginate($request->integer('per_page', 15))
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'title' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $exists = Certificate::where('user_id', $validated['user_id'])
            ->where('course_id', $validated['course_id'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Certificate already exists for this user and course.'], 409);
        }

        $validated['certificate_number'] = 'LMS-' . strtoupper(Str::random(10));
        $validated['issued_at'] = now();

        $certificate = Certificate::create($validated);

        return response()->json($certificate->load('course:id,title', 'user:id,name'), 201);
    }

    public function show(Certificate $certificate)
    {
        return response()->json(
            $certificate->load('course:id,title,slug', 'user:id,name,username')
        );
    }

    public function download(Certificate $certificate)
    {
        $pdf = Pdf::loadView('certificates.template', [
            'certificate' => $certificate->load('course', 'user'),
        ]);

        return $pdf->download("certificate-{$certificate->certificate_number}.pdf");
    }

    public function verify(string $certificateNumber)
    {
        $certificate = Certificate::with('course:id,title', 'user:id,name')
            ->where('certificate_number', $certificateNumber)
            ->first();

        if (! $certificate) {
            return response()->json(['message' => 'Certificate not found.', 'valid' => false], 404);
        }

        $isValid = ! $certificate->expires_at || $certificate->expires_at->isFuture();

        return response()->json([
            'valid' => $isValid,
            'certificate' => $certificate,
        ]);
    }

    public function myCertificates(Request $request)
    {
        return response()->json(
            Certificate::with('course:id,title,slug')
                ->where('user_id', $request->user()->id)
                ->latest('issued_at')
                ->paginate($request->integer('per_page', 15))
        );
    }

    public function destroy(Certificate $certificate)
    {
        $certificate->delete();

        return response()->json(['message' => 'Certificate deleted.']);
    }
}
