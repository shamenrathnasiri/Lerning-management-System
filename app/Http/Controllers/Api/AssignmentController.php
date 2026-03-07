<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Assignment::with('course:id,title,slug')
            ->withCount('submissions');

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        return response()->json(
            $query->latest()->paginate($request->integer('per_page', 15))
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'lesson_id' => ['nullable', 'exists:lessons,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
            'max_score' => ['nullable', 'integer', 'min:1'],
            'due_date' => ['nullable', 'date', 'after:now'],
            'max_file_size_mb' => ['nullable', 'integer', 'min:1'],
            'allowed_file_types' => ['nullable', 'array'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $assignment = Assignment::create($validated);

        return response()->json($assignment, 201);
    }

    public function show(Assignment $assignment)
    {
        return response()->json(
            $assignment->load('course:id,title,slug')->loadCount('submissions')
        );
    }

    public function update(Request $request, Assignment $assignment)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
            'max_score' => ['nullable', 'integer', 'min:1'],
            'due_date' => ['nullable', 'date'],
            'max_file_size_mb' => ['nullable', 'integer', 'min:1'],
            'allowed_file_types' => ['nullable', 'array'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $assignment->update($validated);

        return response()->json($assignment);
    }

    public function destroy(Assignment $assignment)
    {
        $assignment->delete();

        return response()->json(['message' => 'Assignment deleted.']);
    }

    public function submissions(Assignment $assignment)
    {
        return response()->json(
            $assignment->submissions()
                ->with('user:id,name,username,avatar')
                ->latest('submitted_at')
                ->paginate(15)
        );
    }

    public function submit(Request $request, Assignment $assignment)
    {
        $validated = $request->validate([
            'content' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
        ]);

        $existing = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing && $existing->status === 'graded') {
            $validated['status'] = 'resubmitted';
        }

        $submission = AssignmentSubmission::updateOrCreate(
            [
                'assignment_id' => $assignment->id,
                'user_id' => $request->user()->id,
            ],
            array_merge($validated, [
                'submitted_at' => now(),
                'status' => $validated['status'] ?? 'submitted',
            ])
        );

        return response()->json($submission, 201);
    }

    public function grade(Request $request, Assignment $assignment, AssignmentSubmission $submission)
    {
        $validated = $request->validate([
            'score' => ['required', 'numeric', 'min:0', 'max:' . $assignment->max_score],
            'feedback' => ['nullable', 'string'],
        ]);

        $submission->update([
            'score' => $validated['score'],
            'feedback' => $validated['feedback'] ?? null,
            'graded_by' => $request->user()->id,
            'graded_at' => now(),
            'status' => 'graded',
        ]);

        return response()->json($submission->load('grader:id,name'));
    }
}
