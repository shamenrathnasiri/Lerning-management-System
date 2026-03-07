<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = Review::with('user:id,name,username,avatar', 'course:id,title,slug');

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->filled('is_approved')) {
            $query->where('is_approved', $request->boolean('is_approved'));
        } else {
            $query->where('is_approved', true);
        }

        return response()->json(
            $query->latest()->paginate($request->integer('per_page', 15))
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ]);

        $exists = Review::where('user_id', $request->user()->id)
            ->where('course_id', $validated['course_id'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'You have already reviewed this course.'], 409);
        }

        $validated['user_id'] = $request->user()->id;

        $review = Review::create($validated);

        return response()->json($review->load('user:id,name,username'), 201);
    }

    public function show(Review $review)
    {
        return response()->json(
            $review->load('user:id,name,username,avatar', 'course:id,title')
        );
    }

    public function update(Request $request, Review $review)
    {
        $validated = $request->validate([
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ]);

        $review->update($validated);

        return response()->json($review);
    }

    public function approve(Review $review)
    {
        $review->update(['is_approved' => true]);

        return response()->json($review);
    }

    public function destroy(Review $review)
    {
        $review->delete();

        return response()->json(['message' => 'Review deleted.']);
    }
}
