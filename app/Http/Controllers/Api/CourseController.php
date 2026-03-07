<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $query = Course::with('instructor:id,name,username,avatar', 'category:id,name,slug', 'tags:id,name,slug')
            ->withCount('enrollments', 'lessons', 'reviews')
            ->withAvg('reviews', 'rating');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'published');
        }

        if ($request->boolean('is_free')) {
            $query->where('is_free', true);
        }

        if ($request->boolean('is_featured')) {
            $query->where('is_featured', true);
        }

        if ($request->filled('instructor_id')) {
            $query->where('instructor_id', $request->instructor_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $sortField = $request->input('sort', 'created_at');
        $sortDir = $request->input('direction', 'desc');
        $allowed = ['created_at', 'title', 'price', 'published_at'];

        if (in_array($sortField, $allowed)) {
            $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        return response()->json(
            $query->paginate($request->integer('per_page', 15))
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],
            'what_you_will_learn' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'thumbnail' => ['nullable', 'string', 'max:255'],
            'intro_video' => ['nullable', 'string', 'max:255'],
            'level' => ['nullable', 'in:beginner,intermediate,advanced,all_levels'],
            'language' => ['nullable', 'string', 'max:10'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0'],
            'is_free' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
        ]);

        $validated['instructor_id'] = $request->user()->id;
        $validated['status'] = 'draft';

        $tags = $validated['tags'] ?? [];
        unset($validated['tags']);

        $course = Course::create($validated);

        if (! empty($tags)) {
            $course->tags()->sync($tags);
        }

        return response()->json(
            $course->load('instructor:id,name,username', 'category:id,name', 'tags:id,name'),
            201
        );
    }

    public function show(Course $course)
    {
        return response()->json(
            $course->load(
                'instructor:id,name,username,avatar,bio,expertise',
                'category:id,name,slug',
                'tags:id,name,slug',
                'sections.lessons:id,section_id,course_id,title,slug,type,duration_minutes,sort_order,is_free_preview,is_published'
            )->loadCount('enrollments', 'lessons', 'reviews')
              ->loadAvg('reviews', 'rating')
        );
    }

    public function update(Request $request, Course $course)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],
            'what_you_will_learn' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'thumbnail' => ['nullable', 'string', 'max:255'],
            'intro_video' => ['nullable', 'string', 'max:255'],
            'level' => ['nullable', 'in:beginner,intermediate,advanced,all_levels'],
            'language' => ['nullable', 'string', 'max:10'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0'],
            'is_free' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:draft,pending,published,archived'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
        ]);

        if (isset($validated['tags'])) {
            $course->tags()->sync($validated['tags']);
            unset($validated['tags']);
        }

        if (isset($validated['status']) && $validated['status'] === 'published' && ! $course->published_at) {
            $validated['published_at'] = now();
        }

        $course->update($validated);

        return response()->json(
            $course->load('instructor:id,name,username', 'category:id,name', 'tags:id,name')
        );
    }

    public function destroy(Course $course)
    {
        $course->delete();

        return response()->json(['message' => 'Course deleted.']);
    }
}
