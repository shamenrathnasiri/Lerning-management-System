<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold leading-tight text-gray-800">
                {{ __('Tags') }}
            </h1>
            @can('manage tags')
                <div class="flex items-center space-x-2">
                    <button onclick="document.getElementById('quick-tag-modal').classList.remove('hidden')" class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Quick Add
                    </button>
                    <a href="{{ route('tags.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        New Tag
                    </a>
                </div>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Flash Messages --}}
            @if (session('success'))
                <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    {{ session('success') }}
                </div>
            @endif

            {{-- Stats --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
                    <p class="text-2xl font-bold text-indigo-600">{{ $totalTags }}</p>
                    <p class="text-xs text-gray-500 mt-1">Total Tags</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
                    <p class="text-2xl font-bold text-green-600">{{ $activeTags }}</p>
                    <p class="text-xs text-gray-500 mt-1">With Courses</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center hidden sm:block">
                    <p class="text-2xl font-bold text-gray-600">{{ $totalTags - $activeTags }}</p>
                    <p class="text-xs text-gray-500 mt-1">Unused</p>
                </div>
            </div>

            {{-- Search & Sort --}}
            <div class="mb-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <form method="GET" action="{{ route('tags.index') }}" class="flex-1 max-w-md">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <div class="relative">
                        <input type="text" name="search" value="{{ $search }}" placeholder="Search tags..." class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 pl-10 pr-4 py-2">
                        <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                </form>
                <div class="flex items-center space-x-2 text-sm">
                    <span class="text-gray-500">Sort:</span>
                    <a href="{{ route('tags.index', ['sort' => 'name', 'search' => $search]) }}" class="px-2 py-1 rounded {{ $sort === 'name' ? 'bg-indigo-100 text-indigo-700 font-medium' : 'text-gray-500 hover:text-gray-700' }}">A-Z</a>
                    <a href="{{ route('tags.index', ['sort' => 'popular', 'search' => $search]) }}" class="px-2 py-1 rounded {{ $sort === 'popular' ? 'bg-indigo-100 text-indigo-700 font-medium' : 'text-gray-500 hover:text-gray-700' }}">Popular</a>
                    <a href="{{ route('tags.index', ['sort' => 'latest', 'search' => $search]) }}" class="px-2 py-1 rounded {{ $sort === 'latest' ? 'bg-indigo-100 text-indigo-700 font-medium' : 'text-gray-500 hover:text-gray-700' }}">Latest</a>
                </div>
            </div>

            {{-- Tag Cloud --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex flex-wrap gap-3">
                    @forelse ($tags as $tag)
                        <div class="group relative inline-flex items-center">
                            <a href="{{ route('tags.show', $tag) }}" class="inline-flex items-center px-4 py-2 rounded-full border border-gray-200 bg-gray-50 text-sm text-gray-700 hover:bg-indigo-50 hover:border-indigo-300 hover:text-indigo-700 transition-colors">
                                <svg class="w-3.5 h-3.5 mr-1.5 text-gray-400 group-hover:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                {{ $tag->name }}
                                <span class="ml-2 bg-gray-200 text-gray-600 text-xs px-1.5 py-0.5 rounded-full group-hover:bg-indigo-100 group-hover:text-indigo-600">{{ $tag->courses_count }}</span>
                            </a>
                            @can('manage tags')
                                <div class="hidden group-hover:flex absolute -top-1 -right-1 space-x-0.5">
                                    <a href="{{ route('tags.edit', $tag) }}" class="p-1 bg-white border border-gray-200 rounded-full shadow-sm hover:bg-indigo-50" title="Edit">
                                        <svg class="w-3 h-3 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </a>
                                    <form method="POST" action="{{ route('tags.destroy', $tag) }}" class="inline" onsubmit="return confirm('Delete this tag?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="p-1 bg-white border border-gray-200 rounded-full shadow-sm hover:bg-red-50" title="Delete">
                                            <svg class="w-3 h-3 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </form>
                                </div>
                            @endcan
                        </div>
                    @empty
                        <div class="w-full text-center py-8">
                            <p class="text-gray-500 text-sm">No tags found.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Pagination --}}
            <div class="mt-6">
                {{ $tags->links() }}
            </div>
        </div>
    </div>

    {{-- Quick Add Tag Modal --}}
    <div id="quick-tag-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Quick Add Tag</h3>
                <button onclick="document.getElementById('quick-tag-modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div id="quick-tag-success" class="hidden mb-3 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm"></div>
            <div id="quick-tag-error" class="hidden mb-3 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm"></div>
            <div class="flex space-x-2">
                <input type="text" id="quick-tag-input" placeholder="Tag name..." class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <button onclick="quickAddTag()" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium transition-colors">
                    Add
                </button>
            </div>
        </div>
    </div>

    <script>
        async function quickAddTag() {
            const input = document.getElementById('quick-tag-input');
            const name = input.value.trim();
            const successEl = document.getElementById('quick-tag-success');
            const errorEl = document.getElementById('quick-tag-error');

            successEl.classList.add('hidden');
            errorEl.classList.add('hidden');

            if (!name) return;

            try {
                const response = await fetch('{{ route("tags.quick-store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ name }),
                });

                const data = await response.json();

                if (response.ok) {
                    successEl.textContent = `Tag "${data.tag.name}" created successfully!`;
                    successEl.classList.remove('hidden');
                    input.value = '';
                    setTimeout(() => location.reload(), 1000);
                } else {
                    const errors = data.errors ? Object.values(data.errors).flat().join(' ') : 'Something went wrong.';
                    errorEl.textContent = errors;
                    errorEl.classList.remove('hidden');
                }
            } catch (err) {
                errorEl.textContent = 'Network error. Please try again.';
                errorEl.classList.remove('hidden');
            }
        }

        document.getElementById('quick-tag-input')?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') quickAddTag();
        });
    </script>
</x-app-layout>
