<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">My Dashboard</h1>
            <p class="mt-1 text-sm text-gray-600">Manage your reports and track matches</p>
        </div>
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
            New Report
        </x-ui.button>
    </div>

    <!-- Statistics Cards -->
    @if ($reports->total() > 0)
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.card padding="default">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Reports</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900">{{ $reports->total() }}</p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100">
                        <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card padding="default">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Active (Open)</p>
                        <p class="mt-2 text-3xl font-bold text-green-600">
                            {{ $reports->where('status', 'open')->count() }}
                        </p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                        <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card padding="default">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Matches</p>
                        <p class="mt-2 text-3xl font-bold text-amber-600">
                            {{ $reports->sum(fn($r) => $r->lost_matches_count + $r->found_matches_count) }}
                        </p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                        <svg class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card padding="default">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Resolved</p>
                        <p class="mt-2 text-3xl font-bold text-blue-600">
                            {{ $reports->where('status', 'resolved')->count() }}
                        </p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100">
                        <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>
            </x-ui.card>
        </div>
    @endif

    <!-- Reports Table -->
    @if ($reports->count())
        <x-ui.card padding="none" class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700">
                                Title
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700">
                                Type
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700">
                                Matches
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700">
                                Created
                            </th>
                            <th scope="col" class="relative px-6 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach ($reports as $report)
                            <tr class="transition hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center">
                                        <div>
                                            <div class="font-medium text-gray-900">{{ $report->title }}</div>
                                            <div class="text-xs text-gray-500">{{ $report->community?->name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <x-ui.badge :variant="$report->type" size="sm">
                                        {{ ucfirst($report->type) }}
                                    </x-ui.badge>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <x-ui.badge 
                                        :variant="match($report->status) {
                                            'open' => 'success',
                                            'resolved' => 'primary',
                                            'expired' => 'default',
                                            default => 'warning'
                                        }" 
                                        size="sm"
                                    >
                                        {{ ucfirst(str_replace('_', ' ', $report->status)) }}
                                    </x-ui.badge>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center gap-1 text-sm">
                                        @php($matchCount = $report->lost_matches_count + $report->found_matches_count)
                                        <span class="font-semibold {{ $matchCount > 0 ? 'text-indigo-600' : 'text-gray-500' }}">
                                            {{ $matchCount }}
                                        </span>
                                        @if($matchCount > 0)
                                            <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                            </svg>
                                        @endif
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    <time datetime="{{ $report->created_at->toIso8601String() }}">
                                        {{ $report->created_at->format('M d, Y') }}
                                    </time>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-2">
                                        <x-ui.button 
                                            variant="ghost" 
                                            size="sm"
                                            wire:navigate 
                                            href="{{ route('reports.matches', $report) }}"
                                            tag="a"
                                            title="View matches"
                                        >
                                            Matches
                                        </x-ui.button>
                                        <x-ui.button 
                                            variant="ghost" 
                                            size="sm"
                                            wire:navigate 
                                            href="{{ route('reports.show', $report) }}"
                                            tag="a"
                                            title="View report details"
                                        >
                                            View
                                        </x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        <div class="mt-6">
            {{ $reports->links() }}
        </div>
    @else
        <div class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-white p-12 text-center">
            <svg class="mb-4 h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="text-lg font-semibold text-gray-900">No reports yet</h3>
            <p class="mt-1 text-sm text-gray-500">Get started by creating your first report.</p>
            <x-ui.button 
                variant="primary" 
                size="md" 
                class="mt-4"
                wire:navigate 
                href="{{ route('reports.create') }}"
                tag="a"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Create Report
            </x-ui.button>
        </div>
    @endif
</div>
