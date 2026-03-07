<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Enrollment::with('course:id,title,slug,thumbnail', 'user:id,name,username');

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(
            $query->latest()->paginate($request->integer('per_page', 15))
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
        ]);

        $exists = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $validated['course_id'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Already enrolled in this course.'], 409);
        }

        $enrollment = Enrollment::create([
            'user_id' => $request->user()->id,
            'course_id' => $validated['course_id'],
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        return response()->json($enrollment->load('course:id,title,slug'), 201);
    }

    public function show(Enrollment $enrollment)
    {
        return response()->json(
            $enrollment->load('course:id,title,slug,thumbnail', 'user:id,name,username')
        );
    }

    public function myEnrollments(Request $request)
    {
        return response()->json(
            Enrollment::with('course:id,title,slug,thumbnail,instructor_id', 'course.instructor:id,name')
                ->where('user_id', $request->user()->id)
                ->latest()
                ->paginate($request->integer('per_page', 15))
        );
    }

    public function updateStatus(Request $request, Enrollment $enrollment)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,completed,expired,cancelled'],
        ]);

        $enrollment->update([
            'status' => $validated['status'],
            'completed_at' => $validated['status'] === 'completed' ? now() : $enrollment->completed_at,
        ]);

        return response()->json($enrollment);
    }

    public function destroy(Enrollment $enrollment)
    {
        $enrollment->delete();

        return response()->json(['message' => 'Enrollment cancelled.']);
    }
}
