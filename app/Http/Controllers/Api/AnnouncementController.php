<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $query = Announcement::with('author:id,name,username', 'course:id,title,slug');

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->filled('audience')) {
            $query->where('audience', $request->audience);
        }

        $query->where('is_published', true);

        return response()->json(
            $query->latest('published_at')->paginate($request->integer('per_page', 15))
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => ['nullable', 'exists:courses,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'audience' => ['nullable', 'in:all,students,instructors'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $validated['user_id'] = $request->user()->id;

        if (! empty($validated['is_published'])) {
            $validated['published_at'] = now();
        }

        $announcement = Announcement::create($validated);

        return response()->json($announcement->load('author:id,name'), 201);
    }

    public function show(Announcement $announcement)
    {
        return response()->json(
            $announcement->load('author:id,name,username', 'course:id,title')
        );
    }

    public function update(Request $request, Announcement $announcement)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'audience' => ['nullable', 'in:all,students,instructors'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        if (isset($validated['is_published']) && $validated['is_published'] && ! $announcement->published_at) {
            $validated['published_at'] = now();
        }

        $announcement->update($validated);

        return response()->json($announcement);
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();

        return response()->json(['message' => 'Announcement deleted.']);
    }
}
