<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">Discover Lost &amp; Found Items</h1>
            <p class="mt-2 text-sm text-gray-600">Browse open reports across communities and help reunite items with their owners.</p>
        </div>
        @auth
            <x-ui.button 
                variant="primary" 
                size="lg" 
                wire:navigate 
                href="{{ route('reports.create') }}"
                tag="a"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Create Report
            </x-ui.button>
        @endauth
    </div>

    {{-- Enhanced Filters --}}
    <x-ui.card padding="default">
        <div class="space-y-4">
            <h3 class="text-sm font-semibold text-gray-900">Filters</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="search" class="block text-xs font-medium text-gray-700">Search</label>
                    <input 
                        type="search" 
                        id="search"
                        wire:model.live.debounce.300ms="search" 
                        placeholder="Title, description, location…"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500"
                        aria-label="Search reports"
                    >
                </div>
                <div>
                    <label for="community" class="block text-xs font-medium text-gray-700">Community</label>
                    <select 
                        id="community"
                        wire:model.live="communityId"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500"
                        aria-label="Filter by community"
                    >
                        <option value="">All communities</option>
                        @foreach ($communities as $community)
                            <option value="{{ $community->id }}">{{ $community->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="category" class="block text-xs font-medium text-gray-700">Category</label>
                    <select 
                        id="category"
                        wire:model.live="categoryId"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500"
                        aria-label="Filter by category"
                    >
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="type" class="block text-xs font-medium text-gray-700">Type</label>
                    <select 
                        id="type"
                        wire:model.live="type"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500"
                        aria-label="Filter by type"
                    >
                        <option value="">Lost &amp; Found</option>
                        <option value="lost">Lost</option>
                        <option value="found">Found</option>
                    </select>
                </div>
            </div>
            <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                <p class="text-xs text-gray-500">
                    <span wire:loading.remove wire:target="search,communityId,categoryId,type">
                        Showing {{ $reports->count() }} of {{ $reports->total() }} reports
                    </span>
                    <span wire:loading wire:target="search,communityId,categoryId,type" class="text-indigo-600">
                        Searching...
                    </span>
                </p>
                @if($search || $communityId || $categoryId || $type)
                    <button 
                        wire:click="clearFilters" 
                        type="button"
                        class="text-sm font-medium text-indigo-600 transition hover:text-indigo-500"
                    >
                        Clear all filters
                    </button>
                @endif
            </div>
        </div>
    </x-ui.card>

    {{-- Loading Skeleton --}}
    <div wire:loading.delay wire:target="search,communityId,categoryId,type" class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
        @for($i = 0; $i < 6; $i++)
            <x-ui.skeleton type="card" />
        @endfor
    </div>

    {{-- Results --}}
    <div wire:loading.remove wire:target="search,communityId,categoryId,type">
        @if ($reports->count())
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($reports as $report)
                    <x-ui.card interactive padding="none" class="overflow-hidden">
                        <div class="p-5">
                            <div class="flex items-center justify-between">
                                <x-ui.badge :variant="$report->type">
                                    {{ ucfirst($report->type) }}
                                </x-ui.badge>
                                <time class="text-xs text-gray-400" datetime="{{ $report->created_at->toIso8601String() }}">
                                    {{ $report->created_at->diffForHumans() }}
                                </time>
                            </div>

                            <h3 class="mt-3 text-lg font-semibold text-gray-900 line-clamp-1">
                                {{ $report->title }}
                            </h3>
                            <p class="mt-2 text-sm text-gray-600 line-clamp-2">
                                {{ $report->description }}
                            </p>

                            <dl class="mt-4 space-y-2 border-t border-gray-100 pt-4">
                                <div class="flex items-center justify-between text-xs">
                                    <dt class="font-medium text-gray-500">Community</dt>
                                    <dd class="text-gray-900">{{ $report->community?->name }}</dd>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <dt class="font-medium text-gray-500">Category</dt>
                                    <dd class="text-gray-900">{{ $report->category?->name }}</dd>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <dt class="font-medium text-gray-500">Location</dt>
                                    <dd class="text-gray-900">{{ $report->location_name }}</dd>
                                </div>
                            </dl>

                            <div class="mt-4 flex gap-2">
                                <x-ui.button 
                                    variant="primary" 
                                    size="sm" 
                                    class="flex-1"
                                    wire:navigate 
                                    href="{{ route('reports.show', $report) }}"
                                    tag="a"
                                >
                                    View Details
                                </x-ui.button>
                                <x-ui.button 
                                    variant="outline" 
                                    size="sm"
                                    x-data="copyToClipboard()"
                                    @click="copy('{{ route('reports.show', $report) }}')"
                                    x-bind:class="{ 'bg-green-50': copied }"
                                    title="Copy link"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" x-show="!copied">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                    </svg>
                                    <svg class="h-4 w-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" x-show="copied" x-cloak>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </x-ui.button>
                            </div>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $reports->links() }}
            </div>
        @else
            <div class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-white p-12 text-center">
                <svg class="mb-4 h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-4.35-4.35m1.85-5.4a7.25 7.25 0 11-14.5 0 7.25 7.25 0 0114.5 0z" />
                </svg>
                <h3 class="text-lg font-semibold text-gray-900">No reports found</h3>
                <p class="mt-1 text-sm text-gray-500">Try adjusting your filters to see more results.</p>
                @if($search || $communityId || $categoryId || $type)
                    <x-ui.button 
                        variant="outline" 
                        size="md" 
                        class="mt-4"
                        wire:click="clearFilters"
                    >
                        Clear all filters
                    </x-ui.button>
                @endif
            </div>
        @endif
    </div>
</div>
