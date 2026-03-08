<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center space-x-2">
            <a href="{{ route('categories.index') }}" class="text-gray-500 hover:text-gray-700">Categories</a>
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-gray-800 font-semibold">{{ __('Edit') }}: {{ $category->name }}</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-xl p-6 md:p-8">
                <form method="POST" action="{{ route('categories.update', $category) }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    {{-- Category Name --}}
                    <div>
                        <x-input-label for="name" :value="__('Category Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $category->name)" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    {{-- Description --}}
                    <div class="mt-4">
                        <x-input-label for="description" :value="__('Description')" />
                        <textarea id="description" name="description" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description', $category->description) }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    {{-- Parent Category --}}
                    <div class="mt-4">
                        <x-input-label for="parent_id" :value="__('Parent Category (Optional)')" />
                        <select id="parent_id" name="parent_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">— None (Root Category) —</option>
                            @foreach ($parentCategories as $parent)
                                <option value="{{ $parent->id }}" {{ old('parent_id', $category->parent_id) == $parent->id ? 'selected' : '' }}>
                                    {{ $parent->name }}
                                </option>
                                @foreach ($parent->children as $child)
                                    <option value="{{ $child->id }}" {{ old('parent_id', $category->parent_id) == $child->id ? 'selected' : '' }}>
                                        &nbsp;&nbsp;&nbsp;↳ {{ $child->name }}
                                    </option>
                                @endforeach
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('parent_id')" class="mt-2" />
                    </div>

                    {{-- Icon Upload --}}
                    <div class="mt-4">
                        <x-input-label for="icon" :value="__('Category Icon/Image')" />
                        <div class="mt-1 flex items-center space-x-4">
                            <div id="icon-preview" class="h-16 w-16 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center overflow-hidden flex-shrink-0">
                                @if ($category->icon)
                                    <img src="{{ asset('storage/' . $category->icon) }}" alt="" class="h-16 w-16 object-contain">
                                @else
                                    <svg class="h-8 w-8 text-white/70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                @endif
                            </div>
                            <div>
                                <input id="icon" type="file" name="icon" accept="image/jpeg,image/png,image/svg+xml,image/webp" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" onchange="previewIcon(this)">
                                @if ($category->icon)
                                    <p class="mt-1 text-xs text-gray-500">Current icon will be kept if no new file is uploaded.</p>
                                @endif
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('icon')" class="mt-2" />
                    </div>

                    {{-- Sort Order --}}
                    <div class="mt-4">
                        <x-input-label for="sort_order" :value="__('Sort Order')" />
                        <x-text-input id="sort_order" name="sort_order" type="number" class="mt-1 block w-32" :value="old('sort_order', $category->sort_order)" min="0" />
                        <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                    </div>

                    {{-- Status --}}
                    <div class="mt-4">
                        <label for="is_active" class="inline-flex items-center">
                            <input id="is_active" type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ old('is_active', $category->is_active) ? 'checked' : '' }}>
                            <span class="ms-2 text-sm text-gray-600">{{ __('Active (visible to students)') }}</span>
                        </label>
                    </div>

                    {{-- Slug preview --}}
                    <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                        <p class="text-xs text-gray-500">
                            <span class="font-medium">Current slug:</span>
                            <code class="ml-1 text-indigo-600">{{ $category->slug }}</code>
                        </p>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center justify-between mt-6 pt-6 border-t border-gray-100">
                        <form method="POST" action="{{ route('categories.destroy', $category) }}" onsubmit="return confirm('Are you sure you want to delete this category?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-sm text-red-600 hover:text-red-800 font-medium">Delete Category</button>
                        </form>
                        <div class="flex items-center space-x-3">
                            <a href="{{ route('categories.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
                            <x-primary-button>
                                {{ __('Update Category') }}
                            </x-primary-button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function previewIcon(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('icon-preview').innerHTML =
                        '<img src="' + e.target.result + '" class="h-16 w-16 object-contain">';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</x-app-layout>
