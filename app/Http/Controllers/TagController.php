<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TagController extends Controller
{
    /**
     * Display a listing of tags.
     */
    public function index(Request $request): View
    {
        $search = $request->get('search');
        $sort = $request->get('sort', 'name');

        $query = Tag::withCount('courses')
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"));

        $query = match ($sort) {
            'popular'  => $query->orderByDesc('courses_count'),
            'latest'   => $query->latest(),
            'oldest'   => $query->oldest(),
            default    => $query->orderBy('name'),
        };

        $tags = $query->paginate(24)->withQueryString();

        // Stats
        $totalTags = Tag::count();
        $activeTags = Tag::has('courses')->count();

        return view('tags.index', compact('tags', 'search', 'sort', 'totalTags', 'activeTags'));
    }

    /**
     * Show the form for creating a new tag.
     */
    public function create(): View
    {
        return view('tags.create');
    }

    /**
     * Store a newly created tag.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:tags,name'],
        ]);

        Tag::create($validated);

        return redirect()->route('tags.index')
            ->with('success', 'Tag created successfully.');
    }

    /**
     * Display the specified tag with its courses.
     */
    public function show(Request $request, Tag $tag): View
    {
        $sort = $request->get('sort', 'latest');

        $coursesQuery = $tag->courses()
            ->published()
            ->with(['instructor', 'category'])
            ->withCount(['enrollments', 'reviews'])
            ->withAvg('approvedReviews', 'rating');

        $coursesQuery = match ($sort) {
            'popular'   => $coursesQuery->orderByDesc('enrollments_count'),
            'rating'    => $coursesQuery->orderByDesc('approved_reviews_avg_rating'),
            'price_low' => $coursesQuery->orderBy('price'),
            'price_high'=> $coursesQuery->orderByDesc('price'),
            'title'     => $coursesQuery->orderBy('title'),
            default     => $coursesQuery->latest(),
        };

        $courses = $coursesQuery->paginate(12)->withQueryString();

        // Related tags (tags that share courses with this tag)
        $relatedTags = Tag::whereHas('courses', function ($q) use ($tag) {
            $q->whereIn('courses.id', $tag->courses()->pluck('courses.id'));
        })->where('id', '!=', $tag->id)
            ->withCount('courses')
            ->orderByDesc('courses_count')
            ->limit(10)
            ->get();

        return view('tags.show', compact('tag', 'courses', 'relatedTags', 'sort'));
    }

    /**
     * Show the form for editing the specified tag.
     */
    public function edit(Tag $tag): View
    {
        return view('tags.edit', compact('tag'));
    }

    /**
     * Update the specified tag.
     */
    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:tags,name,' . $tag->id],
        ]);

        $tag->update($validated);

        return redirect()->route('tags.index')
            ->with('success', 'Tag updated successfully.');
    }

    /**
     * Soft delete the specified tag.
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        // Detach from all courses first
        $tag->courses()->detach();
        $tag->delete();

        return redirect()->route('tags.index')
            ->with('success', 'Tag deleted successfully.');
    }

    /**
     * AJAX: Quick create a tag (returns JSON).
     */
    public function quickStore(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:tags,name'],
        ]);

        $tag = Tag::create($validated);

        return response()->json([
            'success' => true,
            'tag' => [
                'id'   => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ],
        ], 201);
    }

    /**
     * AJAX: Search tags (for autocomplete / select2).
     */
    public function search(Request $request)
    {
        $term = $request->get('q', '');

        $tags = Tag::where('name', 'like', "%{$term}%")
            ->withCount('courses')
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'slug']);

        return response()->json($tags);
    }

    /**
     * Bulk delete tags (AJAX).
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['exists:tags,id'],
        ]);

        $tags = Tag::whereIn('id', $request->input('ids'))->get();

        foreach ($tags as $tag) {
            $tag->courses()->detach();
            $tag->delete();
        }

        return response()->json([
            'success' => true,
            'message' => count($request->input('ids')) . ' tag(s) deleted.',
        ]);
    }
}
