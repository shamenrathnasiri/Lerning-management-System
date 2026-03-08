<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center space-x-2 text-sm">
            <a href="{{ route('tags.index') }}" class="text-gray-500 hover:text-gray-700">Tags</a>
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-gray-800 font-semibold">{{ $tag->name }}</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row gap-8">

                {{-- Sidebar --}}
                <aside class="lg:w-64 flex-shrink-0">
                    {{-- Tag Info --}}
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
                        <div class="flex items-center space-x-2">
                            <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                            <h2 class="font-bold text-gray-900">{{ $tag->name }}</h2>
                        </div>
                        <p class="text-sm text-gray-500 mt-2">{{ $courses->total() }} course{{ $courses->total() !== 1 ? 's' : '' }} tagged</p>
                    </div>

                    {{-- Related Tags --}}
                    @if ($relatedTags->isNotEmpty())
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                            <h3 class="font-semibold text-gray-900 text-sm mb-3">Related Tags</h3>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($relatedTags as $related)
                                    <a href="{{ route('tags.show', $related) }}" class="inline-flex items-center px-3 py-1 rounded-full border border-gray-200 text-xs text-gray-600 hover:bg-indigo-50 hover:border-indigo-300 hover:text-indigo-700 transition-colors">
                                        {{ $related->name }}
                                        <span class="ml-1 text-gray-400">({{ $related->courses_count }})</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </aside>

                {{-- Main Content --}}
                <div class="flex-1 min-w-0">
                    {{-- Sort Bar --}}
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
                        <form method="GET" action="{{ route('tags.show', $tag) }}" class="flex items-center justify-between">
                            <select name="sort" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" onchange="this.form.submit()">
                                <option value="latest" {{ $sort === 'latest' ? 'selected' : '' }}>Latest</option>
                                <option value="popular" {{ $sort === 'popular' ? 'selected' : '' }}>Most Popular</option>
                                <option value="rating" {{ $sort === 'rating' ? 'selected' : '' }}>Highest Rated</option>
                                <option value="price_low" {{ $sort === 'price_low' ? 'selected' : '' }}>Price: Low to High</option>
                                <option value="price_high" {{ $sort === 'price_high' ? 'selected' : '' }}>Price: High to Low</option>
                                <option value="title" {{ $sort === 'title' ? 'selected' : '' }}>A-Z</option>
                            </select>
                            <span class="text-sm text-gray-500">{{ $courses->total() }} result{{ $courses->total() !== 1 ? 's' : '' }}</span>
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
                                </div>
                                <div class="p-4">
                                    <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $course->level === 'beginner' ? 'bg-green-100 text-green-700' : ($course->level === 'intermediate' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                        {{ ucfirst($course->level) }}
                                    </span>
                                    <h3 class="mt-2 font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors line-clamp-2 text-sm">
                                        {{ $course->title }}
                                    </h3>
                                    <p class="text-xs text-gray-500 mt-1">by {{ $course->instructor->name }}</p>

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

                                    <div class="flex items-center space-x-3 mt-2 text-xs text-gray-500">
                                        <span>{{ $course->enrollments_count }} students</span>
                                        <span>•</span>
                                        <span>{{ $course->formatted_duration }}</span>
                                    </div>

                                    <div class="mt-3">
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
                        @empty
                            <div class="col-span-full text-center py-12">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No courses with this tag</h3>
                                <p class="mt-1 text-sm text-gray-500">Check back later or browse other tags.</p>
                            </div>
                        @endforelse
                    </div>

                    <div class="mt-6">
                        {{ $courses->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
