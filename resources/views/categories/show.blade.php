<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center space-x-2 text-sm">
            <a href="{{ route('categories.index') }}" class="text-gray-500 hover:text-gray-700">Categories</a>
            @if ($category->parent)
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <a href="{{ route('categories.show', $category->parent) }}" class="text-gray-500 hover:text-gray-700">{{ $category->parent->name }}</a>
            @endif
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-gray-800 font-semibold">{{ $category->name }}</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row gap-8">

                {{-- Sidebar --}}
                <aside class="lg:w-64 flex-shrink-0">
                    {{-- Category Info Card --}}
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
                        <div class="flex items-center space-x-3">
                            <div class="h-12 w-12 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center flex-shrink-0">
                                @if ($category->icon)
                                    <img src="{{ asset('storage/' . $category->icon) }}" alt="" class="h-8 w-8 object-contain">
                                @else
                                    <span class="text-white font-bold text-lg">{{ strtoupper(substr($category->name, 0, 1)) }}</span>
                                @endif
                            </div>
                            <div>
                                <h2 class="font-bold text-gray-900">{{ $category->name }}</h2>
                                <p class="text-xs text-gray-500">{{ $category->courses_count }} course{{ $category->courses_count !== 1 ? 's' : '' }}</p>
                            </div>
                        </div>
                        @if ($category->description)
                            <p class="mt-3 text-sm text-gray-600">{{ $category->description }}</p>
                        @endif
                    </div>

                    {{-- Sub-categories --}}
                    @if ($category->children->isNotEmpty())
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
                            <h3 class="font-semibold text-gray-900 text-sm mb-3">Sub-categories</h3>
                            <ul class="space-y-2">
                                @foreach ($category->children as $child)
                                    <li>
                                        <a href="{{ route('categories.show', $child) }}" class="flex items-center justify-between text-sm text-gray-600 hover:text-indigo-600 transition-colors p-1.5 rounded-md hover:bg-indigo-50">
                                            <span>{{ $child->name }}</span>
                                            <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full">{{ $child->courses_count }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- All Categories --}}
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                        <h3 class="font-semibold text-gray-900 text-sm mb-3">All Categories</h3>
                        <ul class="space-y-1">
                            @foreach ($allCategories as $cat)
                                <li>
                                    <a href="{{ route('categories.show', $cat) }}"
                                       class="flex items-center justify-between text-sm p-1.5 rounded-md transition-colors {{ $cat->id === $category->id ? 'bg-indigo-50 text-indigo-600 font-medium' : 'text-gray-600 hover:text-indigo-600 hover:bg-indigo-50' }}">
                                        <span>{{ $cat->name }}</span>
                                        <span class="bg-gray-100 text-gray-500 text-xs px-2 py-0.5 rounded-full">{{ $cat->courses_count }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </aside>

                {{-- Main Content --}}
                <div class="flex-1 min-w-0">
                    {{-- Filters Bar --}}
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
                        <form method="GET" action="{{ route('categories.show', $category) }}" class="flex flex-wrap items-center gap-3">
                            {{-- Sort --}}
                            <select name="sort" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" onchange="this.form.submit()">
                                <option value="latest" {{ $sort === 'latest' ? 'selected' : '' }}>Latest</option>
                                <option value="popular" {{ $sort === 'popular' ? 'selected' : '' }}>Most Popular</option>
                                <option value="rating" {{ $sort === 'rating' ? 'selected' : '' }}>Highest Rated</option>
                                <option value="price_low" {{ $sort === 'price_low' ? 'selected' : '' }}>Price: Low to High</option>
                                <option value="price_high" {{ $sort === 'price_high' ? 'selected' : '' }}>Price: High to Low</option>
                                <option value="title" {{ $sort === 'title' ? 'selected' : '' }}>A-Z</option>
                            </select>

                            {{-- Level Filter --}}
                            <select name="level" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" onchange="this.form.submit()">
                                <option value="">All Levels</option>
                                <option value="beginner" {{ $level === 'beginner' ? 'selected' : '' }}>Beginner</option>
                                <option value="intermediate" {{ $level === 'intermediate' ? 'selected' : '' }}>Intermediate</option>
                                <option value="advanced" {{ $level === 'advanced' ? 'selected' : '' }}>Advanced</option>
                            </select>

                            {{-- Pricing Filter --}}
                            <select name="pricing" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" onchange="this.form.submit()">
                                <option value="">All Prices</option>
                                <option value="free" {{ $pricing === 'free' ? 'selected' : '' }}>Free</option>
                                <option value="paid" {{ $pricing === 'paid' ? 'selected' : '' }}>Paid</option>
                            </select>

                            @if ($level || $pricing || $sort !== 'latest')
                                <a href="{{ route('categories.show', $category) }}" class="text-xs text-red-500 hover:text-red-700">Clear filters</a>
                            @endif

                            <span class="ml-auto text-sm text-gray-500">{{ $courses->total() }} course{{ $courses->total() !== 1 ? 's' : '' }}</span>
                        </form>
                    </div>

                    {{-- Course Grid --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
                        @forelse ($courses as $course)
                            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow group">
                                <div class="relative">
                                    <img src="{{ $course->thumbnail_url }}" alt="{{ $course->title }}" class="w-full h-40 object-cover">
                                    @if ($course->is_featured)
                                        <span class="absolute top-2 left-2 px-2 py-0.5 bg-yellow-400 text-yellow-900 text-xs font-bold rounded-full">Featured</span>
                                    @endif
                                    @if ($course->discount_percentage)
                                        <span class="absolute top-2 right-2 px-2 py-0.5 bg-red-500 text-white text-xs font-bold rounded-full">-{{ $course->discount_percentage }}%</span>
                                    @endif
                                </div>
                                <div class="p-4">
                                    <div class="flex items-center space-x-2 mb-2">
                                        <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $course->level === 'beginner' ? 'bg-green-100 text-green-700' : ($course->level === 'intermediate' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                            {{ ucfirst($course->level) }}
                                        </span>
                                    </div>
                                    <h3 class="font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors line-clamp-2 text-sm">
                                        {{ $course->title }}
                                    </h3>
                                    <p class="text-xs text-gray-500 mt-1">by {{ $course->instructor->name }}</p>

                                    {{-- Rating --}}
                                    <div class="flex items-center mt-2">
                                        <span class="text-sm font-bold text-yellow-500">{{ number_format($course->approved_reviews_avg_rating ?? 0, 1) }}</span>
                                        <div class="flex ml-1">
                                            @for ($i = 1; $i <= 5; $i++)
                                                <svg class="w-3.5 h-3.5 {{ $i <= round($course->approved_reviews_avg_rating ?? 0) ? 'text-yellow-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                </svg>
                                            @endfor
                                        </div>
                                        <span class="text-xs text-gray-400 ml-1">({{ $course->reviews_count }})</span>
                                    </div>

                                    {{-- Stats --}}
                                    <div class="flex items-center space-x-3 mt-2 text-xs text-gray-500">
                                        <span>{{ $course->enrollments_count }} students</span>
                                        <span>•</span>
                                        <span>{{ $course->formatted_duration }}</span>
                                    </div>

                                    {{-- Price --}}
                                    <div class="mt-3 flex items-center justify-between">
                                        <div>
                                            @if ($course->is_free)
                                                <span class="text-lg font-bold text-green-600">Free</span>
                                            @elseif ($course->discount_price)
                                                <span class="text-lg font-bold text-gray-900">${{ number_format($course->discount_price, 2) }}</span>
                                                <span class="text-sm text-gray-400 line-through ml-1">${{ number_format($course->price, 2) }}</span>
                                            @else
                                                <span class="text-lg font-bold text-gray-900">${{ number_format($course->price, 2) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full text-center py-12">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No courses in this category</h3>
                                <p class="mt-1 text-sm text-gray-500">Check back later or browse other categories.</p>
                            </div>
                        @endforelse
                    </div>

                    {{-- Pagination --}}
                    <div class="mt-6">
                        {{ $courses->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
