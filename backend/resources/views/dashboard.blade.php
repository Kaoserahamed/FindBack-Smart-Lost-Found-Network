<x-layout>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">My Dashboard</h1>
            <a href="{{ route('reports.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">+ New Report</a>
        </div>

        @php($myReports = \App\Models\Report::where('user_id', auth()->id())->latest()->get())

        @if ($myReports->count())
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Title</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Matches</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($myReports as $report)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $report->title }}</td>
                                <td class="px-4 py-3">{{ ucfirst($report->type) }}</td>
                                <td class="px-4 py-3">{{ ucfirst(str_replace('_', ' ', $report->status)) }}</td>
                                <td class="px-4 py-3">{{ $report->lostMatches()->count() + $report->foundMatches()->count() }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('reports.matches', $report) }}" class="font-medium text-indigo-600 hover:text-indigo-500">View matches</a>
                                    <a href="{{ route('reports.show', $report) }}" class="ml-3 font-medium text-gray-500 hover:text-gray-700">Open</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center">
                <p class="text-sm text-gray-500">You have not created any reports yet.</p>
            </div>
        @endif
    </div>
</x-layout>