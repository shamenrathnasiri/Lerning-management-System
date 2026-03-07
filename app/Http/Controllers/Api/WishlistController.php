<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Wishlist::with('course:id,title,slug,thumbnail,price,discount_price,instructor_id', 'course.instructor:id,name')
                ->where('user_id', $request->user()->id)
                ->latest()
                ->paginate($request->integer('per_page', 15))
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
        ]);

        $exists = Wishlist::where('user_id', $request->user()->id)
            ->where('course_id', $validated['course_id'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Course already in wishlist.'], 409);
        }

        $wishlist = Wishlist::create([
            'user_id' => $request->user()->id,
            'course_id' => $validated['course_id'],
        ]);

        return response()->json($wishlist->load('course:id,title,slug'), 201);
    }

    public function destroy(Request $request, int $courseId)
    {
        Wishlist::where('user_id', $request->user()->id)
            ->where('course_id', $courseId)
            ->delete();

        return response()->json(['message' => 'Removed from wishlist.']);
    }
}
