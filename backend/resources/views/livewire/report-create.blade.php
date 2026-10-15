<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Create a Report</h1>
        <p class="mt-1 text-sm text-gray-500">Report a lost or found item in a few quick steps.</p>
    </div>

    <ol class="flex items-center justify-between text-xs font-medium text-gray-400">
        @foreach (['Classify', 'Details', 'When & Where', 'Photos'] as $i => $label)
            @php($n = $i + 1)
            <li @class(['flex-1 text-center', 'text-indigo-600' => $step >= $n])>
                <span @class([
                    'mx-auto mb-1 flex h-7 w-7 items-center justify-center rounded-full border',
                    'border-indigo-600 bg-indigo-600 text-white' => $step >= $n,
                    'border-gray-300' => $step < $n,
                ])>{{ $n }}</span>
                {{ $label }}
            </li>
        @endforeach
    </ol>

    <form wire:submit="save" class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        @if ($step === 1)
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Community</label>
                    <select wire:model.live="community_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select a community…</option>
                        @foreach ($this->communities() as $community)
                            <option value="{{ $community->id }}">{{ $community->name }}</option>
                        @endforeach
                    </select>
                    @error('community_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Category</label>
                    <select wire:model="category_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select a category…</option>
                        @foreach ($this->categories() as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Type</label>
                    <div class="mt-1 flex gap-3">
                        <label @class(['flex-1 cursor-pointer rounded-md border p-3 text-center text-sm font-medium', 'border-indigo-600 bg-indigo-50 text-indigo-700' => $type === 'lost', 'border-gray-300' => $type !== 'lost'])>
                            <input type="radio" wire:model.live="type" value="lost" class="sr-only"> I lost something
                        </label>
                        <label @class(['flex-1 cursor-pointer rounded-md border p-3 text-center text-sm font-medium', 'border-indigo-600 bg-indigo-50 text-indigo-700' => $type === 'found', 'border-gray-300' => $type !== 'found'])>
                            <input type="radio" wire:model.live="type" value="found" class="sr-only"> I found something
                        </label>
                    </div>
                    @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        @endif

        @if ($step === 2)
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Title</label>
                    <input type="text" wire:model.blur="title" maxlength="120" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea wire:model.blur="description" rows="4" maxlength="3000" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Color</label>
                        <input type="text" wire:model.blur="color" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('color') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Brand</label>
                        <input type="text" wire:model.blur="brand" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('brand') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        @endif
        @if ($step === 3)
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">When did it happen?</label>
                    <input type="datetime-local" wire:model.blur="event_at" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('event_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Location name</label>
                    <input type="text" wire:model.blur="location_name" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('location_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Latitude <span class="text-gray-400">(optional)</span></label>
                        <input type="text" wire:model.blur="latitude" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('latitude') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Longitude <span class="text-gray-400">(optional)</span></label>
                        <input type="text" wire:model.blur="longitude" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('longitude') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        @endif

        @if ($step === 4)
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Photos <span class="text-gray-400">(up to 4)</span></label>
                    <input type="file" wire:model="photos" multiple accept="image/jpeg,image/png,image/webp"
                           class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                    <div wire:loading wire:target="photos" class="mt-1 text-xs text-gray-500">Uploading…</div>
                    @error('photos.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    @error('photos') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                @if (count($photos))
                    <ul class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ($photos as $i => $photo)
                            <li class="relative overflow-hidden rounded-md border border-gray-200">
                                <img src="{{ $photo->temporaryUrl() }}" class="h-24 w-full object-cover" alt="">
                                <button type="button" wire:click="removePhoto({{ $i }})"
                                        class="absolute right-1 top-1 rounded-full bg-white/90 px-1.5 text-xs font-bold text-red-600 shadow">×</button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        <div class="flex items-center justify-between border-t border-gray-100 pt-4">
            <div>
                @if ($step > 1)
                    <button type="button" wire:click="previousStep" class="rounded-md px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900">Back</button>
                @endif
            </div>
            <div>
                @if ($step < 4)
                    <button type="button" wire:click="nextStep" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Continue</button>
                @else
                    <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">Submit Report</span>
                        <span wire:loading wire:target="save">Submitting…</span>
                    </button>
                @endif
            </div>
        </div>
    </form>
</div>