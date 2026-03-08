<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request): View
    {
        $view = $request->get('view', 'grid');
        $search = $request->get('search');

        $query = Category::withCount('courses')
            ->with('parent')
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->ordered();

        // For admin: show all; for public: show only active
        if (!auth()->check() || !auth()->user()->isAdmin()) {
            $query->active();
        }

        $categories = $query->paginate(12)->withQueryString();

        // Category tree for sidebar
        $categoryTree = Category::rootLevel()
            ->active()
            ->ordered()
            ->withCount('courses')
            ->with(['children' => fn($q) => $q->active()->ordered()->withCount('courses')])
            ->get();

        return view('categories.index', compact('categories', 'categoryTree', 'view', 'search'));
    }

    /**
     * Show the form for creating a new category.
     */
    public function create(): View
    {
        $parentCategories = Category::active()
            ->rootLevel()
            ->ordered()
            ->with(['children' => fn($q) => $q->active()->ordered()])
            ->get();

        return view('categories.create', compact('parentCategories'));
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255', 'unique:categories,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,svg,webp', 'max:1024'],
            'parent_id'   => ['nullable', 'exists:categories,id'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        // Handle icon upload
        if ($request->hasFile('icon')) {
            $validated['icon'] = $request->file('icon')->store('categories', 'public');
        }

        $validated['is_active'] = $request->boolean('is_active', true);

        Category::create($validated);

        return redirect()->route('categories.index')
            ->with('success', 'Category created successfully.');
    }

    /**
     * Display the specified category with its courses.
     */
    public function show(Request $request, Category $category): View
    {
        $sort = $request->get('sort', 'latest');
        $level = $request->get('level');
        $pricing = $request->get('pricing');

        // Load child categories
        $category->load(['children' => fn($q) => $q->active()->ordered()->withCount('courses')]);
        $category->loadCount('courses');

        // Courses query with filters
        $coursesQuery = Course::where('category_id', $category->id)
            ->published()
            ->with(['instructor', 'category'])
            ->withCount(['enrollments', 'reviews'])
            ->withAvg('approvedReviews', 'rating');

        // Also include courses from child categories
        $childIds = $category->children->pluck('id')->toArray();
        if (!empty($childIds)) {
            $coursesQuery->orWhere(function ($q) use ($childIds) {
                $q->whereIn('category_id', $childIds)->where('status', 'published');
            });
        }

        // Sorting
        $coursesQuery = match ($sort) {
            'popular'   => $coursesQuery->orderByDesc('enrollments_count'),
            'rating'    => $coursesQuery->orderByDesc('approved_reviews_avg_rating'),
            'price_low' => $coursesQuery->orderBy('price'),
            'price_high'=> $coursesQuery->orderByDesc('price'),
            'title'     => $coursesQuery->orderBy('title'),
            default     => $coursesQuery->latest(),
        };

        // Level filter
        if ($level) {
            $coursesQuery->where('level', $level);
        }

        // Pricing filter
        if ($pricing === 'free') {
            $coursesQuery->where('is_free', true);
        } elseif ($pricing === 'paid') {
            $coursesQuery->where('is_free', false);
        }

        $courses = $coursesQuery->paginate(12)->withQueryString();

        // Sidebar categories
        $allCategories = Category::active()
            ->rootLevel()
            ->ordered()
            ->withCount('courses')
            ->get();

        return view('categories.show', compact('category', 'courses', 'allCategories', 'sort', 'level', 'pricing'));
    }

    /**
     * Show the form for editing the specified category.
     */
    public function edit(Category $category): View
    {
        $parentCategories = Category::active()
            ->where('id', '!=', $category->id)
            ->rootLevel()
            ->ordered()
            ->with(['children' => fn($q) => $q->active()->ordered()->where('id', '!=', $category->id)])
            ->get();

        return view('categories.edit', compact('category', 'parentCategories'));
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255', 'unique:categories,name,' . $category->id],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,svg,webp', 'max:1024'],
            'parent_id'   => ['nullable', 'exists:categories,id'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        // Prevent self-referencing
        if (isset($validated['parent_id']) && $validated['parent_id'] == $category->id) {
            return back()->withErrors(['parent_id' => 'A category cannot be its own parent.']);
        }

        // Handle icon upload
        if ($request->hasFile('icon')) {
            // Delete old icon
            if ($category->icon) {
                Storage::disk('public')->delete($category->icon);
            }
            $validated['icon'] = $request->file('icon')->store('categories', 'public');
        }

        $validated['is_active'] = $request->boolean('is_active', true);

        $category->update($validated);

        return redirect()->route('categories.index')
            ->with('success', 'Category updated successfully.');
    }

    /**
     * Soft delete the specified category.
     */
    public function destroy(Category $category): RedirectResponse
    {
        // Check if category has courses
        $courseCount = $category->courses()->count();
        if ($courseCount > 0) {
            return back()->with('error', "Cannot delete category. It has {$courseCount} course(s). Please reassign them first.");
        }

        $category->delete();

        return redirect()->route('categories.index')
            ->with('success', 'Category deleted successfully.');
    }

    /**
     * AJAX: Quick create a category (returns JSON).
     */
    public function quickStore(Request $request)
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255', 'unique:categories,name'],
            'parent_id' => ['nullable', 'exists:categories,id'],
        ]);

        $validated['is_active'] = true;

        $category = Category::create($validated);

        return response()->json([
            'success' => true,
            'category' => [
                'id'   => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'full_path' => $category->full_path,
            ],
        ], 201);
    }

    /**
     * AJAX: Search categories (returns JSON for select dropdowns).
     */
    public function search(Request $request)
    {
        $term = $request->get('q', '');

        $categories = Category::active()
            ->where('name', 'like', "%{$term}%")
            ->ordered()
            ->limit(20)
            ->get(['id', 'name', 'slug', 'parent_id']);

        return response()->json($categories);
    }

    /**
     * Bulk update sort order for categories (AJAX).
     */
    public function updateOrder(Request $request)
    {
        $request->validate([
            'order'      => ['required', 'array'],
            'order.*.id' => ['required', 'exists:categories,id'],
            'order.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($request->input('order') as $item) {
            Category::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['success' => true]);
    }
}
