<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Discover Lost &amp; Found Items</h1>
            <p class="mt-1 text-sm text-gray-500">Browse open reports across communities.</p>
        </div>
        @auth
            <a href="{{ route('reports.create') }}"
               class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                + Create Report
            </a>
        @endauth
    </div>

    {{-- Filters --}}
    <div class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label class="block text-xs font-medium text-gray-600">Search</label>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Title, description, location…"
                   class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600">Community</label>
            <select wire:model.live="communityId"
                    class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All communities</option>
                @foreach ($communities as $community)
                    <option value="{{ $community->id }}">{{ $community->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600">Category</label>
            <select wire:model.live="categoryId"
                    class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600">Type</label>
            <select wire:model.live="type"
                    class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Lost &amp; Found</option>
                <option value="lost">Lost</option>
                <option value="found">Found</option>
            </select>
        </div>
        <div class="sm:col-span-2 lg:col-span-4">
            <button wire:click="clearFilters" type="button"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Clear all filters</button>
        </div>
    </div>

    {{-- Results --}}
    @if ($reports->count())
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($reports as $report)
                <div class="flex flex-col rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span @class([
                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                            'bg-red-100 text-red-700' => $report->type === 'lost',
                            'bg-green-100 text-green-700' => $report->type === 'found',
                        ])>
                            {{ ucfirst($report->type) }}
                        </span>
                        <span class="text-xs text-gray-400">{{ $report->created_at->diffForHumans() }}</span>
                    </div>

                    <h3 class="mt-2 text-base font-semibold text-gray-900">{{ $report->title }}</h3>
                    <p class="mt-1 line-clamp-2 text-sm text-gray-600">{{ $report->description }}</p>

                    <dl class="mt-3 space-y-1 text-xs text-gray-500">
                        <div class="flex justify-between"><dt>Community</dt><dd class="text-gray-700">{{ $report->community?->name }}</dd></div>
                        <div class="flex justify-between"><dt>Category</dt><dd class="text-gray-700">{{ $report->category?->name }}</dd></div>
                        <div class="flex justify-between"><dt>Location</dt><dd class="text-gray-700">{{ $report->location_name }}</dd></div>
                    </dl>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $reports->links() }}
        </div>
    @else
        <div class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center">
            <p class="text-sm text-gray-500">No reports match your filters.</p>
        </div>
    @endif
</div>
