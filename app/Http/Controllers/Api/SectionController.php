<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Section;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    public function index(Course $course)
    {
        return response()->json(
            $course->sections()->with('lessons:id,section_id,title,slug,type,duration_minutes,sort_order,is_free_preview,is_published')
                ->orderBy('sort_order')
                ->get()
        );
    }

    public function store(Request $request, Course $course)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['sort_order'] = $validated['sort_order']
            ?? ($course->sections()->max('sort_order') + 1);

        $section = $course->sections()->create($validated);

        return response()->json($section, 201);
    }

    public function show(Course $course, Section $section)
    {
        return response()->json(
            $section->load('lessons:id,section_id,title,slug,type,duration_minutes,sort_order,is_free_preview,is_published')
        );
    }

    public function update(Request $request, Course $course, Section $section)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $section->update($validated);

        return response()->json($section);
    }

    public function destroy(Course $course, Section $section)
    {
        $section->delete();

        return response()->json(['message' => 'Section deleted.']);
    }
}
